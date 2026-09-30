function initCart() {
  const config = window.SmileBaby || {};
  const owner = config.userId ? `user-${config.userId}` : 'guest';
  const storageKey = `smilebaby_cart_v1:${owner}`;
  const drawer = document.querySelector('[data-cart-drawer]');
  const backdrop = document.querySelector('[data-cart-backdrop]');
  const content = document.querySelector('[data-cart-drawer-content]');

  const customizationValues = customization => {
    const values = customization?.values;
    if (!values || typeof values !== 'object') return {};
    if (Array.isArray(values)) return Object.fromEntries(values.filter(value => value?.field_id).map(value => [String(value.field_id), String(value.value ?? '')]));
    return Object.fromEntries(Object.entries(values).map(([key, value]) => [String(key), String(value ?? '')]));
  };
  const customizationSignature = customization => {
    if (!customization?.enabled) return '';
    return JSON.stringify(Object.entries(customizationValues(customization)).sort(([a], [b]) => a.localeCompare(b, 'ro', { numeric: true })));
  };
  const normalizeAddons = addons => {
    const result = new Map();
    const entries = Array.isArray(addons) ? addons : Object.values(addons || {});
    entries.slice(0, 100).forEach(addon => {
      const productId = Number.parseInt(addon?.product_id, 10);
      const quantity = Math.min(99, Math.max(1, Number.parseInt(addon?.quantity, 10) || 1));
      if (productId > 0) result.set(productId, { product_id: productId, quantity });
    });
    return [...result.values()].sort((a, b) => a.product_id - b.product_id);
  };
  const addonSignature = addons => JSON.stringify(normalizeAddons(addons));

  const normalize = items => {
    const normalized = new Map();
    (Array.isArray(items) ? items : []).slice(0, 100).forEach(item => {
      const productId = Number.parseInt(item?.product_id, 10);
      const variantId = Number.parseInt(item?.variant_id, 10) || null;
      const quantity = Math.min(99, Math.max(1, Number.parseInt(item?.quantity, 10) || 1));
      const customization = item?.customization?.enabled ? { enabled: true, values: customizationValues(item.customization) } : null;
      const addons = normalizeAddons(item?.addons);
      const identity = `${productId}:${variantId || 0}:${customizationSignature(customization)}:${addonSignature(addons)}`;
      if (productId > 0) {
        const current = normalized.get(identity);
        if (current) current.quantity = Math.min(99, current.quantity + quantity);
        else normalized.set(identity, { product_id: productId, variant_id: variantId, quantity, customization, addons, cart_key: item?.cart_key || null });
      }
    });
    return [...normalized.values()];
  };

  const readLocal = () => {
    try {
      const raw = localStorage.getItem(storageKey);
      return raw === null ? null : normalize(JSON.parse(raw));
    } catch { return null; }
  };

  let items = readLocal();
  if (items === null) items = normalize(config.cart || []);

  const count = () => items.reduce((total, item) => total + item.quantity + normalizeAddons(item.addons).reduce((sum, addon) => sum + addon.quantity, 0), 0);
  const paintCount = () => document.querySelectorAll('[data-cart-count]').forEach(badge => {
    badge.textContent = String(count());
    badge.closest('[data-cart-open]')?.setAttribute('aria-label', `Coș, ${count()} produse`);
  });
  const saveLocal = () => {
    try { localStorage.setItem(storageKey, JSON.stringify(items)); } catch {}
    paintCount();
  };

  async function syncCart() {
    const response = await fetch('/cos/sincronizeaza', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ _token: config.csrf, items })
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Coșul nu a putut fi sincronizat.');
    items = normalize(data.items);
    saveLocal();
    return data;
  }

  if (location.pathname.startsWith('/comanda-confirmata/')) {
    items = [];
    saveLocal();
  }
  saveLocal();
  let ready = syncCart().catch(() => null);

  async function openDrawer(event) {
    if (event) event.preventDefault();
    if (!drawer || !backdrop || !content) {
      location.href = '/cos';
      return;
    }
    backdrop.hidden = false;
    requestAnimationFrame(() => {
      backdrop.classList.add('open');
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    });
    await ready;
    await syncCart().catch(() => null);
    try {
      const response = await fetch('/cos/mini', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      content.innerHTML = await response.text();
    } catch {
      content.innerHTML = '<div class="drawer-empty"><p>Nu am putut încărca acum coșul.</p><a class="button" href="/cos">VEZI COȘUL</a></div>';
    }
  }

  function closeDrawer() {
    if (!drawer || !backdrop) return;
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    setTimeout(() => { backdrop.hidden = true; }, 380);
  }

  document.addEventListener('click', event => {
    if (event.target.closest('[data-cart-open]')) {
      void openDrawer(event);
      return;
    }
    if (event.target.closest('[data-cart-close]')) closeDrawer();
  });
  backdrop?.addEventListener('click', closeDrawer);
  addEventListener('keydown', event => { if (event.key === 'Escape' && drawer?.classList.contains('open')) closeDrawer(); });

  document.querySelectorAll('[data-cart-form]').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    await ready;
    const button = form.querySelector('[type=submit]');
    const label = button?.textContent || '';
    const data = new FormData(form);
    const productId = Number.parseInt(data.get('product_id'), 10);
    const variantId = Number.parseInt(data.get('variant_id'), 10) || null;
    const quantity = Math.min(99, Math.max(1, Number.parseInt(data.get('quantity'), 10) || 1));
    const customization = data.get('personalization_enabled') ? { enabled: true, values: Object.fromEntries([...data.entries()].filter(([key]) => key.startsWith('customization[')).map(([key, value]) => [key.slice(14, -1), String(value)])) } : null;
    const addons = [...data.entries()].filter(([key]) => /^addons\[\d+\]\[selected\]$/.test(key)).map(([key]) => {
      const productId = Number.parseInt(key.match(/^addons\[(\d+)\]/)?.[1], 10);
      return { product_id: productId, quantity: Number.parseInt(data.get(`addons[${productId}][quantity]`), 10) || 1 };
    });
    const signature = customizationSignature(customization);
    const addonsSignature = addonSignature(addons);
    const previousItems = JSON.stringify(items);
    const existing = items.find(item => item.product_id === productId && (item.variant_id || null) === variantId && customizationSignature(item.customization) === signature && addonSignature(item.addons) === addonsSignature);
    if (existing) existing.quantity = Math.min(99, existing.quantity + quantity);
    else items.push({ product_id: productId, variant_id: variantId, quantity, customization, addons, cart_key: null });
    saveLocal();
    if (button) { button.disabled = true; button.textContent = 'SE ADAUGĂ…'; }
    try {
      await syncCart();
      if (button) button.textContent = 'ADĂUGAT ✓';
      setTimeout(() => {
        if (button) { button.textContent = label; button.disabled = false; }
        openDrawer();
      }, 450);
    } catch (error) {
      items = normalize(JSON.parse(previousItems));
      saveLocal();
      if (button) { button.textContent = 'VERIFICĂ DETALIILE'; button.disabled = false; }
      const message = String(error?.message || 'Nu am putut adăuga produsul. Verifică detaliile personalizării.');
      window.alert(message);
      setTimeout(() => { if (button) button.textContent = label; }, 1600);
    }
  }));

  document.querySelectorAll('[data-cart-quantity]').forEach(button => button.addEventListener('click', () => {
    const form = button.closest('form');
    const input = form?.querySelector('[name="quantity"]');
    if (!form || !input) return;
    const delta = button.dataset.cartQuantity === 'plus' ? 1 : -1;
    input.value = String(Math.min(99, Math.max(1, (Number.parseInt(input.value, 10) || 1) + delta)));
    form.requestSubmit();
  }));
  document.querySelectorAll('.cart-quantity-form [name="quantity"]').forEach(input => input.addEventListener('change', () => input.form?.requestSubmit()));

  document.querySelectorAll('.cart-page-premium form[action="/cos/actualizeaza"], .cart-page-premium form[action="/cos/elimina"], .cart-page form[action="/cos/actualizeaza"], .cart-page form[action="/cos/elimina"]').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    const data = new FormData(form);
    const key = String(data.get('key') || '');
    const quantity = form.action.endsWith('/elimina') ? 0 : Math.max(0, Number.parseInt(data.get('quantity'), 10) || 0);
    const line = items.find(item => item.cart_key === key);
    items = items.filter(item => item.cart_key !== key);
    if (quantity > 0 && line) items.push({ ...line, quantity: Math.min(99, quantity) });
    saveLocal();
    await syncCart().catch(() => null);
    location.reload();
  }));

  document.querySelectorAll('form[action="/cos/personalizare"]').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    await ready;
    const data = new FormData(form);
    const key = String(data.get('key') || '');
    const line = items.find(item => item.cart_key === key);
    if (!line) { form.submit(); return; }
    const enabled = Boolean(data.get('personalization_enabled'));
    const values = Object.fromEntries([...data.entries()].filter(([name]) => name.startsWith('customization[')).map(([name, value]) => [name.slice(14, -1), String(value)]));
    line.customization = enabled ? { enabled: true, values } : null;
    line.cart_key = null;
    saveLocal();
    await syncCart().catch(() => null);
    location.reload();
  }));

  addEventListener('storage', event => {
    if (event.key !== storageKey) return;
    items = readLocal() || [];
    paintCount();
    ready = syncCart().catch(() => null);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCart, { once: true });
} else {
  initCart();
}
