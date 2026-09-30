(() => {
  const money = value => new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(Number(value) || 0);
  const normalize = value => (value || '').toLocaleLowerCase('ro').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
  const stems = { trusouri: 'trusou', trusourile: 'trusou', lumanari: 'lumanar', lumanare: 'lumanar', marturii: 'martur', marturie: 'martur', botezuri: 'botez', fetite: 'fetit', fetita: 'fetit', baieti: 'baiet', baiat: 'baiet', cutii: 'cuti', cutie: 'cuti', seturi: 'set' };
  const stem = word => stems[word] || word;
  const distance = (a, b) => {
    const row = [...Array(b.length + 1).keys()];
    for (let i = 1; i <= a.length; i += 1) {
      let previous = row[0]; row[0] = i;
      for (let j = 1; j <= b.length; j += 1) {
        const saved = row[j];
        row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1));
        previous = saved;
      }
    }
    return row[b.length];
  };
  const similar = (query, word) => {
    if (query === word || stem(query) === stem(word)) return true;
    if (Math.min(query.length, word.length) >= 3 && (word.startsWith(query) || query.startsWith(word))) return true;
    if (Math.min(query.length, word.length) >= 4 && (word.includes(query) || query.includes(word))) return true;
    const size = Math.max(query.length, word.length), allowed = size <= 4 ? 1 : (size <= 8 ? 2 : 3);
    return size >= 3 && distance(query, word) <= allowed;
  };
  const matches = (query, value) => {
    const tokens = normalize(query).split(' ').filter(Boolean), words = normalize(value).split(' ').filter(Boolean);
    return tokens.every(token => words.some(word => similar(token, word)));
  };

  document.querySelectorAll('[data-storefront-addons]').forEach(root => {
    const picker = root.querySelector('[data-storefront-addon-picker]');
    const trigger = root.querySelector('[data-storefront-addon-open]');
    const dropdown = root.querySelector('[data-storefront-addon-dropdown]');
    const search = root.querySelector('[data-storefront-addon-search]');
    const options = [...root.querySelectorAll('[data-storefront-addon-option]')];
    const empty = root.querySelector('[data-storefront-addon-empty]');
    const selection = root.querySelector('[data-storefront-addon-selection]');
    const chips = root.querySelector('[data-storefront-addon-chips]');
    const total = root.querySelector('[data-storefront-addon-total]');
    const summary = root.querySelector('[data-storefront-addon-summary]');
    const counts = [...root.querySelectorAll('[data-storefront-addon-count]')];
    const imageDialog = root.querySelector('[data-storefront-addon-image-dialog]');
    const imageLarge = root.querySelector('[data-storefront-addon-image-large]');
    const imageTitle = root.querySelector('[data-storefront-addon-image-title]');

    const checkbox = option => option.querySelector('[data-storefront-addon-check]');
    const quantity = option => option.querySelector('[data-storefront-addon-quantity]');
    const selected = () => options.filter(option => checkbox(option).checked);
    const setOpen = open => {
      dropdown.hidden = !open;
      trigger.setAttribute('aria-expanded', String(open));
      picker.classList.toggle('open', open);
      if (open) requestAnimationFrame(() => search.focus());
    };
    const sync = () => {
      const chosen = selected();
      let sum = 0;
      options.forEach(option => {
        const active = checkbox(option).checked;
        option.classList.toggle('is-selected', active);
        quantity(option).disabled = !active;
        if (active) sum += (Number(option.dataset.addonPrice) || 0) * Math.max(1, Number(quantity(option).value) || 1);
      });
      counts.forEach(node => { node.textContent = String(chosen.length); });
      selection.hidden = chosen.length === 0;
      total.textContent = money(sum);
      summary.textContent = chosen.length ? `${chosen.slice(0, 2).map(option => option.dataset.addonName).join(', ')}${chosen.length > 2 ? ` +${chosen.length - 2}` : ''}` : 'Alege produsele suplimentare';
      chips.replaceChildren(...chosen.map(option => {
        const chip = document.createElement('button'); chip.type = 'button'; chip.dataset.removeAddon = option.dataset.addonId;
        chip.innerHTML = `<span>${option.dataset.addonName}</span><b>${quantity(option).value} × ${money(option.dataset.addonPrice)}</b><i aria-hidden="true">×</i>`;
        chip.setAttribute('aria-label', `Elimină ${option.dataset.addonName}`);
        return chip;
      }));
    };

    trigger.addEventListener('click', () => setOpen(dropdown.hidden));
    root.querySelector('[data-storefront-addon-done]')?.addEventListener('click', () => setOpen(false));
    search.addEventListener('input', () => {
      let visible = 0; const query = search.value.trim();
      options.forEach(option => { const show = !query || matches(query, option.dataset.addonSearchTerms); option.hidden = !show; if (show) visible += 1; });
      empty.hidden = visible > 0;
    });
    search.addEventListener('keydown', event => { if (event.key === 'Escape') { event.stopPropagation(); setOpen(false); trigger.focus(); } });
    options.forEach(option => {
      checkbox(option).addEventListener('change', sync);
      quantity(option).addEventListener('input', () => { quantity(option).value = String(Math.min(99, Math.max(1, Number(quantity(option).value) || 1))); sync(); });
      option.querySelector('[data-storefront-addon-minus]').addEventListener('click', () => { if (!checkbox(option).checked) checkbox(option).checked = true; quantity(option).value = String(Math.max(1, Number(quantity(option).value) - 1)); sync(); });
      option.querySelector('[data-storefront-addon-plus]').addEventListener('click', () => { if (!checkbox(option).checked) checkbox(option).checked = true; quantity(option).value = String(Math.min(99, Number(quantity(option).value) + 1)); sync(); });
      option.addEventListener('click', event => { if (event.target.closest('button,input,label')) return; checkbox(option).checked = !checkbox(option).checked; sync(); });
    });
    chips.addEventListener('click', event => { const button = event.target.closest('[data-remove-addon]'); if (!button) return; const option = options.find(item => item.dataset.addonId === button.dataset.removeAddon); if (option) { checkbox(option).checked = false; sync(); } });
    root.querySelectorAll('[data-storefront-addon-image]').forEach(button => button.addEventListener('click', () => { imageLarge.src = button.dataset.image; imageLarge.alt = button.dataset.alt || ''; imageTitle.textContent = button.dataset.alt || ''; imageDialog.showModal(); }));
    root.querySelector('[data-storefront-addon-image-close]')?.addEventListener('click', () => imageDialog.close());
    imageDialog?.addEventListener('click', event => { if (event.target === imageDialog) imageDialog.close(); });
    document.addEventListener('click', event => { if (!picker.contains(event.target)) setOpen(false); });
    sync();
    root.dataset.addonsReady = '1';
  });
})();
