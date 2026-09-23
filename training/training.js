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

  const lesson = document.querySelector('[data-training-lesson]');
  if (lesson && lesson.dataset.schemaReady === '1') {
    const lessonId = lesson.dataset.lessonId;
    const csrf = lesson.dataset.csrf;
    const saveStatus = document.getElementById('training-save-status');

    const saveProgress = async (status, percent) => {
      const body = new URLSearchParams({
        lesson_id: lessonId,
        status,
        progress_percent: String(percent),
        csrf_token: csrf
      });

      const response = await fetch('/training/progress.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
        body
      });

      if (!response.ok) throw new Error('Progress update failed');
      return response.json();
    };

    saveProgress('in_progress', 10).catch(() => {
      if (saveStatus) saveStatus.textContent = 'Progress could not be saved';
    });

    const completeButton = document.querySelector('[data-training-complete]');
    if (completeButton) {
      completeButton.addEventListener('click', async () => {
        completeButton.disabled = true;
        if (saveStatus) saveStatus.textContent = 'Saving completion…';
        try {
          const result = await saveProgress('completed', 100);
          if (!result.success) throw new Error('Progress update failed');
          if (saveStatus) saveStatus.textContent = 'Lesson complete';
          completeButton.innerHTML = '<i class="bx bx-check-double me-1"></i>Completed';
        } catch (error) {
          completeButton.disabled = false;
          if (saveStatus) saveStatus.textContent = 'Could not save completion';
        }
      });
    }
  }

})();
