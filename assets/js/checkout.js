document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-customer-type-switch]').forEach(switcher => {
    const form = switcher.closest('form');
    const companyFields = form?.querySelector('[data-company-fields]');
    if (!companyFields) return;

    const updateCustomerType = () => {
      const isCompany = switcher.querySelector('[name="customer_type"]:checked')?.value === 'company';
      companyFields.hidden = !isCompany;
      companyFields.querySelectorAll('[data-company-required]').forEach(input => {
        input.required = isCompany;
        input.disabled = !isCompany;
      });
    };

    switcher.querySelectorAll('[name="customer_type"]').forEach(input => input.addEventListener('change', updateCustomerType));
    updateCustomerType();
  });

  const form = document.querySelector('[data-checkout]');
  if (!form) return;

  const addressBook = form.querySelector('[data-checkout-addresses]');
  if (addressBook) {
    const fields = ['first_name', 'last_name', 'phone', 'county', 'city', 'address', 'postcode'];
    const updateAddress = input => {
      if (!input) return;
      const isNewAddress = input.value === 'new';
      fields.forEach(name => {
        const field = form.elements.namedItem(name);
        if (!(field instanceof HTMLInputElement)) return;
        const dataName = name.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
        const value = isNewAddress
          ? (['first_name', 'last_name', 'phone'].includes(name) ? field.dataset.accountValue || '' : '')
          : input.dataset[dataName] || (['first_name', 'last_name', 'phone'].includes(name) ? field.dataset.accountValue || '' : '');
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
      });
    };

    addressBook.querySelectorAll('input[name="address_choice"]').forEach(input => {
      input.addEventListener('change', () => updateAddress(input));
    });
  }

  const totalLabel = form.querySelector('[data-order-total]');
  const subtotalLabel = form.querySelector('[data-subtotal]');
  const feeRow = form.querySelector('[data-payment-fee-row]');
  const feeLabel = form.querySelector('[data-payment-fee]');
  const base = Number(totalLabel?.dataset.baseTotal || 0);
  const subtotal = Number(subtotalLabel?.dataset.subtotal || 0);
  const format = value => new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(value);
  const updatePaymentFee = () => {
    const method = form.querySelector('[name="payment_method"]:checked');
    if (!method || !totalLabel || !feeRow || !feeLabel) return;
    const value = Number(method.dataset.feeValue || 0);
    const fee = method.dataset.feeType === 'fixed' ? value : method.dataset.feeType === 'percentage' ? subtotal * value / 100 : 0;
    feeRow.hidden = fee <= 0;
    feeLabel.textContent = format(fee);
    totalLabel.textContent = format(base + fee);
  };
  form.querySelectorAll('[name="payment_method"]').forEach(input => input.addEventListener('change', updatePaymentFee));
  updatePaymentFee();
});
