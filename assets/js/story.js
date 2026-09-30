document.addEventListener('DOMContentLoaded', () => {
  const story = document.querySelector('[data-story-home]');
  if (!story) return;

  const steps = [...story.querySelectorAll('[data-story-step]')];
  const panels = [...story.querySelectorAll('[data-story-panel]')];
  const stepBar = story.querySelector('.story-steps');
  const header = document.querySelector('.site-header');

  const activate = (index) => {
    steps.forEach((step, position) => {
      const current = position === index;
      step.classList.toggle('active', current);
      step.setAttribute('aria-current', current ? 'step' : 'false');
    });
  };

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (visible) activate(Number(visible.target.dataset.storyPanel));
    }, { rootMargin: '-24% 0px -48% 0px', threshold: [0.08, 0.3, 0.6] });
    panels.forEach((panel) => observer.observe(panel));
  }

  steps.forEach((step, index) => step.addEventListener('click', () => activate(index)));

  let scheduled = false;
  const updateStoryBar = () => {
    scheduled = false;
    if (!stepBar) return;
    const storyRect = story.getBoundingClientRect();
    const headerBottom = header ? header.getBoundingClientRect().bottom : 0;
    const stickyTop = Math.max(headerBottom, window.innerWidth <= 767 ? 68 : 92);
    const barHeight = stepBar.offsetHeight;
    const pinned = storyRect.top <= stickyTop && storyRect.bottom > stickyTop + barHeight + 36;
    const leaving = storyRect.bottom <= stickyTop + barHeight + 36;
    stepBar.classList.toggle('story-steps-pinned', pinned);
    stepBar.classList.toggle('story-steps-leaving', leaving);
  };

  const requestStoryBarUpdate = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(updateStoryBar);
  };

  addEventListener('scroll', requestStoryBarUpdate, { passive: true });
  addEventListener('resize', requestStoryBarUpdate, { passive: true });
  updateStoryBar();
});
