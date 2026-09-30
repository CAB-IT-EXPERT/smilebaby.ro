(() => {
  const directory = document.querySelector('[data-customer-directory]');
  const filterForm = directory?.querySelector('[data-customer-filter-form]');
  const search = filterForm?.querySelector('[data-customer-search]');
  let searchTimer = 0;
  let composing = false;

  const submitFilters = () => {
    if (!filterForm || directory?.classList.contains('is-filtering')) return;
    directory?.classList.add('is-filtering');
    filterForm.requestSubmit();
  };

  filterForm?.querySelectorAll('[data-customer-filter]').forEach((select) => {
    select.addEventListener('change', submitFilters);
  });

  search?.addEventListener('compositionstart', () => { composing = true; });
  search?.addEventListener('compositionend', () => { composing = false; });
  search?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    if (composing) return;
    const value = search.value.trim();
    if (value.length === 1) return;
    searchTimer = window.setTimeout(submitFilters, 480);
  });

  directory?.querySelector('[data-customer-search-clear]')?.addEventListener('click', () => {
    search.value = '';
    submitFilters();
  });

  directory?.querySelector('[data-customer-page-size]')?.addEventListener('change', (event) => {
    event.currentTarget.form?.submit();
  });

  document.querySelectorAll('[data-customer-row]').forEach((row) => {
    const open = () => { if (row.dataset.customerRow) window.location.assign(row.dataset.customerRow); };
    row.addEventListener('click', (event) => {
      if (event.target.closest('a, button, input, select, textarea')) return;
      open();
    });
    row.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      if (event.target.closest('a, button, input, select, textarea')) return;
      event.preventDefault();
      open();
    });
  });
})();
