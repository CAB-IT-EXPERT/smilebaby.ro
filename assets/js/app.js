document.addEventListener('DOMContentLoaded',()=>{
  const header=document.querySelector('[data-header]');
  const menu=document.querySelector('#mobile-menu');
  const toggle=document.querySelector('[data-menu-toggle]');
  addEventListener('scroll',()=>header?.classList.toggle('scrolled',scrollY>12),{passive:true});
  const menuIcon='<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';
  const closeIcon='<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg>';
  let menuTimer=0;
  const setMenuOpen=open=>{
    if(!menu||!toggle)return;
    clearTimeout(menuTimer);
    if(open){
      menu.hidden=false;
      requestAnimationFrame(()=>menu.classList.add('open'));
    }else{
      menu.classList.remove('open');
      menuTimer=setTimeout(()=>{menu.hidden=true},280);
    }
    document.body.classList.toggle('mobile-menu-open',open);
    toggle.setAttribute('aria-expanded',String(open));
    toggle.setAttribute('aria-label',open?'Închide meniul':'Deschide meniul');
    toggle.innerHTML=open?closeIcon:menuIcon;
  };
  toggle?.addEventListener('click',()=>setMenuOpen(menu?.hidden??true));
  menu?.addEventListener('click',event=>{if(event.target===menu)setMenuOpen(false)});
  menu?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>setMenuOpen(false)));
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu&&!menu.hidden)setMenuOpen(false)});
  document.querySelectorAll('[data-qty-minus],[data-qty-plus]').forEach(button=>button.addEventListener('click',()=>{const input=button.parentElement.querySelector('input');const delta=button.hasAttribute('data-qty-plus')?1:-1;input.value=Math.max(1,Math.min(99,Number(input.value||1)+delta))}));
  document.querySelectorAll('[data-tab]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.tab-panel').forEach(x=>x.classList.remove('active'));button.classList.add('active');document.getElementById(button.dataset.tab)?.classList.add('active')}));
  document.querySelectorAll('.product-short,#descriere').forEach(container=>{
    if(container.innerHTML.includes('\\n')||container.innerHTML.includes('\\r'))container.innerHTML=container.innerHTML.replaceAll('\\r','').replaceAll('\\n','');
  });
  const whatsappFloat=document.querySelector('.whatsapp-float');
  const productActions=document.querySelector('.product-actions');
  if(whatsappFloat&&productActions&&'IntersectionObserver'in window){
    new IntersectionObserver(entries=>whatsappFloat.classList.toggle('avoid-product-actions',entries[0]?.isIntersecting),{threshold:.15}).observe(productActions);
  }
  const cookie=document.querySelector('[data-cookie-banner]');
  if(cookie&&!localStorage.getItem('sb_cookie_consent'))cookie.hidden=false;
  document.querySelector('[data-cookie-essential]')?.addEventListener('click',()=>{localStorage.setItem('sb_cookie_consent','necessary');cookie.hidden=true});
  document.querySelector('[data-cookie-accept]')?.addEventListener('click',()=>{localStorage.setItem('sb_cookie_consent','all');cookie.hidden=true;dispatchEvent(new CustomEvent('smilebaby:consent'))});
  document.querySelectorAll('[data-cookie-settings]').forEach(button=>button.addEventListener('click',()=>{if(!cookie)return;cookie.hidden=false;cookie.classList.remove('cookie-reopen');requestAnimationFrame(()=>cookie.classList.add('cookie-reopen'));cookie.querySelector('button')?.focus()}));
});
