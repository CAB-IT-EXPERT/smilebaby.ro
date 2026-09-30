(() => {
  const accountPath = path => path === '/cont' || path.startsWith('/cont/');
  let navigating = false;

  async function navigateAccount(url, push = true) {
    if (navigating) return;
    const destination = new URL(url, location.href);
    if (destination.origin !== location.origin || !accountPath(destination.pathname)) return;

    const currentMain = document.querySelector('#continut');
    if (!currentMain) return;

    navigating = true;
    currentMain.classList.add('account-content-loading');

    try {
      const response = await fetch(destination.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error('Pagina contului nu a putut fi încărcată.');
      if (!accountPath(new URL(response.url).pathname)) {
        location.href = response.url;
        return;
      }

      const documentNext = new DOMParser().parseFromString(await response.text(), 'text/html');
      const nextMain = documentNext.querySelector('#continut');
      if (!nextMain) throw new Error('Conținutul paginii lipsește.');

      currentMain.innerHTML = nextMain.innerHTML;
      currentMain.className = nextMain.className;
      document.title = documentNext.title || document.title;
      if (push) history.pushState({ account: true }, '', destination.href);
      document.querySelector('.account-dashboard, .page-hero')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch {
      location.href = destination.href;
    } finally {
      navigating = false;
      currentMain.classList.remove('account-content-loading');
    }
  }

  document.addEventListener('click', event => {
    const link = event.target.closest('[data-account-nav] a, #continut a[href^="/cont"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const destination = new URL(link.href, location.href);
    if (!accountPath(destination.pathname)) return;
    event.preventDefault();
    void navigateAccount(destination.href);
  });

  addEventListener('popstate', () => {
    if (accountPath(location.pathname)) {
      void navigateAccount(location.href, false);
    } else {
      location.reload();
    }
  });
})();
