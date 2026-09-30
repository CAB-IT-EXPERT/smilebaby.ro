(() => {
  const rows = document.querySelectorAll('[data-order-row]');
  if (!rows.length) return;

  const openOrder = (row) => {
    const url = row.dataset.orderRow;
    if (url) window.location.assign(url);
  };

  rows.forEach((row) => {
    row.addEventListener('click', (event) => {
      if (event.target.closest('a, button, input, select, textarea')) return;
      openOrder(row);
    });
    row.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      if (event.target.closest('a, button, input, select, textarea')) return;
      event.preventDefault();
      openOrder(row);
    });
  });
})();
