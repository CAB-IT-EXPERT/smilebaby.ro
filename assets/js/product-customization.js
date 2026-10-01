function initProductCustomization() {
  document.querySelectorAll('[data-storefront-customization]').forEach(root => {
    const toggle = root.querySelector('[data-personalization-toggle]');
    const fields = root.querySelector('[data-personalization-fields]');
    const options = [...root.querySelectorAll('[data-customization-option]')];
    const sync = () => {
      if (options.length && toggle) toggle.checked = options.some(option => option.checked);
      const enabled = Boolean(toggle?.checked);
      root.classList.toggle('is-active', enabled);
      if (fields) fields.hidden = !enabled;
      fields?.querySelectorAll('[data-personalization-input]').forEach(input => {
        input.disabled = !enabled;
        input.required = enabled && input.dataset.personalizationRequired === '1';
      });
      root.dispatchEvent(new CustomEvent('smilebaby:product-price-change', { bubbles: true }));
    };
    toggle?.addEventListener('change', sync);
    options.forEach(option => option.addEventListener('change', sync));
    sync();
    // Chromium may restore a checkbox after DOMContentLoaded. Re-sync after the
    // restoration phase as well, otherwise the checkbox can look selected while
    // its required fields are still hidden and disabled.
    requestAnimationFrame(sync);
    setTimeout(sync, 0);
    addEventListener('pageshow', sync);
  });

  document.querySelectorAll('[data-cart-customization-dialog]').forEach(dialog => {
    const toggle = dialog.querySelector('[data-cart-personalization-toggle]');
    const fields = dialog.querySelector('[data-cart-personalization-fields]');
    const options = [...dialog.querySelectorAll('[data-cart-customization-option]')];
    const sync = () => {
      if (options.length && toggle) toggle.checked = options.some(option => option.checked);
      const enabled = Boolean(toggle?.checked);
      dialog.classList.toggle('is-active', enabled);
      if (fields && options.length) fields.hidden = !enabled;
      fields?.querySelectorAll('[data-personalization-input]').forEach(input => {
        input.disabled = !enabled;
        input.required = enabled && input.dataset.personalizationRequired === '1';
      });
    };
    toggle?.addEventListener('change', sync);
    options.forEach(option => option.addEventListener('change', sync));
    dialog.querySelectorAll('[data-cart-customization-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    sync();
  });

  document.addEventListener('click', event => {
    const opener = event.target.closest('[data-cart-customization-open]');
    if (!opener) return;
    const dialog = document.getElementById(opener.getAttribute('aria-controls'));
    if (dialog instanceof HTMLDialogElement) dialog.showModal();
  });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initProductCustomization, { once: true });
else initProductCustomization();
