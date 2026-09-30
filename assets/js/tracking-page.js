document.addEventListener('DOMContentLoaded', () => {
  const page = document.querySelector('[data-tracking-page]');
  if (!page) return;

  requestAnimationFrame(() => page.classList.add('is-ready'));

  const cards = page.querySelectorAll('.tracking-journey-card,.tracking-info-card,.tracking-info-stack,.tracking-help-card,.tracking-exception');
  if (!('IntersectionObserver' in window)) {
    cards.forEach(card => card.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.12 });
  cards.forEach(card => observer.observe(card));
});
