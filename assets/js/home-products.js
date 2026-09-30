(() => {
  const root = document.querySelector('[data-product-carousel]');
  if (!root) return;

  const viewport = root.querySelector('[data-product-viewport]');
  const cards = [...root.querySelectorAll('[data-product-track] > .product-card')];
  const dots = [...root.querySelectorAll('[data-product-dot]')];
  if (!viewport || cards.length < 2) return;

  let active = 0;
  let stops = [];
  let timer = 0;
  let scrollFrame = 0;
  let dragging = false;
  let moved = false;
  let axis = null;
  let startX = 0;
  let startY = 0;
  let startScroll = 0;
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const setActive = (index) => {
    const count = Math.max(stops.length, 1);
    active = (index + count) % count;
    dots.forEach((dot, i) => dot.classList.toggle('active', i === active && i < count));
  };

  const goTo = (index, smooth = true) => {
    setActive(index);
    viewport.scrollTo({ left: stops[active] || 0, behavior: smooth && !reducedMotion ? 'smooth' : 'auto' });
  };

  const closestStop = () => {
    const left = viewport.scrollLeft;
    let best = 0;
    let distance = Infinity;
    stops.forEach((stop, index) => {
      const value = Math.abs(stop - left);
      if (value < distance) { distance = value; best = index; }
    });
    setActive(best);
  };

  const buildStops = () => {
    const max = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
    const rawStops = cards.map(card => Math.min(max, Math.max(0, card.offsetLeft - viewport.offsetLeft)));
    stops = rawStops.filter((value, index, values) => index === 0 || Math.abs(value - values[index - 1]) > 2);
    if (!stops.length) stops = [0];
    if (max > 1 && Math.abs(stops[stops.length - 1] - max) > 2) stops.push(max);
    dots.forEach((dot, index) => { dot.hidden = index >= stops.length; });
    if (active >= stops.length) active = stops.length - 1;
    closestStop();
  };

  const stop = () => { if (timer) window.clearTimeout(timer); timer = 0; };
  const start = (delay = 1700) => {
    stop();
    if (!reducedMotion && !document.hidden) timer = window.setTimeout(() => { goTo(active + 1); start(); }, delay);
  };

  root.querySelector('[data-product-prev]')?.addEventListener('click', () => { goTo(active - 1); start(); });
  root.querySelector('[data-product-next]')?.addEventListener('click', () => { goTo(active + 1); start(); });
  dots.forEach((dot, index) => dot.addEventListener('click', () => { goTo(index); start(); }));
  viewport.addEventListener('scroll', () => {
    if (scrollFrame) return;
    scrollFrame = window.requestAnimationFrame(() => {
      scrollFrame = 0;
      closestStop();
    });
  }, { passive: true });
  viewport.addEventListener('pointerdown', (event) => {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    dragging = true;
    moved = false;
    axis = null;
    startX = event.clientX;
    startY = event.clientY;
    startScroll = viewport.scrollLeft;
    viewport.classList.add('dragging');
    stop();
  });
  viewport.addEventListener('pointermove', (event) => {
    if (!dragging) return;
    const dx = event.clientX - startX;
    const dy = event.clientY - startY;
    if (!axis && Math.max(Math.abs(dx), Math.abs(dy)) > 6) axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
    if (axis === 'y') return;
    if (axis === 'x') {
      moved = true;
      if (!viewport.hasPointerCapture?.(event.pointerId)) viewport.setPointerCapture?.(event.pointerId);
      event.preventDefault();
      viewport.scrollLeft = startScroll - dx;
    }
  });
  const finishDrag = (event) => {
    if (!dragging) return;
    dragging = false;
    if (viewport.hasPointerCapture?.(event.pointerId)) viewport.releasePointerCapture(event.pointerId);
    viewport.classList.remove('dragging');
    closestStop();
    if (axis === 'x') goTo(active);
    start(1700);
  };
  viewport.addEventListener('pointerup', finishDrag);
  viewport.addEventListener('pointercancel', finishDrag);
  viewport.addEventListener('click', (event) => {
    if (!moved) return;
    event.preventDefault();
    event.stopPropagation();
    moved = false;
  }, true);
  viewport.addEventListener('dragstart', event => event.preventDefault());
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
  window.addEventListener('resize', () => {
    const progress = viewport.scrollWidth > viewport.clientWidth
      ? viewport.scrollLeft / (viewport.scrollWidth - viewport.clientWidth)
      : 0;
    buildStops();
    viewport.scrollTo({ left: progress * Math.max(0, viewport.scrollWidth - viewport.clientWidth), behavior: 'auto' });
    closestStop();
  });
  buildStops();
  start();
})();
