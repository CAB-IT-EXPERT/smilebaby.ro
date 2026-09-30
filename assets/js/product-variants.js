(() => {
  document.querySelectorAll('[data-product-variant-picker]').forEach(picker => {
    const select = picker.querySelector('[data-product-variant]');
    const trigger = picker.querySelector('[data-product-variant-trigger]');
    const menu = picker.querySelector('[data-product-variant-menu]');
    const options = [...picker.querySelectorAll('[data-product-variant-option]')];
    const name = picker.querySelector('[data-product-variant-name]');
    const price = picker.querySelector('[data-product-variant-price]');
    const error = picker.querySelector('[data-product-variant-error]');
    if (!select || !trigger || !menu || !options.length) return;

    picker.classList.add('is-enhanced');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;

    const enabledOptions = () => options.filter(option => !option.disabled);
    const selectedOption = () => options.find(option => option.dataset.value === select.value);

    const setOpen = (open, focusOption = false) => {
      picker.classList.toggle('is-open', open);
      trigger.setAttribute('aria-expanded', String(open));
      menu.hidden = !open;
      if (open && focusOption) {
        requestAnimationFrame(() => (selectedOption() || enabledOptions()[0])?.focus());
      }
    };

    const render = () => {
      const selected = selectedOption();
      options.forEach(option => {
        const active = option === selected;
        option.classList.toggle('is-selected', active);
        option.setAttribute('aria-selected', String(active));
      });
      name.textContent = selected?.dataset.name || 'Selectează varianta';
      price.textContent = selected?.dataset.priceLabel || 'Alege';
      picker.classList.toggle('has-value', Boolean(selected));
      if (selected) {
        picker.classList.remove('is-invalid');
        if (error) error.hidden = true;
      }
    };

    const choose = option => {
      if (!option || option.disabled) return;
      select.value = option.dataset.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      render();
      setOpen(false);
      trigger.focus();
    };

    const moveFocus = (current, delta) => {
      const available = enabledOptions();
      const index = Math.max(0, available.indexOf(current));
      available[(index + delta + available.length) % available.length]?.focus();
    };

    trigger.addEventListener('click', () => setOpen(menu.hidden, true));
    trigger.addEventListener('keydown', event => {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        setOpen(true, true);
      }
      if (event.key === 'Escape') setOpen(false);
    });
    options.forEach(option => {
      option.addEventListener('click', () => choose(option));
      option.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          moveFocus(option, event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Home' || event.key === 'End') {
          event.preventDefault();
          const available = enabledOptions();
          available[event.key === 'Home' ? 0 : available.length - 1]?.focus();
        } else if (event.key === 'Escape' || event.key === 'Tab') {
          setOpen(false);
          if (event.key === 'Escape') { event.preventDefault(); trigger.focus(); }
        }
      });
    });
    select.addEventListener('change', render);
    select.addEventListener('invalid', event => {
      event.preventDefault();
      picker.classList.add('is-invalid');
      if (error) error.hidden = false;
      setOpen(true);
      trigger.focus();
    });
    document.addEventListener('pointerdown', event => {
      if (!picker.contains(event.target)) setOpen(false);
    });
    render();
  });
})();
