(() => {
  const form = document.querySelector('[data-product-review-form]');
  if (!form) return;

  const list = document.querySelector('[data-product-reviews]');
  const empty = document.querySelector('[data-product-reviews-empty]');
  const feedback = form.querySelector('[data-review-form-feedback]');
  const submit = form.querySelector('button[type="submit"]');
  const composeDialog = document.querySelector('[data-review-dialog]');
  const thankYouDialog = document.querySelector('[data-review-thank-you-dialog]');
  const starPicker = form.querySelector('[data-review-star-picker]');
  const starLabels = [...form.querySelectorAll('[data-review-star]')];
  const ratingLabel = form.querySelector('[data-review-rating-label]');
  const ratingNames = ['', 'Slab', 'Acceptabil', 'Bun', 'Foarte bun', 'Excelent'];

  const paintStars = (score = 0, preview = false) => {
    starPicker?.classList.toggle('is-previewing', preview);
    starLabels.forEach(label => label.classList.toggle(preview ? 'is-preview' : 'is-selected', Number(label.dataset.reviewStar) <= score));
    if (ratingLabel) ratingLabel.textContent = score ? `${score} din 5 — ${ratingNames[score]}` : 'Alege numărul de stele';
  };

  const selectedRating = () => Number(form.querySelector('input[name="rating"]:checked')?.value || 0);

  document.querySelectorAll('[data-review-open]').forEach(button => button.addEventListener('click', () => {
    setFeedback();
    composeDialog?.showModal();
    requestAnimationFrame(() => starPicker?.querySelector('input:checked')?.focus() || form.elements.author_name?.focus());
  }));

  composeDialog?.querySelectorAll('[data-review-close]').forEach(button => button.addEventListener('click', () => composeDialog.close()));
  composeDialog?.addEventListener('click', event => { if (event.target === composeDialog) composeDialog.close(); });

  starLabels.forEach(label => {
    const score = Number(label.dataset.reviewStar);
    label.addEventListener('mouseenter', () => paintStars(score, true));
    label.addEventListener('focus', () => paintStars(score, true));
    label.addEventListener('click', () => {
      starLabels.forEach(star => star.classList.remove('is-preview'));
      requestAnimationFrame(() => paintStars(score));
    });
  });
  form.querySelectorAll('input[name="rating"]').forEach(input => input.addEventListener('change', () => paintStars(Number(input.value))));
  starPicker?.querySelector('div')?.addEventListener('mouseleave', () => {
    starLabels.forEach(label => label.classList.remove('is-preview'));
    paintStars(selectedRating());
  });

  const setFeedback = (message = '', error = false) => {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.hidden = !message;
    feedback.classList.toggle('is-error', error);
  };

  const createReviewCard = review => {
    const article = document.createElement('article');
    article.className = 'product-review-card is-new';

    const stars = document.createElement('div');
    stars.className = 'rating-stars';
    stars.setAttribute('aria-label', `${review.rating} din 5 stele`);
    stars.append(document.createTextNode('★'.repeat(review.rating)));
    const mutedStars = document.createElement('i');
    mutedStars.textContent = '★'.repeat(Math.max(0, 5 - review.rating));
    stars.append(mutedStars);

    const title = document.createElement('h3');
    title.textContent = review.title || 'Recenzie client';
    const body = document.createElement('p');
    body.textContent = review.body;
    const author = document.createElement('small');
    author.textContent = `${review.author_name} · ${review.created_label || 'acum'}`;
    article.append(stars, title, body, author);
    return article;
  };

  const updateSummary = summary => {
    if (!summary) return;
    const productSummary = document.querySelector('.product-summary');
    if (!productSummary) return;
    let rating = productSummary.querySelector('.rating');
    if (!rating) {
      rating = document.createElement('div');
      rating.className = 'rating';
      rating.innerHTML = '<span></span><a href="#recenzii"></a>';
      productSummary.querySelector('h1')?.after(rating);
    }
    const rounded = Math.max(0, Math.min(5, Math.round(Number(summary.rating) || 0)));
    rating.querySelector('span').textContent = `${'★'.repeat(rounded)}${'☆'.repeat(5 - rounded)}`;
    rating.querySelector('a').textContent = `${summary.count} ${summary.count === 1 ? 'recenzie' : 'recenzii'}`;
    const average = document.querySelector('[data-review-average]');
    const summaryStars = document.querySelector('[data-review-summary-stars]');
    const summaryCount = document.querySelector('[data-review-summary-count]');
    if (average) average.textContent = Number(summary.rating || 0).toLocaleString('ro-RO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    if (summaryStars) summaryStars.textContent = `${'★'.repeat(rounded)}${'☆'.repeat(5 - rounded)}`;
    if (summaryCount) summaryCount.textContent = `${summary.count} ${summary.count === 1 ? 'recenzie' : 'recenzii'}`;
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const savedName = form.elements.author_name?.value || '';
    const savedEmail = form.elements.email?.value || '';
    setFeedback();
    submit.disabled = true;
    submit.dataset.originalText ||= submit.textContent;
    submit.textContent = 'SE PUBLICĂ…';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.ok) throw new Error(data.message || 'Recenzia nu a putut fi publicată. Încearcă din nou.');

      empty?.remove();
      list?.prepend(createReviewCard(data.review));
      updateSummary(data.summary);
      form.reset();
      if (form.elements.author_name) form.elements.author_name.value = savedName;
      if (form.elements.email) form.elements.email.value = savedEmail;
      paintStars(0);
      setFeedback();
      composeDialog?.close();
      thankYouDialog?.showModal();
    } catch (error) {
      setFeedback(error.message || 'A apărut o eroare. Încearcă din nou.', true);
    } finally {
      submit.disabled = false;
      submit.textContent = submit.dataset.originalText || 'TRIMITE RECENZIA';
    }
  });

  thankYouDialog?.querySelectorAll('[data-review-thank-you-close]').forEach(button => button.addEventListener('click', () => thankYouDialog.close()));
  thankYouDialog?.addEventListener('click', event => { if (event.target === thankYouDialog) thankYouDialog.close(); });
})();
