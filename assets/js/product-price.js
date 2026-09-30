(() => {
  const money = value => `${new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value) || 0)} lei`;

  document.querySelectorAll('[data-product-price-configurator]').forEach(root => {
    const currentNode = root.querySelector('[data-product-price-current]');
    const regularNode = root.querySelector('[data-product-price-regular]');
    const priceBox = currentNode?.closest('.product-page-price');
    const variantSelect = root.querySelector('[data-product-variant]');
    let animationTimer = 0;

    if (!currentNode || !regularNode) return;

    const update = () => {
      let current = Number(root.dataset.basePrice) || 0;
      let regular = Number(root.dataset.baseRegularPrice) || current;
      const selectedVariant = variantSelect?.selectedOptions?.[0];

      if (selectedVariant?.value) {
        current = Number(selectedVariant.dataset.price) || current;
        regular = Number(selectedVariant.dataset.regularPrice) || current;
      }

      if (root.querySelector('[data-personalization-toggle]')?.checked) {
        const fee = Number(root.dataset.customizationPrice) || 0;
        current += fee;
        regular += fee;
      }

      root.querySelectorAll('[data-storefront-addon-option]').forEach(option => {
        if (!option.querySelector('[data-storefront-addon-check]')?.checked) return;
        const quantity = Math.max(1, Number(option.querySelector('[data-storefront-addon-quantity]')?.value) || 1);
        current += (Number(option.dataset.addonPrice) || 0) * quantity;
        regular += (Number(option.dataset.addonCatalogPrice) || Number(option.dataset.addonPrice) || 0) * quantity;
      });

      current = Math.round(current * 100) / 100;
      regular = Math.round(regular * 100) / 100;
      currentNode.textContent = money(current);
      const hasDiscount = regular > current + 0.005;
      regularNode.hidden = !hasDiscount;
      if (hasDiscount) regularNode.textContent = money(regular);

      if (priceBox) {
        priceBox.classList.remove('is-updating');
        requestAnimationFrame(() => priceBox.classList.add('is-updating'));
        clearTimeout(animationTimer);
        animationTimer = setTimeout(() => priceBox.classList.remove('is-updating'), 360);
      }
    };

    root.addEventListener('change', update);
    root.addEventListener('input', event => {
      if (event.target.matches('[data-storefront-addon-quantity]')) update();
    });
    root.addEventListener('smilebaby:product-price-change', update);
    update();
  });
})();
