document.addEventListener('DOMContentLoaded',()=>{
  const panel=document.querySelector('[data-filters]');
  const backdrop=document.querySelector('[data-filter-backdrop]');
  if(!panel)return;
  const open=()=>{panel.classList.add('open');if(backdrop){backdrop.hidden=false;requestAnimationFrame(()=>backdrop.classList.add('open'))}document.body.classList.add('filters-open')};
  const close=()=>{panel.classList.remove('open');backdrop?.classList.remove('open');document.body.classList.remove('filters-open');window.setTimeout(()=>{if(backdrop&&!backdrop.classList.contains('open'))backdrop.hidden=true},240)};
  document.querySelector('[data-filter-open]')?.addEventListener('click',open);
  document.querySelector('[data-filter-close]')?.addEventListener('click',close);
  backdrop?.addEventListener('click',close);
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&panel.classList.contains('open'))close()});
  panel.querySelectorAll('[data-filter-auto]').forEach(input=>input.addEventListener('change',()=>input.form?.requestSubmit()));
});
