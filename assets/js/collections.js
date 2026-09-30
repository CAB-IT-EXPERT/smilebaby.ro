document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-category-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-category-track]');
    const slides = [...carousel.querySelectorAll('[data-category-slide]')];
    const dots = [...carousel.querySelectorAll('[data-category-dot]')];

    if (!track || slides.length < 2) return;

    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const loopLayout = matchMedia('(max-width: 1023px)');
    let visualSlides = slides;
    let active = 0;
    let visualIndex = 0;
    let animationTimer = 0;
    let autoplayTimer = 0;
    let resumeTimer = 0;
    let frame = 0;
    let drag = null;
    let suppressClick = false;
    let programmatic = false;

    if (loopLayout.matches) {
      const before = slides.at(-1).cloneNode(true);
      const after = slides[0].cloneNode(true);

      [before, after].forEach((clone) => {
        clone.removeAttribute('data-category-slide');
        clone.classList.remove('active');
        clone.classList.add('category-clone');
        clone.setAttribute('aria-hidden', 'true');
        clone.setAttribute('tabindex', '-1');
      });

      track.prepend(before);
      track.append(after);
      visualSlides = [before, ...slides, after];
      visualIndex = 1;
    }

    const logicalFromVisual = (index) => {
      if (!loopLayout.matches) return (index + slides.length) % slides.length;
      if (index === 0) return slides.length - 1;
      if (index === visualSlides.length - 1) return 0;
      return index - 1;
    };

    const leftFor = (slide) => slide.offsetLeft - (track.clientWidth - slide.clientWidth) / 2;

    const paint = () => {
      slides.forEach((slide, index) => slide.classList.toggle('active', index === active));
      visualSlides.forEach((slide, index) => slide.classList.toggle('visual-active', index === visualIndex));
      dots.forEach((dot, index) => {
        const selected = index === active;
        dot.classList.toggle('active', selected);
        dot.setAttribute('aria-current', selected ? 'true' : 'false');
      });
    };

    const jump = (index) => {
      visualIndex = index;
      const oldBehavior = track.style.scrollBehavior;
      track.style.scrollBehavior = 'auto';
      track.scrollLeft = leftFor(visualSlides[visualIndex]);
      track.style.scrollBehavior = oldBehavior;
      paint();
    };

    const settleClone = () => {
      if (!loopLayout.matches) return;
      if (visualIndex === 0) jump(slides.length);
      if (visualIndex === visualSlides.length - 1) jump(1);
    };

    const moveVisual = (index, behaviour = 'smooth') => {
      clearTimeout(animationTimer);
      visualIndex = Math.max(0, Math.min(index, visualSlides.length - 1));
      active = logicalFromVisual(visualIndex);
      programmatic = true;
      paint();
      track.scrollTo({
        left: leftFor(visualSlides[visualIndex]),
        behavior: reducedMotion.matches ? 'auto' : behaviour,
      });
      animationTimer = window.setTimeout(() => {
        settleClone();
        programmatic = false;
      }, reducedMotion.matches ? 30 : 520);
    };

    const go = (logicalIndex) => {
      const normalized = (logicalIndex + slides.length) % slides.length;

      if (!loopLayout.matches) {
        active = normalized;
        moveVisual(active);
        return;
      }

      if (active === slides.length - 1 && normalized === 0) {
        moveVisual(visualSlides.length - 1);
      } else if (active === 0 && normalized === slides.length - 1) {
        moveVisual(0);
      } else {
        moveVisual(normalized + 1);
      }
    };

    const nearestVisual = () => {
      const center = track.scrollLeft + track.clientWidth / 2;
      let nearest = 0;
      let distance = Infinity;

      visualSlides.forEach((slide, index) => {
        const candidate = Math.abs(slide.offsetLeft + slide.clientWidth / 2 - center);
        if (candidate < distance) {
          nearest = index;
          distance = candidate;
        }
      });

      return nearest;
    };

    const sync = () => {
      if (programmatic || drag) return;
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        visualIndex = nearestVisual();
        active = logicalFromVisual(visualIndex);
        paint();
      });
    };

    const stopAuto = () => {
      clearInterval(autoplayTimer);
      clearTimeout(resumeTimer);
      autoplayTimer = 0;
      resumeTimer = 0;
    };

    const startAuto = () => {
      stopAuto();
      if (loopLayout.matches && !reducedMotion.matches && !document.hidden) {
        autoplayTimer = window.setInterval(() => go(active + 1), 1500);
      }
    };

    const resumeAuto = () => {
      stopAuto();
      resumeTimer = window.setTimeout(startAuto, 900);
    };

    track.addEventListener('pointerdown', (event) => {
      if (event.button !== undefined && event.button !== 0) return;
      stopAuto();
      clearTimeout(animationTimer);
      programmatic = false;
      drag = {
        id: event.pointerId,
        x: event.clientX,
        startScroll: track.scrollLeft,
        moved: false,
        link: event.target.closest?.('a[href]') || null,
      };
      track.setPointerCapture?.(event.pointerId);
    });

    track.addEventListener('pointermove', (event) => {
      if (!drag || drag.id !== event.pointerId) return;
      const delta = event.clientX - drag.x;
      if (Math.abs(delta) > 5) {
        drag.moved = true;
        suppressClick = true;
        track.classList.add('dragging');
        event.preventDefault();
      }
      track.scrollLeft = drag.startScroll - delta;
    });

    const finishDrag = (event) => {
      if (!drag || drag.id !== event.pointerId) return;
      const moved = drag.moved;
      const link = drag.link;
      drag = null;
      track.classList.remove('dragging');
      if (moved) {
        moveVisual(nearestVisual());
        window.setTimeout(() => { suppressClick = false; }, 220);
      } else if (link?.href) {
        window.location.assign(link.href);
      }
      resumeAuto();
    };

    track.addEventListener('pointerup', finishDrag);
    track.addEventListener('pointercancel', finishDrag);
    track.addEventListener('click', (event) => {
      if (suppressClick) {
        event.preventDefault();
        event.stopPropagation();
      }
    }, true);
    track.addEventListener('dragstart', (event) => event.preventDefault());
    track.addEventListener('scroll', sync, { passive: true });

    carousel.querySelector('[data-category-prev]')?.addEventListener('click', () => {
      go(active - 1);
      resumeAuto();
    });
    carousel.querySelector('[data-category-next]')?.addEventListener('click', () => {
      go(active + 1);
      resumeAuto();
    });
    dots.forEach((dot, index) => dot.addEventListener('click', () => {
      go(index);
      resumeAuto();
    }));

    document.addEventListener('visibilitychange', () => document.hidden ? stopAuto() : startAuto());
    addEventListener('resize', () => jump(loopLayout.matches ? active + 1 : active), { passive: true });

    requestAnimationFrame(() => {
      jump(loopLayout.matches ? 1 : 0);
      startAuto();
    });
  });
});
