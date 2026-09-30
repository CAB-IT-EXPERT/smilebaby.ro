document.addEventListener('DOMContentLoaded',()=>{
  const page=document.querySelector('[data-admin-order-page]');
  if(!page)return;

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
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&imageDialog?.open)imageDialog.close()});
});
