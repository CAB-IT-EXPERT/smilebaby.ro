document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.querySelector('[data-product-editor]');
  if (!dialog) return;
  const form = dialog.querySelector('[data-product-editor-form]');
  const steps = [...dialog.querySelectorAll('[data-product-step]')];
  const progress = [...dialog.querySelectorAll('[data-product-step-target]')];
  const previous = dialog.querySelector('[data-product-step-prev]');
  const next = dialog.querySelector('[data-product-step-next]');
  const submit = dialog.querySelector('[data-product-submit]');
  const stepLabel = dialog.querySelector('[data-product-step-label]');
  let current = 0;

  const fieldsForStep = index => [...steps[index].querySelectorAll('input,select,textarea')].filter(field => !field.disabled && field.type !== 'hidden');
  const validateStep = index => {
    const invalid = fieldsForStep(index).find(field => !field.checkValidity());
    if (!invalid) return true;
    invalid.reportValidity();
    invalid.focus({ preventScroll: true });
    invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return false;
  };
  const showStep = (index, validate = false) => {
    if (validate && index > current && !validateStep(current)) return;
    current = Math.max(0, Math.min(steps.length - 1, index));
    steps.forEach((step, stepIndex) => { const active = stepIndex === current; step.hidden = !active; step.classList.toggle('active', active); });
    progress.forEach((button, buttonIndex) => { button.classList.toggle('active', buttonIndex === current); button.classList.toggle('complete', buttonIndex < current); button.setAttribute('aria-current', buttonIndex === current ? 'step' : 'false'); });
    previous.hidden = current === 0;
    next.hidden = current === steps.length - 1;
    submit.hidden = current !== steps.length - 1;
    stepLabel.textContent = `Pasul ${current + 1} din ${steps.length}`;
    dialog.querySelector('.product-editor-panels').scrollTo({ top: 0, behavior: 'smooth' });
  };
  progress.forEach(button => button.addEventListener('click', () => showStep(Number(button.dataset.productStepTarget), Number(button.dataset.productStepTarget) > current)));
  previous.addEventListener('click', () => showStep(current - 1));
  next.addEventListener('click', () => showStep(current + 1, true));
  dialog.addEventListener('cancel', event => { event.preventDefault(); location.href = '/admin/produse'; });
  if (!dialog.open) dialog.showModal();

  const slugify = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const name = dialog.querySelector('[data-product-name]');
  const slug = dialog.querySelector('[data-product-slug]');
  const sku = dialog.querySelector('[data-product-sku]');
  const productId = Number(form.dataset.productId || 0);
  const availabilityInputs = [...dialog.querySelectorAll('[data-check-availability]')];
  const price = dialog.querySelector('[data-product-price]');
  const status = dialog.querySelector('[data-product-status]');
  const summaryName = dialog.querySelector('[data-product-summary-name]');
  const summaryPrice = dialog.querySelector('[data-product-summary-price]');
  const summaryStatus = dialog.querySelector('[data-product-summary-status]');
  const seoTitle = dialog.querySelector('[data-seo-title]');
  const seoDescription = dialog.querySelector('[data-seo-description]');
  const seoPreviewTitle = dialog.querySelector('[data-seo-preview-title]');
  const seoPreviewDescription = dialog.querySelector('[data-seo-preview-description]');
  const seoPreviewUrl = dialog.querySelector('[data-seo-preview-url]');
  let slugTouched = Boolean(slug.value.trim());
  const sync = () => {
    if (!slugTouched) slug.value = slugify(name.value);
    if (!sku.value.trim()) sku.placeholder = name.value.trim() ? 'Generat automat la salvare' : 'SB-P000001';
    summaryName.textContent = name.value.trim() || 'Produs fără nume';
    summaryPrice.textContent = new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(Number(price.value) || 0);
    const statuses = { active: 'Activ', draft: 'Draft', hidden: 'Ascuns', archived: 'Arhivat' };
    summaryStatus.textContent = statuses[status.value] || status.value;
    seoPreviewTitle.textContent = seoTitle.value.trim() || name.value.trim() || 'Numele produsului';
    seoPreviewDescription.textContent = seoDescription.value.trim() || 'Descrierea produsului va apărea aici și îi va ajuta pe clienți să înțeleagă rapid ce oferi.';
    seoPreviewUrl.textContent = `smilebaby.ro/produs/${slug.value.trim() || 'adresa-produsului'}`;
  };

  const availabilityTimers = new Map();
  const availabilityRequests = new Map();
  const setAvailabilityState = (input, state, message) => {
    const field = input.closest('[data-availability-field]');
    const output = field?.querySelector('[data-availability-message]');
    if (!field || !output) return;
    field.classList.remove('is-checking', 'is-available', 'is-taken', 'is-empty', 'is-unavailable');
    field.classList.add(`is-${state}`);
    field.dataset.availabilityState = state;
    output.textContent = message;
    input.setCustomValidity(state === 'taken' ? message : '');
  };
  const emptyAvailabilityMessage = input => {
    const field = input.dataset.checkAvailability;
    if (field === 'sku') return 'Generare automată activă — codul va fi creat la salvare.';
    if (field === 'gtin') return 'Opțional — completează doar dacă produsul are un cod de bare.';
    if (field === 'slug') return 'Se generează automat din numele produsului.';
    return 'Începe să scrii și verificăm imediat disponibilitatea.';
  };
  const checkAvailability = async input => {
    const fieldName = input.dataset.checkAvailability;
    const value = input.value.trim();
    availabilityRequests.get(input)?.abort();
    if (!value) { setAvailabilityState(input, 'empty', emptyAvailabilityMessage(input)); return; }
    if (value.length < 2) { setAvailabilityState(input, 'empty', 'Mai scrie cel puțin un caracter pentru verificare.'); return; }
    const controller = new AbortController();
    availabilityRequests.set(input, controller);
    setAvailabilityState(input, 'checking', 'Verificăm disponibilitatea…');
    try {
      const params = new URLSearchParams({ field: fieldName, value, id: String(productId) });
      const response = await fetch(`/admin/produse/verifica-disponibilitate?${params}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
      });
      if (!response.ok) throw new Error('availability-check-failed');
      const result = await response.json();
      if (input.value.trim() !== value) return;
      setAvailabilityState(input, result.state === 'taken' ? 'taken' : (result.state === 'empty' ? 'empty' : 'available'), result.message);
    } catch (error) {
      if (error.name === 'AbortError') return;
      setAvailabilityState(input, 'unavailable', 'Nu am putut verifica acum. Încearcă din nou peste câteva secunde.');
    }
  };
  const scheduleAvailability = (input, immediate = false) => {
    clearTimeout(availabilityTimers.get(input));
    if (!input.value.trim()) { checkAvailability(input); return; }
    setAvailabilityState(input, 'checking', 'Verificăm disponibilitatea…');
    const timer = window.setTimeout(() => checkAvailability(input), immediate ? 0 : 420);
    availabilityTimers.set(input, timer);
  };

  name.addEventListener('input', () => { sync(); if (!slugTouched) scheduleAvailability(slug); });
  slug.addEventListener('input', () => { slugTouched = true; sync(); });
  [price, status, seoTitle, seoDescription].forEach(field => field?.addEventListener('input', sync));
  availabilityInputs.forEach(input => input.addEventListener('input', () => scheduleAvailability(input)));
  sync();
  availabilityInputs.forEach(input => scheduleAvailability(input, true));

  dialog.querySelectorAll('[data-product-count]').forEach(field => {
    const output = dialog.querySelector(`[data-product-count-output="${field.dataset.productCount}"]`);
    const update = () => output.textContent = field.maxLength > 0 ? `${field.value.length} / ${field.maxLength}` : `${field.value.length} caractere`;
    field.addEventListener('input', update); update();
  });
  let syncRichEditor = () => {};
  const richEditor = dialog.querySelector('[data-rich-editor]');
  if (richEditor) {
    const canvas = richEditor.querySelector('[data-rich-canvas]');
    const textarea = richEditor.querySelector('[data-rich-textarea]');
    const countOutput = dialog.querySelector('[data-product-count-output="long"]');
    const format = richEditor.querySelector('[data-rich-format]');
    syncRichEditor = () => {
      const text = canvas.innerText.replace(/\u00a0/g, ' ').trim();
      textarea.value = text ? canvas.innerHTML.trim() : '';
      countOutput.textContent = `${text.length} caractere`;
    };
    const applyCommand = (command, value = null) => {
      canvas.focus();
      if (command === 'createLink') {
        const href = window.prompt('Introdu adresa linkului (ex.: https://smilebaby.ro):', 'https://');
        if (!href || href === 'https://') return;
        document.execCommand(command, false, href);
      } else {
        document.execCommand(command, false, value);
      }
      syncRichEditor();
    };
    richEditor.querySelectorAll('[data-rich-command]').forEach(button => {
      button.addEventListener('mousedown', event => event.preventDefault());
      button.addEventListener('click', () => applyCommand(button.dataset.richCommand));
    });
    format.addEventListener('change', () => {
      applyCommand('formatBlock', format.value);
      format.value = 'p';
    });
    canvas.addEventListener('input', syncRichEditor);
    canvas.addEventListener('blur', syncRichEditor);
    canvas.addEventListener('paste', event => {
      event.preventDefault();
      const text = event.clipboardData?.getData('text/plain') || '';
      document.execCommand('insertText', false, text);
      syncRichEditor();
    });
    syncRichEditor();
  }
  const stockToggle = dialog.querySelector('[data-product-manage-stock]');
  const stockFields = dialog.querySelector('[data-product-stock-fields]');
  const syncStock = () => {
    const managed = stockToggle.checked;
    stockFields.classList.toggle('disabled', !managed);
    stockFields.setAttribute('aria-disabled', String(!managed));
    if (!managed) stockFields.querySelector('[name="stock_status"]').value = 'in_stock';
    stockFields.querySelectorAll('input,select').forEach(field => { field.disabled = !managed; });
  };
  stockToggle.addEventListener('change', syncStock); syncStock();

  const categoryRoot = dialog.querySelector('[data-category-multiselect]');
  const categoryInputs = [...categoryRoot.querySelectorAll('[data-category-options] input')];
  const categoryCounts = [...categoryRoot.querySelectorAll('[data-category-selection-count]')];
  const categorySummary = categoryRoot.querySelector('[data-category-summary]');
  const categoryChips = categoryRoot.querySelector('[data-category-chips]');
  const categoryToggle = categoryRoot.querySelector('[data-category-toggle]');
  const categoryDropdown = categoryRoot.querySelector('[data-category-dropdown]');
  const categorySearch = categoryRoot.querySelector('[data-category-search]');
  const categoryEmpty = categoryRoot.querySelector('[data-category-empty]');
  const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const setCategoryOpen = open => {
    categoryDropdown.hidden = !open; categoryToggle.setAttribute('aria-expanded', String(open)); categoryRoot.classList.toggle('open', open);
    if (open) requestAnimationFrame(() => { categorySearch.focus(); categoryToggle.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); });
  };
  const syncCategories = () => {
    const selectedCategories = categoryInputs.filter(input => input.checked);
    categoryCounts.forEach(node => node.textContent = String(selectedCategories.length));
    const names = selectedCategories.map(input => input.closest('label').querySelector('span').textContent.trim());
    categorySummary.textContent = names.length ? `${names.slice(0, 2).join(', ')}${names.length > 2 ? ` +${names.length - 2}` : ''}` : 'Alege categoriile produsului';
    categoryChips.replaceChildren();
    categoryInputs.forEach(input => input.closest('label').setAttribute('aria-selected', String(input.checked)));
    selectedCategories.forEach(input => {
      const chip = document.createElement('button'), chipLabel = document.createElement('span'), remove = document.createElement('b');
      const categoryName = input.closest('label').querySelector('span').textContent.trim();
      chip.type = 'button'; chip.dataset.categoryValue = input.value; chipLabel.textContent = categoryName; remove.textContent = '×'; remove.setAttribute('aria-hidden', 'true');
      chip.append(chipLabel, remove); chip.setAttribute('aria-label', `Elimină categoria ${categoryName}`); categoryChips.append(chip);
    });
  };
  categoryToggle.addEventListener('click', () => setCategoryOpen(categoryDropdown.hidden));
  categoryRoot.querySelector('[data-category-done]').addEventListener('click', () => setCategoryOpen(false));
  categoryInputs.forEach(input => input.addEventListener('change', syncCategories));
  categoryChips.addEventListener('click', event => { const chip = event.target.closest('[data-category-value]'); if (!chip) return; const input = categoryInputs.find(item => item.value === chip.dataset.categoryValue); if (input) { input.checked = false; syncCategories(); } });
  categorySearch.addEventListener('input', () => {
    const query = normalize(categorySearch.value.trim()); let visible = 0;
    categoryInputs.forEach(input => { const label = input.closest('label'), matches = !query || normalize(label.textContent).includes(query); label.hidden = !matches; if (matches) visible += 1; });
    categoryEmpty.hidden = visible > 0;
  });
  categorySearch.addEventListener('keydown', event => { if (event.key === 'Escape') { event.stopPropagation(); setCategoryOpen(false); categoryToggle.focus(); } });
  document.addEventListener('click', event => { if (!categoryRoot.contains(event.target)) setCategoryOpen(false); });
  syncCategories();

  const mediaManager = dialog.querySelector('[data-product-media-manager]');
  const imageInput = mediaManager?.querySelector('[data-product-images]');
  const imageGallery = mediaManager?.querySelector('[data-product-image-gallery]');
  const mediaAdd = mediaManager?.querySelector('[data-product-media-add]');
  const imageOrder = mediaManager?.querySelector('[data-product-image-order]');
  const coverInput = mediaManager?.querySelector('[data-product-cover-image]');
  const imageCount = mediaManager?.querySelector('[data-product-image-count]');
  const removedImages = mediaManager?.querySelector('[data-product-removed-images]');
  const summaryImage = dialog.querySelector('.product-summary-image');
  let selectedFiles = [];
  let draggedCard = null;
  let fileSequence = 0;
  const canRebuildImageInput = typeof DataTransfer === 'function';

  const mediaCards = () => [...imageGallery.querySelectorAll('[data-image-key]')];
  const tokenForCard = card => {
    if (!card) return '';
    if (card.dataset.imageKind === 'existing') return `existing:${card.dataset.imageId}`;
    const item = selectedFiles.find(candidate => candidate.key === card.dataset.imageKey);
    return item ? `new:${item.uploadIndex}` : '';
  };
  const rebuildImageInput = () => {
    if (!imageInput || !canRebuildImageInput) return false;
    const transfer = new DataTransfer();
    selectedFiles.forEach((item, index) => { item.uploadIndex = index; transfer.items.add(item.file); });
    imageInput.files = transfer.files;
    return true;
  };
  const syncMedia = () => {
    const cards = mediaCards();
    if (coverInput && !cards.some(card => card.dataset.imageKey === coverInput.dataset.coverKey)) coverInput.dataset.coverKey = cards[0]?.dataset.imageKey || '';
    cards.forEach((card, index) => {
      const isCover = card.dataset.imageKey === coverInput?.dataset.coverKey;
      card.classList.toggle('is-cover', isCover);
      card.querySelector('[data-media-action="left"]')?.toggleAttribute('disabled', index === 0);
      card.querySelector('[data-media-action="right"]')?.toggleAttribute('disabled', index === cards.length - 1);
    });
    const orderedTokens = cards.map(tokenForCard).filter(Boolean);
    if (imageOrder) imageOrder.value = JSON.stringify(orderedTokens);
    if (coverInput) coverInput.value = tokenForCard(cards.find(card => card.dataset.imageKey === coverInput.dataset.coverKey)) || '';
    if (imageCount) imageCount.textContent = String(cards.length);
    if (mediaAdd) mediaAdd.classList.toggle('is-full', cards.length >= 12);
    const cover = cards.find(card => card.classList.contains('is-cover'))?.querySelector('img') || cards[0]?.querySelector('img');
    if (cover && summaryImage) { const clone = cover.cloneNode(); clone.alt = ''; summaryImage.replaceChildren(clone); }
  };
  const mediaActionButton = (action, label, title = label) => {
    const button = document.createElement('button'); button.type = 'button'; button.dataset.mediaAction = action; button.setAttribute('aria-label', label); button.title = title; return button;
  };
  const createNewImageCard = item => {
    const card = document.createElement('article'), image = document.createElement('img'), drag = document.createElement('button'), remove = mediaActionButton('remove', 'Elimină fotografia'), badge = document.createElement('span'), actions = document.createElement('div');
    card.className = 'product-media-card is-new'; card.draggable = true; card.dataset.imageKey = item.key; card.dataset.imageKind = 'new';
    image.src = item.url; image.alt = item.file.name;
    drag.type = 'button'; drag.className = 'product-media-drag'; drag.setAttribute('aria-label', 'Trage pentru reordonare'); drag.title = 'Trage pentru reordonare';
    for (let index = 0; index < 6; index += 1) drag.append(document.createElement('i'));
    remove.className = 'product-media-remove'; remove.textContent = '×';
    badge.className = 'product-media-cover-label'; badge.textContent = 'Copertă';
    actions.className = 'product-media-actions';
    const left = mediaActionButton('left', 'Mută fotografia la stânga'), cover = mediaActionButton('cover', 'Alege fotografia drept copertă'), right = mediaActionButton('right', 'Mută fotografia la dreapta');
    left.textContent = '←'; cover.textContent = '★'; right.textContent = '→'; actions.append(left, cover, right);
    card.append(image, drag, remove, badge, actions); return card;
  };
  const appendFiles = files => {
    if (!canRebuildImageInput && selectedFiles.length) {
      selectedFiles.forEach(item => URL.revokeObjectURL(item.url));
      imageGallery.querySelectorAll('[data-image-kind="new"]').forEach(card => card.remove());
      removedImages.querySelectorAll('input[name="removed_new_images[]"]').forEach(input => input.remove());
      selectedFiles = [];
    }
    const incoming = [...files].map((file, index) => ({ file, index }));
    const valid = incoming.filter(item => /^image\/(jpeg|png|webp|gif)$/i.test(item.file.type) && item.file.size <= 12 * 1024 * 1024);
    const remaining = Math.max(0, 12 - mediaCards().length);
    const accepted = valid.slice(0, remaining);
    accepted.forEach(({ file, index }) => {
      const item = { key: `file:${Date.now()}:${fileSequence++}`, file, url: URL.createObjectURL(file), uploadIndex: canRebuildImageInput ? selectedFiles.length : index };
      selectedFiles.push(item); imageGallery.insertBefore(createNewImageCard(item), mediaAdd);
      if (!coverInput.dataset.coverKey) coverInput.dataset.coverKey = item.key;
    });
    if (!canRebuildImageInput) {
      const acceptedIndexes = accepted.map(item => item.index);
      incoming.filter(item => !acceptedIndexes.includes(item.index)).forEach(item => { const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'removed_new_images[]'; hidden.value = String(item.index); removedImages.append(hidden); });
    }
    if (valid.length < files.length) window.alert('Unele fișiere nu au fost adăugate. Sunt acceptate imagini JPG, PNG, WebP sau GIF de maximum 12 MB.');
    if (valid.length > remaining) window.alert('Poți păstra cel mult 12 fotografii pentru un produs.');
    rebuildImageInput(); syncMedia();
  };
  if (coverInput) coverInput.dataset.coverKey = coverInput.value;
  imageInput?.addEventListener('change', () => appendFiles(imageInput.files));
  mediaAdd?.addEventListener('dragover', event => { event.preventDefault(); mediaAdd.classList.add('is-dragging-over'); });
  mediaAdd?.addEventListener('dragleave', () => mediaAdd.classList.remove('is-dragging-over'));
  mediaAdd?.addEventListener('drop', event => { event.preventDefault(); mediaAdd.classList.remove('is-dragging-over'); appendFiles(event.dataTransfer.files); });
  imageGallery?.addEventListener('click', event => {
    const button = event.target.closest('[data-media-action]'); if (!button) return;
    const card = button.closest('[data-image-key]'); if (!card) return;
    const action = button.dataset.mediaAction;
    if (action === 'cover') coverInput.dataset.coverKey = card.dataset.imageKey;
    if (action === 'left' && card.previousElementSibling?.matches('[data-image-key]')) imageGallery.insertBefore(card, card.previousElementSibling);
    if (action === 'right' && card.nextElementSibling?.matches('[data-image-key]')) imageGallery.insertBefore(card.nextElementSibling, card);
    if (action === 'remove') {
      if (card.dataset.imageKind === 'existing') {
        const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'removed_images[]'; hidden.value = card.dataset.imageId; removedImages.append(hidden);
      } else {
        const fileIndex = selectedFiles.findIndex(item => item.key === card.dataset.imageKey);
        if (fileIndex >= 0) {
          const removed = selectedFiles[fileIndex]; URL.revokeObjectURL(removed.url); selectedFiles.splice(fileIndex, 1);
          if (!rebuildImageInput()) { const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'removed_new_images[]'; hidden.value = String(removed.uploadIndex); removedImages.append(hidden); }
        }
      }
      card.remove();
    }
    syncMedia();
  });
  imageGallery?.addEventListener('dragstart', event => { draggedCard = event.target.closest('[data-image-key]'); if (!draggedCard) return; draggedCard.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; });
  imageGallery?.addEventListener('dragover', event => {
    if (!draggedCard) return; event.preventDefault();
    const target = event.target.closest('[data-image-key]'); if (!target || target === draggedCard) return;
    const rect = target.getBoundingClientRect(); imageGallery.insertBefore(draggedCard, event.clientX < rect.left + rect.width / 2 ? target : target.nextSibling);
  });
  imageGallery?.addEventListener('dragend', () => { draggedCard?.classList.remove('is-dragging'); draggedCard = null; syncMedia(); });
  syncMedia();

  const variantEditor = dialog.querySelector('[data-variant-editor]');
  const variantTemplate = dialog.querySelector('[data-variant-template]');
  const variantEmpty = dialog.querySelector('[data-product-variant-empty]');
  const syncVariants = () => variantEmpty.hidden = Boolean(variantEditor.querySelector('.variant-row'));
  new MutationObserver(syncVariants).observe(variantEditor, { childList: true }); syncVariants();
  dialog.querySelector('[data-add-variant]')?.addEventListener('click', () => {
    if (!variantTemplate) return;
    variantEditor.append(variantTemplate.content.cloneNode(true));
    const row = variantEditor.lastElementChild;
    row?.querySelector('input')?.focus();
    row?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
  variantEditor.addEventListener('click', event => {
    const remove = event.target.closest('[data-remove-variant]');
    if (remove) remove.closest('.variant-row')?.remove();
  });

  const variantSkuTimers = new WeakMap();
  const checkVariantSku = async input => {
    const field = input.closest('[data-variant-sku-field]'), message = field?.querySelector('[data-variant-sku-message]'), value = input.value.trim();
    if (!field || !message) return;
    field.classList.remove('is-checking', 'is-available', 'is-taken', 'is-unavailable');
    if (!value) { message.textContent = 'Generare automată activă — codul va fi creat la salvare.'; input.setCustomValidity(''); return; }
    field.classList.add('is-checking'); message.textContent = 'Verificăm disponibilitatea…';
    try {
      const params = new URLSearchParams({ field: 'variant_sku', value, id: String(productId), variant_id: field.dataset.variantId || '0' });
      const response = await fetch(`/admin/produse/verifica-disponibilitate?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) throw new Error('variant-check-failed');
      const result = await response.json(); if (input.value.trim() !== value) return;
      const state = result.state === 'taken' ? 'taken' : 'available'; field.classList.remove('is-checking'); field.classList.add(`is-${state}`); message.textContent = result.message; input.setCustomValidity(state === 'taken' ? result.message : '');
    } catch (_) { field.classList.remove('is-checking'); field.classList.add('is-unavailable'); message.textContent = 'Nu am putut verifica acum. Încearcă din nou.'; }
  };
  variantEditor.addEventListener('input', event => {
    const input = event.target.closest('[data-variant-sku]'); if (!input) return;
    clearTimeout(variantSkuTimers.get(input));
    const field = input.closest('[data-variant-sku-field]'), message = field?.querySelector('[data-variant-sku-message]');
    field?.classList.remove('is-available', 'is-taken', 'is-unavailable'); field?.classList.add('is-checking'); if (message) message.textContent = input.value.trim() ? 'Verificăm disponibilitatea…' : 'Generare automată activă — codul va fi creat la salvare.';
    variantSkuTimers.set(input, window.setTimeout(() => checkVariantSku(input), input.value.trim() ? 420 : 0));
  });
  variantEditor.querySelectorAll('[data-variant-sku]').forEach(input => checkVariantSku(input));

  const customizationRoot = dialog.querySelector('[data-customization-settings]');
  const customizationToggle = customizationRoot?.querySelector('[data-customization-toggle]');
  const customizationContent = customizationRoot?.querySelector('[data-customization-content]');
  const customizationFields = customizationRoot?.querySelector('[data-customization-fields]');
  const customizationTemplate = customizationRoot?.querySelector('[data-customization-template]');
  const customizationEmpty = customizationRoot?.querySelector('[data-customization-empty]');
  let draggedCustomizationField = null;
  const customizationRows = () => [...(customizationFields?.querySelectorAll('[data-customization-field]') || [])];
  const syncCustomization = () => {
    if (!customizationRoot) return;
    const enabled = Boolean(customizationToggle?.checked);
    const rows = customizationRows();
    customizationRoot.classList.toggle('is-disabled', !enabled);
    customizationContent?.setAttribute('aria-disabled', String(!enabled));
    if (customizationEmpty) customizationEmpty.hidden = rows.length > 0;
    rows.forEach((row, index) => {
      const label = row.querySelector('[name="customization_field_label[]"]');
      if (label) label.required = enabled;
      row.querySelector('[data-customization-move="up"]')?.toggleAttribute('disabled', index === 0);
      row.querySelector('[data-customization-move="down"]')?.toggleAttribute('disabled', index === rows.length - 1);
    });
    customizationToggle?.setCustomValidity(enabled && rows.length === 0 ? 'Adaugă cel puțin un câmp pentru personalizare.' : '');
  };
  customizationToggle?.addEventListener('change', syncCustomization);
  customizationRoot?.querySelector('[data-add-customization-field]')?.addEventListener('click', () => {
    if (!customizationTemplate || !customizationFields) return;
    customizationFields.append(customizationTemplate.content.cloneNode(true));
    const row = customizationFields.lastElementChild;
    syncCustomization();
    row?.querySelector('[name="customization_field_label[]"]')?.focus();
    row?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
  customizationFields?.addEventListener('click', event => {
    const row = event.target.closest('[data-customization-field]');
    if (!row) return;
    if (event.target.closest('[data-remove-customization-field]')) row.remove();
    const direction = event.target.closest('[data-customization-move]')?.dataset.customizationMove;
    if (direction === 'up' && row.previousElementSibling) customizationFields.insertBefore(row, row.previousElementSibling);
    if (direction === 'down' && row.nextElementSibling) customizationFields.insertBefore(row.nextElementSibling, row);
    syncCustomization();
  });
  customizationFields?.addEventListener('mousedown', event => {
    const handle = event.target.closest('.product-customization-drag');
    if (handle) handle.closest('[data-customization-field]')?.setAttribute('draggable', 'true');
  });
  customizationFields?.addEventListener('dragstart', event => {
    draggedCustomizationField = event.target.closest('[data-customization-field]');
    draggedCustomizationField?.classList.add('is-dragging');
  });
  customizationFields?.addEventListener('dragover', event => {
    if (!draggedCustomizationField) return;
    event.preventDefault();
    const target = event.target.closest('[data-customization-field]');
    if (!target || target === draggedCustomizationField) return;
    const rect = target.getBoundingClientRect();
    customizationFields.insertBefore(draggedCustomizationField, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
  });
  customizationFields?.addEventListener('dragend', () => {
    draggedCustomizationField?.classList.remove('is-dragging');
    draggedCustomizationField?.removeAttribute('draggable');
    draggedCustomizationField = null;
    syncCustomization();
  });
  syncCustomization();

  const addonRoot = dialog.querySelector('[data-addon-settings]');
  if (addonRoot) {
    const addonToggle = addonRoot.querySelector('[data-addon-toggle]');
    const addonContent = addonRoot.querySelector('[data-addon-content]');
    const addonPicker = addonRoot.querySelector('[data-addon-picker]');
    const addonDropdown = addonRoot.querySelector('[data-addon-dropdown]');
    const addonTrigger = addonRoot.querySelector('[data-addon-open]');
    const addonSearch = addonRoot.querySelector('[data-addon-search]');
    const addonOptions = [...addonRoot.querySelectorAll('[data-addon-option]')];
    const addonSelectedList = addonRoot.querySelector('[data-addon-selected-list]');
    const addonEmpty = addonRoot.querySelector('[data-addon-empty]');
    const addonSummary = addonRoot.querySelector('[data-addon-summary]');
    const addonCounts = [...addonRoot.querySelectorAll('[data-addon-count]')];
    const addonCategories = [...addonRoot.querySelectorAll('[data-addon-category]')];
    const addonSearchEmpty = addonRoot.querySelector('[data-addon-search-empty]');
    const addonImageDialog = addonRoot.querySelector('[data-addon-image-dialog]');
    const addonImageLarge = addonRoot.querySelector('[data-addon-image-large]');
    const addonImageTitle = addonRoot.querySelector('[data-addon-image-title]');

    const addonNormalize = value => (value || '').toLocaleLowerCase('ro').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
    const addonStems = { trusouri: 'trusou', trusourile: 'trusou', lumanari: 'lumanar', lumanare: 'lumanar', marturii: 'martur', marturie: 'martur', botezuri: 'botez', fetite: 'fetit', fetita: 'fetit', baieti: 'baiet', baiat: 'baiet', cutii: 'cuti', cutie: 'cuti', seturi: 'set' };
    const addonStem = word => addonStems[word] || word;
    const addonDistance = (a, b) => { const row = [...Array(b.length + 1).keys()]; for (let i = 1; i <= a.length; i += 1) { let previous = row[0]; row[0] = i; for (let j = 1; j <= b.length; j += 1) { const saved = row[j]; row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1)); previous = saved; } } return row[b.length]; };
    const addonSimilar = (query, word) => { if (query === word || addonStem(query) === addonStem(word)) return true; if (Math.min(query.length, word.length) >= 3 && (word.startsWith(query) || query.startsWith(word))) return true; if (Math.min(query.length, word.length) >= 4 && (word.includes(query) || query.includes(word))) return true; const size = Math.max(query.length, word.length), allowed = size <= 4 ? 1 : (size <= 8 ? 2 : 3); return size >= 3 && addonDistance(query, word) <= allowed; };
    const addonMatches = (query, value) => { const tokens = addonNormalize(query).split(' ').filter(Boolean), words = addonNormalize(value).split(' ').filter(Boolean); return tokens.every(token => words.some(word => addonSimilar(token, word))); };
    const addonInput = option => option.querySelector('input[name="addon_product_ids[]"]');
    const selectedAddonOptions = () => addonOptions.filter(option => addonInput(option)?.checked);

    const makeAddonSelectedRow = option => {
      const id = option.dataset.addonId;
      const row = document.createElement('article'); row.dataset.addonSelected = id;
      const imageButton = document.createElement('button'); imageButton.type = 'button'; imageButton.dataset.addonSelectedImage = ''; imageButton.dataset.image = option.dataset.addonImage; imageButton.dataset.alt = option.dataset.addonName;
      const image = document.createElement('img'); image.src = option.dataset.addonImage; image.alt = ''; imageButton.append(image);
      const copy = document.createElement('div'), title = document.createElement('strong'), meta = document.createElement('small'), catalogPrice = document.createElement('em');
      title.textContent = option.dataset.addonName; meta.textContent = `${option.dataset.addonCategories || 'Fără categorie'} · ${option.dataset.addonSku || 'Fără SKU'}`; catalogPrice.textContent = `Preț catalog: ${new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(Number(option.dataset.addonPrice) || 0)}`; copy.append(title, meta, catalogPrice);
      const priceLabel = document.createElement('label'); priceLabel.append(document.createTextNode('Preț în set (lei)'));
      const priceInput = document.createElement('input'); priceInput.type = 'number'; priceInput.name = `addon_prices[${id}]`; priceInput.min = '0'; priceInput.step = '0.01'; priceInput.value = Number(option.dataset.addonPrice || 0).toFixed(2); priceInput.required = true; priceLabel.append(priceInput);
      const remove = document.createElement('button'); remove.type = 'button'; remove.dataset.addonRemove = ''; remove.setAttribute('aria-label', 'Elimină produsul'); remove.textContent = '×';
      row.append(imageButton, copy, priceLabel, remove); return row;
    };

    const syncAddonCategories = () => addonCategories.forEach(category => {
      const matching = addonOptions.filter(option => (option.dataset.addonCategoryIds || '').split(',').includes(category.value));
      const selected = matching.filter(option => addonInput(option)?.checked).length;
      category.checked = matching.length > 0 && selected === matching.length;
      category.indeterminate = selected > 0 && selected < matching.length;
    });
    const syncAddons = () => {
      const enabled = Boolean(addonToggle?.checked), selected = selectedAddonOptions(), selectedIds = new Set(selected.map(option => option.dataset.addonId));
      addonRoot.classList.toggle('is-disabled', !enabled); addonContent?.setAttribute('aria-disabled', String(!enabled));
      addonOptions.forEach(option => option.classList.toggle('is-selected', Boolean(addonInput(option)?.checked)));
      addonSelectedList.querySelectorAll('[data-addon-selected]').forEach(row => { if (!selectedIds.has(row.dataset.addonSelected)) row.remove(); });
      selected.forEach(option => { let row = addonSelectedList.querySelector(`[data-addon-selected="${option.dataset.addonId}"]`); if (!row) row = makeAddonSelectedRow(option); addonSelectedList.append(row); });
      addonCounts.forEach(node => { node.textContent = String(selected.length); });
      addonEmpty.hidden = selected.length > 0;
      addonSummary.textContent = selected.length ? `${selected.slice(0, 2).map(option => option.dataset.addonName).join(', ')}${selected.length > 2 ? ` +${selected.length - 2}` : ''}` : 'Alege produse sau o categorie întreagă';
      addonToggle?.setCustomValidity(enabled && !selected.length ? 'Selectează cel puțin un produs suplimentar.' : '');
      syncAddonCategories();
    };
    const setAddonDropdown = open => { addonDropdown.hidden = !open; addonTrigger.setAttribute('aria-expanded', String(open)); addonPicker.classList.toggle('open', open); if (open) requestAnimationFrame(() => addonSearch.focus()); };
    const showAddonImage = (src, title) => { if (!addonImageDialog || !src) return; addonImageLarge.src = src; addonImageLarge.alt = title || ''; addonImageTitle.textContent = title || ''; addonImageDialog.showModal(); };

    addonToggle?.addEventListener('change', syncAddons);
    addonTrigger?.addEventListener('click', () => setAddonDropdown(addonDropdown.hidden));
    addonRoot.querySelector('[data-addon-done]')?.addEventListener('click', () => setAddonDropdown(false));
    addonSearch?.addEventListener('input', () => { const query = addonSearch.value.trim(); let visible = 0; addonOptions.forEach(option => { const match = !query || addonMatches(query, option.dataset.addonSearchTerms); option.hidden = !match; if (match) visible += 1; }); addonSearchEmpty.hidden = visible > 0; });
    addonSearch?.addEventListener('keydown', event => { if (event.key === 'Escape') { event.stopPropagation(); setAddonDropdown(false); addonTrigger.focus(); } });
    addonOptions.forEach(option => addonInput(option)?.addEventListener('change', syncAddons));
    addonCategories.forEach(category => category.addEventListener('change', () => { addonOptions.filter(option => (option.dataset.addonCategoryIds || '').split(',').includes(category.value)).forEach(option => { addonInput(option).checked = category.checked; }); syncAddons(); }));
    addonSelectedList?.addEventListener('click', event => { const row = event.target.closest('[data-addon-selected]'); if (!row) return; if (event.target.closest('[data-addon-remove]')) { const option = addonOptions.find(item => item.dataset.addonId === row.dataset.addonSelected); if (option) addonInput(option).checked = false; syncAddons(); } if (event.target.closest('[data-addon-selected-image]')) showAddonImage(event.target.closest('[data-addon-selected-image]').dataset.image, event.target.closest('[data-addon-selected-image]').dataset.alt); });
    addonRoot.querySelector('[data-addon-options]')?.addEventListener('click', event => { const button = event.target.closest('[data-addon-image-preview]'); if (!button) return; const option = button.closest('[data-addon-option]'); showAddonImage(option.dataset.addonImage, option.dataset.addonName); });
    addonRoot.querySelectorAll('[data-addon-image-close]').forEach(button => button.addEventListener('click', () => addonImageDialog.close()));
    addonImageDialog?.addEventListener('click', event => { if (event.target === addonImageDialog) addonImageDialog.close(); });
    document.addEventListener('click', event => { if (!addonPicker.contains(event.target)) setAddonDropdown(false); });
    syncAddons();
  }

  form.addEventListener('submit', event => {
    syncRichEditor();
    if (event.submitter?.hasAttribute('data-product-archive')) {
      if (!window.confirm('Sigur vrei să arhivezi produsul? Îl poți reactiva ulterior.')) event.preventDefault();
      return;
    }
    const duplicateField = form.querySelector('.product-check-field.is-taken input');
    if (duplicateField) {
      event.preventDefault();
      showStep(0);
      requestAnimationFrame(() => {
        duplicateField.focus({ preventScroll: true });
        duplicateField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        duplicateField.reportValidity();
      });
      return;
    }
    const invalidStep = steps.findIndex((_, index) => fieldsForStep(index).some(field => !field.checkValidity()));
    if (invalidStep >= 0) { event.preventDefault(); showStep(invalidStep); requestAnimationFrame(() => validateStep(invalidStep)); return; }
    submit.classList.add('saving'); submit.textContent = 'SE SALVEAZĂ…';
  });
  const requestedStep = Number(new URLSearchParams(location.search).get('step'));
  showStep(requestedStep >= 1 && requestedStep <= steps.length ? requestedStep - 1 : 0);
});
