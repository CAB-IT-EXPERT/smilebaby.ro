document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('.home-testimonials');
  const track = root?.querySelector('[data-testimonials-track]');
  const cards = track ? [...track.querySelectorAll('.testimonial-card')] : [];
  const dots = root ? [...root.querySelectorAll('[data-testimonial-dot]')] : [];
  if (!root || !track || cards.length < 2) return;

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

  const setActive = index => {
    active = Math.max(0, Math.min(stops.length - 1, index));
    dots.forEach((dot, i) => {
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
    setActive(index);
    track.scrollTo({ left: stops[active] || 0, behavior: smooth && !reducedMotion ? 'smooth' : 'auto' });
  };

  const buildStops = () => {
    const max = Math.max(0, track.scrollWidth - track.clientWidth);
    stops = cards.map(card => Math.min(max, Math.max(0, card.offsetLeft - track.offsetLeft)));
    if (!stops.length) stops = [0];
    setActive(closestIndex());
  };

  dots.forEach((dot, index) => dot.addEventListener('click', () => goTo(index)));
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
  };

  track.addEventListener('pointerup', finish);
  track.addEventListener('pointercancel', finish);
  track.addEventListener('dragstart', event => event.preventDefault());
  addEventListener('resize', buildStops);
  buildStops();
});
