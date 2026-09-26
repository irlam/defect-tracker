(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.querySelectorAll('[data-training-ring]').forEach((ring) => {
    const value = Math.max(0, Math.min(100, Number(ring.dataset.trainingRing || 0)));
    ring.style.setProperty('--value', value);
    ring.setAttribute('aria-label', value + '% training complete');
  });

  document.querySelectorAll('[data-training-transcript-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.getAttribute('aria-controls');
      const target = targetId ? document.getElementById(targetId) : null;
      if (!target) return;
      const willOpen = target.hasAttribute('hidden');
      target.toggleAttribute('hidden', !willOpen);
      button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      button.textContent = willOpen ? 'Hide transcript' : 'Show transcript';
    });
  });

  if (reduceMotion) {
    document.documentElement.classList.add('training-reduced-motion');
  }

  document.querySelectorAll('[data-training-catalogue]').forEach((catalogue) => {
    const search = catalogue.querySelector('[data-training-search]');
    const filters = Array.from(catalogue.querySelectorAll('[data-training-filter]'));
    const items = Array.from(catalogue.querySelectorAll('[data-training-item]'));
    const empty = catalogue.querySelector('[data-training-no-results]');
    let audience = 'all';

    const appliesToAudience = (roles) => {
      if (audience === 'all' || roles.includes('all')) return true;
      if (audience === 'site') return roles.includes('inspector') || roles.includes('manager') || roles.includes('admin');
      return roles.includes(audience);
    };

    const updateCatalogue = () => {
      const query = String(search?.value || '').trim().toLowerCase();
      let visible = 0;

      items.forEach((item) => {
        const haystack = String(item.dataset.trainingSearchText || '');
        const roles = String(item.dataset.trainingRoles || 'all').toLowerCase();
        const show = (!query || haystack.includes(query)) && appliesToAudience(roles);
        item.hidden = !show;
        if (show) visible += 1;
      });

      if (empty) empty.hidden = visible !== 0;
    };

    search?.addEventListener('input', updateCatalogue);
    filters.forEach((filter) => {
      filter.addEventListener('click', () => {
        audience = String(filter.dataset.trainingFilter || 'all');
        filters.forEach((item) => {
          const active = item === filter;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        updateCatalogue();
      });
    });
  });

  const lesson = document.querySelector('[data-training-lesson]');
  let saveProgress = async () => ({success: false});

  if (lesson && lesson.dataset.schemaReady === '1') {
    const lessonId = lesson.dataset.lessonId;
    const csrf = lesson.dataset.csrf;
    const saveStatus = document.getElementById('training-save-status');

    saveProgress = async (status, percent, lastPosition = '') => {
      const body = new URLSearchParams({
        lesson_id: lessonId,
        status,
        progress_percent: String(percent),
        last_position: lastPosition,
        csrf_token: csrf
      });

      const response = await fetch('/training/progress.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
        body
      });

      if (!response.ok) throw new Error('Progress update failed');
      const result = await response.json();
      if (!result.success) throw new Error(result.message || 'Progress update failed');
      return result;
    };

    saveProgress('in_progress', 10, 'lesson-opened').catch(() => {
      if (saveStatus) saveStatus.textContent = 'Progress could not be saved';
    });

    const completeButton = document.querySelector('[data-training-complete]');
    if (completeButton) {
      completeButton.addEventListener('click', async () => {
        completeButton.disabled = true;
        if (saveStatus) saveStatus.textContent = 'Saving completion…';
        try {
          await saveProgress('completed', 100, 'completed');
          if (saveStatus) saveStatus.textContent = 'Lesson complete';
          completeButton.innerHTML = '<i class="bx bx-check-double me-1"></i>Completed';
          document.querySelectorAll('.training-progress .progress-bar').forEach((bar) => {
            if (bar.closest('.training-lesson-header')) bar.style.width = '100%';
          });
        } catch (error) {
          completeButton.disabled = false;
          if (saveStatus) saveStatus.textContent = 'Could not save completion';
        }
      });
    }
  }

  document.querySelectorAll('[data-training-demo]').forEach((demo) => {
    const scenes = Array.from(demo.querySelectorAll('[data-demo-scene]'));
    const title = demo.querySelector('[data-demo-title]');
    const caption = demo.querySelector('[data-demo-caption]');
    const counter = demo.querySelector('[data-demo-counter]');
    const prev = demo.querySelector('[data-demo-prev]');
    const next = demo.querySelector('[data-demo-next]');
    const play = demo.querySelector('[data-demo-play]');
    const narrate = demo.querySelector('[data-demo-narrate]');
    const dots = Array.from(demo.querySelectorAll('[data-demo-go]'));
    const cursor = demo.querySelector('[data-demo-cursor]');
    const recordedAudio = new Audio();
    recordedAudio.preload = 'none';

    let current = 0;
    let timer = null;
    let speaking = false;
    let autoPlaying = false;

    const animateCursor = () => {
      if (!cursor || reduceMotion) return;
      cursor.classList.remove('is-moving');
      void cursor.offsetWidth;
      cursor.classList.add('is-moving');
    };

    const updateNarrationButton = (active = false) => {
      if (!narrate) return;
      narrate.setAttribute('aria-pressed', active ? 'true' : 'false');
      narrate.innerHTML = active
        ? '<i class="bx bx-stop-circle me-1"></i>Stop narration'
        : '<i class="bx bx-volume-full me-1"></i>Play narration';
    };

    const stopNarration = () => {
      if ('speechSynthesis' in window) window.speechSynthesis.cancel();
      recordedAudio.pause();
      recordedAudio.removeAttribute('src');
      recordedAudio.load();
      recordedAudio.onended = null;
      recordedAudio.onerror = null;
      speaking = false;
      updateNarrationButton(false);
    };

    const narrateCurrent = (onComplete = null) => {
      stopNarration();
      const text = scenes[current]?.dataset.demoVoice || caption?.textContent || '';
      if (!text) return;

      const complete = () => {
        speaking = false;
        updateNarrationButton(false);
        if (typeof onComplete === 'function') onComplete();
      };

      const speakWithBrowser = () => {
        if (!('speechSynthesis' in window)) {
          timer = window.setTimeout(complete, reduceMotion ? 6500 : 5000);
          return;
        }
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 0.96;
        utterance.pitch = 1;
        utterance.onend = complete;
        utterance.onerror = complete;
        speaking = true;
        updateNarrationButton(true);
        window.speechSynthesis.speak(utterance);
      };

      const audioUrl = scenes[current]?.dataset.demoAudio || '';
      if (audioUrl) {
        recordedAudio.src = audioUrl;
        recordedAudio.currentTime = 0;
        recordedAudio.onended = complete;
        recordedAudio.onerror = speakWithBrowser;
        speaking = true;
        updateNarrationButton(true);
        recordedAudio.play().catch(speakWithBrowser);
        return;
      }

      speakWithBrowser();
    };

    const toggleNarration = () => {
      if (speaking) {
        stopNarration();
        return;
      }
      if (autoPlaying) stopAutoPlay();
      narrateCurrent();
    };

    const showScene = (index, userInitiated = true) => {
      current = Math.max(0, Math.min(scenes.length - 1, index));
      stopNarration();

      scenes.forEach((scene, i) => {
        const active = i === current;
        scene.hidden = !active;
        scene.classList.toggle('is-active', active);
      });

      dots.forEach((dot, i) => {
        const active = i === current;
        dot.classList.toggle('is-active', active);
        dot.setAttribute('aria-current', active ? 'step' : 'false');
      });

      const activeScene = scenes[current];

      if (counter) counter.textContent = 'Step ' + (current + 1) + ' of ' + scenes.length;
      if (title && activeScene?.dataset.demoTitle) title.textContent = activeScene.dataset.demoTitle;
      if (caption && activeScene?.dataset.demoCaption) caption.textContent = activeScene.dataset.demoCaption;

      if (prev) prev.disabled = current === 0;
      if (next) {
        next.innerHTML = current === scenes.length - 1
          ? 'Replay <i class="bx bx-revision ms-1"></i>'
          : 'Next <i class="bx bx-right-arrow-alt ms-1"></i>';
      }

      animateCursor();

      if (userInitiated && lesson?.dataset.schemaReady === '1') {
        const percent = Math.min(80, 20 + Math.round(((current + 1) / scenes.length) * 60));
        saveProgress('in_progress', percent, 'demo-step-' + (current + 1)).catch(() => {});
      }
    };

    function stopAutoPlay() {
      if (timer) window.clearTimeout(timer);
      timer = null;
      autoPlaying = false;
      stopNarration();
      if (play) {
        play.setAttribute('aria-pressed', 'false');
        play.innerHTML = '<i class="bx bx-play me-1"></i>Auto play';
      }
    }

    const playSequence = () => {
      if (!autoPlaying) return;
      narrateCurrent(() => {
        if (!autoPlaying) return;
        if (current >= scenes.length - 1) {
          stopAutoPlay();
          return;
        }
        timer = window.setTimeout(() => {
          showScene(current + 1, true);
          playSequence();
        }, 650);
      });
    };

    const chooseScene = (index) => {
      stopAutoPlay();
      showScene(index);
    };

    prev?.addEventListener('click', () => chooseScene(current - 1));
    next?.addEventListener('click', () => chooseScene(current === scenes.length - 1 ? 0 : current + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => chooseScene(Number(dot.dataset.demoGo || 0))));
    narrate?.addEventListener('click', toggleNarration);

    play?.addEventListener('click', () => {
      if (autoPlaying) {
        stopAutoPlay();
        return;
      }
      autoPlaying = true;
      play.setAttribute('aria-pressed', 'true');
      play.innerHTML = '<i class="bx bx-pause me-1"></i>Pause';
      playSequence();
    });

    demo.tabIndex = 0;
    demo.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowRight') {
        event.preventDefault();
        chooseScene(current === scenes.length - 1 ? current : current + 1);
      }
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        chooseScene(current === 0 ? current : current - 1);
      }
    });

    window.addEventListener('beforeunload', () => {
      stopAutoPlay();
      stopNarration();
    });
  });

  document.querySelectorAll('[data-training-quiz]').forEach((quiz) => {
    const questions = Array.from(quiz.querySelectorAll('[data-quiz-question]'));
    const check = quiz.querySelector('[data-quiz-check]');
    const score = quiz.querySelector('[data-quiz-score]');

    check?.addEventListener('click', async () => {
      let correct = 0;

      questions.forEach((question) => {
        const expected = Number(question.dataset.answer || 0);
        const selected = question.querySelector('input[type="radio"]:checked');
        const feedback = question.querySelector('[data-quiz-feedback]');
        const selectedValue = selected ? Number(selected.value) : -1;
        const ok = selectedValue === expected;

        question.classList.toggle('is-correct', ok);
        question.classList.toggle('is-incorrect', !ok);

        if (ok) {
          correct += 1;
          if (feedback) feedback.textContent = 'Correct';
        } else {
          if (feedback) feedback.textContent = selected ? 'Not quite — try this question again.' : 'Choose an answer first.';
        }
      });

      const passed = correct === questions.length && questions.length > 0;
      if (score) {
        score.textContent = passed
          ? 'Passed · ' + correct + '/' + questions.length
          : correct + '/' + questions.length + ' correct';
        score.classList.toggle('text-success', passed);
      }

      if (passed && lesson?.dataset.schemaReady === '1') {
        try {
          await saveProgress('in_progress', 90, 'knowledge-check-passed');
          const saveStatus = document.getElementById('training-save-status');
          if (saveStatus) saveStatus.textContent = 'Knowledge check passed — ready to complete';
        } catch (error) {}
      }
    });
  });
})();
