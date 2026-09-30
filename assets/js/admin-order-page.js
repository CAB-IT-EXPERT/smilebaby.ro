// Order page interactions are kept in this versioned asset so the admin never loads stale dialog behavior.
const initAdminOrderPage=()=>{
  const page=document.querySelector('[data-admin-order-page]');
  if(!page||page.dataset.orderPageReady==='1')return;
  page.dataset.orderPageReady='1';

  const statusForm=page.querySelector('[data-order-status-form]');
  const messageInput=statusForm?.querySelector('[data-order-status-message-input]');
  statusForm?.querySelectorAll('input[name="status"]').forEach(input=>input.addEventListener('change',()=>{
    if(input.checked&&messageInput)messageInput.value=input.dataset.orderStatusMessage||'';
  }));

  const imageDialog=document.querySelector('[data-order-image-dialog]');
  const imagePreview=imageDialog?.querySelector('[data-order-image-preview]');
  page.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-order-image]');
    if(!trigger||!imageDialog||!imagePreview)return;
    imagePreview.src=trigger.dataset.orderImage||'';
    imagePreview.alt=trigger.dataset.orderImageAlt||'Previzualizare produs';
    imageDialog.showModal();
  });
  imageDialog?.querySelector('[data-order-image-close]')?.addEventListener('click',()=>imageDialog.close());
  imageDialog?.addEventListener('click',event=>{if(event.target===imageDialog)imageDialog.close()});

  const customerDialog=document.querySelector('[data-order-customer-dialog]');
  const customerType=customerDialog?.querySelector('[data-order-customer-type]');
  const companyFields=customerDialog?.querySelector('[data-order-company-fields]');
  const syncCompanyFields=()=>{
    if(!companyFields||!customerType)return;
    companyFields.hidden=customerType.value!=='company';
  };
  page.querySelector('[data-order-customer-open]')?.addEventListener('click',()=>{
    syncCompanyFields();
    customerDialog?.showModal();
  });
  customerDialog?.querySelectorAll('[data-order-customer-close]').forEach(button=>button.addEventListener('click',()=>customerDialog.close()));
  customerDialog?.addEventListener('click',event=>{if(event.target===customerDialog)customerDialog.close()});
  customerType?.addEventListener('change',syncCompanyFields);

  document.addEventListener('keydown',event=>{
    if(event.key!=='Escape')return;
    if(imageDialog?.open)imageDialog.close();
    if(customerDialog?.open)customerDialog.close();
  });
};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initAdminOrderPage,{once:true});
else initAdminOrderPage();
