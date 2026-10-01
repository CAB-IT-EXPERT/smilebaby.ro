document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('.home-testimonials');
  const track = root?.querySelector('[data-testimonials-track]');
  const cards = track ? [...track.querySelectorAll('.testimonial-card')] : [];
  const dotsContainer = root?.querySelector('[data-testimonial-dots]');
  if (!root || !track || !dotsContainer || cards.length < 2) return;

  let stops = [];
  let active = 0;
  let frame = 0;
  let dragging = false;
  let axis = null;
  let pointerId = null;
  let startX = 0;
  let startY = 0;
  let startScroll = 0;
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let timer = 0;
  let pauseUntil = 0;
  let visible = !('IntersectionObserver' in window);
  let hovered = false;
  let focused = false;

  const dots = () => [...dotsContainer.querySelectorAll('[data-testimonial-dot]')];

  const setActive = index => {
    active = Math.max(0, Math.min(stops.length - 1, index));
    dots().forEach((dot, i) => {
      dot.classList.toggle('active', i === active);
      dot.setAttribute('aria-current', i === active ? 'true' : 'false');
    });
  };

  const closestIndex = () => {
    let best = 0;
    let distance = Infinity;
    stops.forEach((stop, index) => {
      const candidate = Math.abs(stop - track.scrollLeft);
      if (candidate < distance) { distance = candidate; best = index; }
    });
    return best;
  };

  const goTo = (index, smooth = true) => {
    setActive((index + stops.length) % stops.length);
    track.scrollTo({ left: stops[active], behavior: smooth && !reducedMotion ? 'smooth' : 'auto' });
  };

  const schedule = () => {
    clearTimeout(timer);
    if (reducedMotion || !visible || hovered || focused || dragging || document.hidden || stops.length < 2) return;
    timer = setTimeout(() => {
      goTo(active + 1);
      schedule();
    }, Math.max(1700, pauseUntil - Date.now()));
  };

  const pauseAfterInteraction = () => {
    pauseUntil = Date.now() + 4000;
    schedule();
  };

  const buildStops = () => {
    const max = Math.max(0, track.scrollWidth - track.clientWidth);
    stops = cards.map(card => Math.min(max, Math.max(0, card.offsetLeft - track.offsetLeft)));
    stops = stops.filter((stop, index, all) => index === 0 || Math.abs(stop - all[index - 1]) > 3);
    if (stops.length > 2 && stops[stops.length - 1] - stops[stops.length - 2] < cards[0].clientWidth / 2) {
      stops.splice(stops.length - 2, 1);
    }
    if (!stops.length) stops = [0];
    dots().forEach(dot => dot.remove());
    stops.forEach((_, index) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.dataset.testimonialDot = '';
      dot.setAttribute('aria-label', `Arată recenzia ${index + 1}`);
      dot.addEventListener('click', () => { goTo(index); pauseAfterInteraction(); });
      dotsContainer.insertBefore(dot, dotsContainer.querySelector('small'));
    });
    setActive(closestIndex());
    schedule();
  };

  track.addEventListener('scroll', () => {
    if (frame) return;
    frame = requestAnimationFrame(() => { frame = 0; setActive(closestIndex()); });
  }, { passive: true });

  track.addEventListener('pointerdown', event => {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    dragging = true;
    axis = null;
    pointerId = event.pointerId;
    startX = event.clientX;
    startY = event.clientY;
    startScroll = track.scrollLeft;
    clearTimeout(timer);
  });

  track.addEventListener('pointermove', event => {
    if (!dragging || event.pointerId !== pointerId) return;
    const dx = event.clientX - startX;
    const dy = event.clientY - startY;
    if (!axis && Math.max(Math.abs(dx), Math.abs(dy)) > 7) axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
    if (axis !== 'x') return;
    if (!track.hasPointerCapture?.(pointerId)) track.setPointerCapture?.(pointerId);
    track.classList.add('dragging');
    event.preventDefault();
    track.scrollLeft = startScroll - dx;
  });

  const finish = event => {
    if (!dragging || event.pointerId !== pointerId) return;
    dragging = false;
    if (track.hasPointerCapture?.(pointerId)) track.releasePointerCapture(pointerId);
    track.classList.remove('dragging');
    if (axis === 'x') goTo(closestIndex());
    axis = null;
    pointerId = null;
    pauseAfterInteraction();
  };

  track.addEventListener('pointerup', finish);
  track.addEventListener('pointercancel', finish);
  track.addEventListener('dragstart', event => event.preventDefault());
  root.addEventListener('mouseenter', () => { hovered = true; clearTimeout(timer); });
  root.addEventListener('mouseleave', () => { hovered = false; schedule(); });
  root.addEventListener('focusin', () => { focused = true; clearTimeout(timer); });
  root.addEventListener('focusout', () => { focused = root.contains(document.activeElement); schedule(); });
  document.addEventListener('visibilitychange', schedule);
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(entries => { visible = entries[0].isIntersecting; schedule(); }, { threshold: 0.2 }).observe(root);
  }
  addEventListener('resize', buildStops);
  buildStops();
});
