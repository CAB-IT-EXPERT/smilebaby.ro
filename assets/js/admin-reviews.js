(() => {
  const page = document.querySelector('[data-review-directory]');
  if (!page) return;

  const filterForm = page.querySelector('[data-review-filter-form]');
  const search = filterForm?.querySelector('[data-review-search]');
  let searchTimer = 0;
  let composing = false;
  const submitFilters = () => {
    if (!filterForm || page.classList.contains('is-filtering')) return;
    page.classList.add('is-filtering');
    filterForm.requestSubmit();
  };

  filterForm?.querySelectorAll('[data-review-filter]').forEach((select) => select.addEventListener('change', submitFilters));
  search?.addEventListener('compositionstart', () => { composing = true; });
  search?.addEventListener('compositionend', () => { composing = false; });
  search?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    if (composing || search.value.trim().length === 1) return;
    searchTimer = window.setTimeout(submitFilters, 480);
  });
  page.querySelector('[data-review-search-clear]')?.addEventListener('click', () => { search.value = ''; submitFilters(); });
  page.querySelector('[data-review-page-size]')?.addEventListener('change', (event) => event.currentTarget.form?.submit());

  page.querySelectorAll('[data-review-reply-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = page.querySelector(`[data-review-reply-dialog="${button.dataset.reviewReplyOpen}"]`);
      if (!dialog) return;
      dialog.showModal();
      requestAnimationFrame(() => dialog.querySelector('textarea')?.focus());
    });
  });
  page.querySelectorAll('[data-review-reply-dialog]').forEach((dialog) => {
    dialog.querySelectorAll('[data-review-reply-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    const textarea = dialog.querySelector('textarea');
    const count = dialog.querySelector('[data-review-reply-count]');
    textarea?.addEventListener('input', () => { if (count) count.textContent = String(textarea.value.length); });
  });
})();
