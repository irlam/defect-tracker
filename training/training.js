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
})();
