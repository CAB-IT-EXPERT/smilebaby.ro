document.addEventListener('DOMContentLoaded', () => {
  const config = window.SmileBaby || {};
  const owner = config.userId ? `user-${config.userId}` : 'guest';
  const storageKey = `smilebaby_wishlist_v1:${owner}`;

  const normalize = value => [...new Set((Array.isArray(value) ? value : []).map(Number).filter(id => Number.isInteger(id) && id > 0))].slice(0, 200);
  const readLocal = () => {
    try {
      const raw = localStorage.getItem(storageKey);
      return raw === null ? null : normalize(JSON.parse(raw));
    } catch { return null; }
  };

  let items = readLocal();
  if (items === null) items = normalize(config.wishlist || []);

  const productIdFor = form => Number.parseInt(form.querySelector('[name="product_id"]')?.value, 10) || 0;
  const paint = () => {
    document.querySelectorAll('[data-wishlist-form]').forEach(form => {
      const productId = productIdFor(form);
      const active = items.includes(productId);
      form.classList.toggle('active', active);
      const button = form.querySelector('button');
      if (button) button.setAttribute('aria-label', active ? 'Elimină de la favorite' : 'Adaugă la favorite');
    });
    document.querySelectorAll('[data-wishlist-count]').forEach(badge => {
      badge.textContent = String(items.length);
      badge.hidden = items.length === 0;
      const link = badge.closest('.favorite-link');
      link?.classList.toggle('has-items', items.length > 0);
      link?.setAttribute('aria-label', `Favorite, ${items.length} produse`);
    });
  };
  const saveLocal = () => {
    try { localStorage.setItem(storageKey, JSON.stringify(items)); } catch {}
    paint();
  };

  async function syncWishlist() {
    const response = await fetch('/favorite/sincronizeaza', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ _token: config.csrf, items })
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Favoritele nu au putut fi sincronizate.');
    items = normalize(data.items);
    saveLocal();
    return data;
  }

  saveLocal();
  let ready = syncWishlist().catch(() => null);

  if (location.pathname === '/favorite' && items.length > 0 && !document.querySelector('[data-wishlist-form]')) {
    ready.then(() => {
      const reloadKey = `${storageKey}:restored`;
      try {
        if (!sessionStorage.getItem(reloadKey)) {
          sessionStorage.setItem(reloadKey, '1');
          location.reload();
        }
      } catch { location.reload(); }
    });
  } else {
    try { sessionStorage.removeItem(`${storageKey}:restored`); } catch {}
  }

  document.querySelectorAll('[data-wishlist-form]').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    await ready;
    const productId = productIdFor(form);
    if (!productId) return;
    if (items.includes(productId)) items = items.filter(id => id !== productId);
    else items.push(productId);
    saveLocal();
    const button = form.querySelector('button');
    if (button) button.disabled = true;
    try {
      await syncWishlist();
    } finally {
      if (button) button.disabled = false;
      if (location.pathname === '/favorite') location.reload();
    }
  }));

  addEventListener('storage', event => {
    if (event.key !== storageKey) return;
    items = readLocal() || [];
    paint();
    ready = syncWishlist().catch(() => null);
  });
});
