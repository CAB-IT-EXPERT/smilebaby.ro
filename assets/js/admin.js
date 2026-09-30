document.addEventListener('DOMContentLoaded',()=>{
  const sidebar=document.querySelector('[data-admin-sidebar]'),backdrop=document.querySelector('[data-admin-sidebar-backdrop]');
  const sidebarCollapse=document.querySelector('[data-admin-sidebar-collapse]');
  const syncCollapsedSidebar=()=>{const collapsed=document.body.classList.contains('admin-sidebar-collapsed');sidebarCollapse?.setAttribute('aria-pressed',String(collapsed));sidebarCollapse?.setAttribute('aria-label',collapsed?'Extinde meniul':'Restrânge meniul')};
  sidebar?.querySelectorAll('nav a').forEach(link=>{link.dataset.sidebarLabel=link.querySelector('strong')?.textContent?.trim()||''});
  sidebarCollapse?.addEventListener('click',()=>{document.body.classList.toggle('admin-sidebar-collapsed');const collapsed=document.body.classList.contains('admin-sidebar-collapsed');try{localStorage.setItem('smilebaby-admin-sidebar',collapsed?'collapsed':'expanded')}catch(_){}syncCollapsedSidebar()});
  syncCollapsedSidebar();
  const setSidebar=open=>{if(!sidebar)return;sidebar.classList.toggle('open',open);if(backdrop){backdrop.hidden=!open;requestAnimationFrame(()=>backdrop.classList.toggle('visible',open))}document.body.classList.toggle('admin-menu-open',open);document.querySelector('.admin-menu-toggle')?.setAttribute('aria-expanded',String(open))};
  document.querySelector('.admin-menu-toggle')?.addEventListener('click',()=>setSidebar(!sidebar?.classList.contains('open')));
  document.querySelector('[data-admin-sidebar-close]')?.addEventListener('click',()=>setSidebar(false));backdrop?.addEventListener('click',()=>setSidebar(false));
  const topbarMenus=[...document.querySelectorAll('[data-admin-topbar-menu]')];
  const closeTopbarMenus=except=>topbarMenus.forEach(menu=>{if(menu===except)return;menu.classList.remove('open');menu.querySelector('[data-admin-topbar-toggle]')?.setAttribute('aria-expanded','false');const panel=menu.querySelector('[data-admin-topbar-popover]');if(panel)panel.hidden=true});
  topbarMenus.forEach(menu=>menu.querySelector('[data-admin-topbar-toggle]')?.addEventListener('click',event=>{event.stopPropagation();const panel=menu.querySelector('[data-admin-topbar-popover]'),willOpen=!menu.classList.contains('open');closeTopbarMenus(menu);menu.classList.toggle('open',willOpen);event.currentTarget.setAttribute('aria-expanded',String(willOpen));if(panel)panel.hidden=!willOpen}));
  document.addEventListener('click',event=>{if(!event.target.closest('[data-admin-topbar-menu]'))closeTopbarMenus()});
  addEventListener('keydown',event=>{if(event.key==='Escape'){setSidebar(false);closeTopbarMenus()}});
  sidebar?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{if(innerWidth<=900)setSidebar(false)}));
  document.querySelectorAll('[data-admin-tab]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-admin-tab]').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.editor-panel').forEach(x=>x.classList.remove('active'));button.classList.add('active');document.getElementById(button.dataset.adminTab)?.classList.add('active')}));
  const removedSettingsTabs=['general','homepage','social'];
  removedSettingsTabs.forEach(id=>{document.querySelector(`[data-settings-tab="${id}"]`)?.remove();document.getElementById(id)?.remove()});
  document.querySelectorAll('.payment-admin-card').forEach(card=>{
    card.querySelector('textarea[name="instructions"]')?.closest('label')?.remove();
    card.querySelectorAll('details').forEach(section=>section.remove());
  });
  const syncSettingsSidebar=id=>document.querySelectorAll('[data-admin-sidebar] a[href^="/admin/setari#"]').forEach(link=>link.classList.toggle('active',link.getAttribute('href')===`/admin/setari#${id}`));
  const openSettings=id=>{document.querySelectorAll('[data-settings-tab]').forEach(x=>x.classList.toggle('active',x.dataset.settingsTab===id));document.querySelectorAll('.settings-panel').forEach(x=>x.classList.toggle('active',x.id===id));syncSettingsSidebar(id)};
  const shippingPanel=document.getElementById('livrare'),shippingEnabledInput=shippingPanel?.querySelector('input[name="shipping_enabled"]');
  if(shippingPanel&&shippingEnabledInput&&!shippingPanel.querySelector('input[name="announcement_enabled"]')){
    const announcementHidden=document.createElement('input'),announcementToggle=document.createElement('label');
    announcementHidden.type='hidden';announcementHidden.name='announcement_enabled';announcementHidden.value='0';
    announcementToggle.className='switch-row';
    announcementToggle.innerHTML='<span><strong>Afișează bara de livrare gratuită</strong><small>Bara apare numai când livrarea este activă și pragul „Gratuit peste” este mai mare decât 0.</small></span><input type="checkbox" name="announcement_enabled" value="1" role="switch"><i></i>';
    announcementToggle.querySelector('input').checked=document.body.dataset.announcementEnabled==='1';
    shippingEnabledInput.before(announcementHidden,announcementToggle);
    const threshold=shippingPanel.querySelector('input[name="free_shipping_threshold"]');
    const standardCost=shippingPanel.querySelector('input[name="standard_shipping_cost"]');
    if(standardCost&&!shippingPanel.querySelector('input[name="return_shipping_cost"]')){
      const returnLabel=document.createElement('label');
      returnLabel.innerHTML='Cost retur<input type="number" step="0.01" min="0" name="return_shipping_cost"><small>Setează 0 dacă returul organizat prin magazin este gratuit.</small>';
      returnLabel.querySelector('input').value=document.body.dataset.returnShippingCost||'20';
      standardCost.closest('label')?.after(returnLabel);
    }
    if(threshold){threshold.min='0';threshold.insertAdjacentHTML('afterend','<small>Lasă gol sau setează 0 pentru a ascunde automat bara.</small>')}
  }
  document.querySelectorAll('[data-settings-tab]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.settingsTab==='email'){location.href='/admin/setari/email';return}openSettings(button.dataset.settingsTab);history.replaceState(null,'','#'+button.dataset.settingsTab)}));
  if(document.querySelector('.settings-tabs')){
    const requested=location.hash.slice(1);
    const initial=document.querySelector(`[data-settings-tab="${requested}"]`)?requested:'livrare';
    openSettings(initial);
    if(initial!==requested)history.replaceState(null,'','#'+initial);
    addEventListener('hashchange',()=>{const id=location.hash.slice(1);if(document.querySelector(`[data-settings-tab="${id}"]`))openSettings(id)});
  }

  /* Product search: recent products, instant filtering and keyboard navigation. */
  const productSearch=document.querySelector('[data-product-search]');
  if(productSearch){
    const input=productSearch.querySelector('[data-product-search-input]'),panel=productSearch.querySelector('[data-product-search-panel]'),items=[...productSearch.querySelectorAll('[data-product-suggestion]')],count=productSearch.querySelector('[data-product-search-count]'),heading=productSearch.querySelector('[data-product-search-heading]'),empty=productSearch.querySelector('[data-product-search-empty]');
    let activeIndex=-1;
    const normalize=value=>(value||'').toLocaleLowerCase('ro').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,' ').trim();
    const stems={trusouri:'trusou',trusourile:'trusou',lumanari:'lumanar',lumanare:'lumanar',marturii:'martur',marturie:'martur',botezuri:'botez',fetite:'fetit',fetita:'fetit',baieti:'baiet',baiat:'baiet',cutii:'cuti',cutie:'cuti',seturi:'set'};
    const stem=word=>stems[word]||word;
    const distance=(a,b)=>{const row=[...Array(b.length+1).keys()];for(let i=1;i<=a.length;i++){let previous=row[0];row[0]=i;for(let j=1;j<=b.length;j++){const saved=row[j];row[j]=Math.min(row[j]+1,row[j-1]+1,previous+(a[i-1]===b[j-1]?0:1));previous=saved}}return row[b.length]};
    const similar=(query,word)=>{if(query===word||stem(query)===stem(word))return true;if(Math.min(query.length,word.length)>=3&&(word.startsWith(query)||query.startsWith(word)))return true;if(Math.min(query.length,word.length)>=4&&(word.includes(query)||query.includes(word)))return true;const size=Math.max(query.length,word.length),allowed=size<=4?1:size<=8?2:3;return size>=3&&distance(query,word)<=allowed};
    const matches=(query,value)=>{const tokens=normalize(query).split(' ').filter(Boolean),words=normalize(value).split(' ').filter(Boolean);return tokens.every(token=>words.some(word=>similar(token,word)))};
    const visibleItems=()=>items.filter(item=>!item.hidden);
    const select=index=>{const visible=visibleItems();items.forEach(item=>item.classList.remove('active'));if(!visible.length){activeIndex=-1;return}activeIndex=(index+visible.length)%visible.length;visible[activeIndex].classList.add('active');visible[activeIndex].scrollIntoView({block:'nearest'})};
    const filter=()=>{const query=normalize(input.value);let visible=0;items.forEach(item=>{item.hidden=query!==''&&!matches(query,item.dataset.searchTerms);if(!item.hidden)visible++});if(count)count.textContent=visible+' '+(visible===1?'sugestie':'sugestii');if(heading)heading.textContent=query?'Rezultate rapide':'Produse recente';if(empty)empty.hidden=visible>0;activeIndex=-1;items.forEach(item=>item.classList.remove('active'))};
    const open=()=>{filter();panel.hidden=false};
    const close=()=>{panel.hidden=true;items.forEach(item=>item.classList.remove('active'));activeIndex=-1};
    input?.addEventListener('focus',open);input?.addEventListener('click',open);input?.addEventListener('input',open);
    input?.addEventListener('keydown',event=>{if(event.key==='ArrowDown'){event.preventDefault();if(panel.hidden)open();select(activeIndex+1)}else if(event.key==='ArrowUp'){event.preventDefault();if(panel.hidden)open();select(activeIndex-1)}else if(event.key==='Enter'&&activeIndex>=0){const active=visibleItems()[activeIndex];if(active){event.preventDefault();location.href=active.href}}else if(event.key==='Escape'){close();input.blur()}});
    document.addEventListener('click',event=>{if(!productSearch.contains(event.target))close()});
  }

  const productLimitDialog=document.querySelector('[data-product-limit-dialog]');
  document.querySelector('[data-product-limit-open]')?.addEventListener('click',()=>productLimitDialog?.showModal());
  productLimitDialog?.querySelectorAll('[data-product-limit-close]').forEach(button=>button.addEventListener('click',()=>productLimitDialog.close()));
  productLimitDialog?.addEventListener('click',event=>{if(event.target===productLimitDialog)productLimitDialog.close()});

  /* One consistent, explicit confirmation before a permanent delete. */
  const deleteModal=document.querySelector('[data-admin-delete-modal]'),deleteForm=deleteModal?.querySelector('[data-admin-delete-form]'),deleteName=deleteModal?.querySelector('[data-admin-delete-name]');
  const openDeleteModal=trigger=>{if(!deleteModal||!deleteForm||trigger.disabled)return;const kind=trigger.dataset.deleteKind||'elementul',name=trigger.dataset.deleteName||'';deleteForm.action=trigger.dataset.deleteAction||'';if(deleteName)deleteName.textContent=(kind+' „'+name+'”').trim();const categoryDialog=document.querySelector('[data-category-editor]');if(categoryDialog?.open)categoryDialog.close();deleteModal.showModal()};
  document.addEventListener('click',event=>{const trigger=event.target.closest('[data-admin-delete]');if(trigger){event.preventDefault();openDeleteModal(trigger)}});
  deleteModal?.querySelectorAll('[data-admin-delete-cancel]').forEach(button=>button.addEventListener('click',()=>deleteModal.close()));
  deleteModal?.addEventListener('click',event=>{if(event.target===deleteModal)deleteModal.close()});

  /* Guided category editor, shared by create and edit. */
  const categoryDialog=document.querySelector('[data-category-editor]');
  if(categoryDialog){
    const form=categoryDialog.querySelector('[data-category-editor-form]'),title=categoryDialog.querySelector('[data-category-editor-title]'),name=categoryDialog.querySelector('[data-category-name]'),slug=categoryDialog.querySelector('[data-category-slug]'),parent=categoryDialog.querySelector('[data-category-parent]'),shortDescription=categoryDialog.querySelector('[data-category-short-description]'),description=categoryDialog.querySelector('[data-category-description]'),status=categoryDialog.querySelector('[data-category-status]'),order=categoryDialog.querySelector('[data-category-order]'),homepage=categoryDialog.querySelector('[data-category-homepage]'),metaTitle=categoryDialog.querySelector('[data-category-meta-title]'),metaDescription=categoryDialog.querySelector('[data-category-meta-description]'),indexable=categoryDialog.querySelector('[data-category-indexable]'),imageInput=categoryDialog.querySelector('[data-category-image]'),imagePreview=categoryDialog.querySelector('[data-category-image-preview]'),previewImage=imagePreview?.querySelector('img'),deleteButton=categoryDialog.querySelector('[data-category-modal-delete]');
    const steps=[...categoryDialog.querySelectorAll('[data-category-step]')],stepButtons=[...categoryDialog.querySelectorAll('[data-category-step-button]')],stepLabel=categoryDialog.querySelector('[data-category-step-label]'),previousStep=categoryDialog.querySelector('[data-category-step-prev]'),nextStep=categoryDialog.querySelector('[data-category-step-next]'),submitButton=categoryDialog.querySelector('[data-category-submit]');
    let createMode=true,slugTouched=false,previewObjectUrl='',currentStep=0;
    const cleanSlug=value=>(value||'').toLocaleLowerCase('ro').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    const setPreview=url=>{if(!imagePreview||!previewImage)return;if(url){previewImage.src=url;imagePreview.hidden=false}else{previewImage.removeAttribute('src');imagePreview.hidden=true}};
    const prepareParent=id=>{[...parent.options].forEach(option=>{option.disabled=id&&Number(option.value)===Number(id)})};
    const setStep=index=>{currentStep=Math.max(0,Math.min(steps.length-1,index));steps.forEach((step,i)=>step.classList.toggle('active',i===currentStep));stepButtons.forEach((button,i)=>button.classList.toggle('active',i===currentStep));if(stepLabel)stepLabel.textContent='Pasul '+(currentStep+1)+' din '+steps.length;if(previousStep)previousStep.hidden=currentStep===0;if(nextStep)nextStep.hidden=currentStep===steps.length-1;if(submitButton)submitButton.hidden=currentStep!==steps.length-1;categoryDialog.querySelector('.category-editor-panels')?.scrollTo({top:0,behavior:'smooth'})};
    const openCategory=(data=null)=>{createMode=!data;slugTouched=!!data;form.reset();form.action=data?'/admin/categorii/'+data.id+'/salvare':'/admin/categorii/salvare';title.textContent=data?'Editează categoria':'Categorie nouă';name.value=data?.name||'';slug.value=data?.slug||'';parent.value=data?.parent_id||'';shortDescription.value=data?.short_description||'';description.value=data?.description||'';status.value=data?.status||'active';order.value=data?.homepage_order??0;homepage.checked=!!data?.show_on_homepage;metaTitle.value=data?.meta_title||'';metaDescription.value=data?.meta_description||'';indexable.checked=data?!!data.indexable:true;prepareParent(data?.id||0);setPreview(data?.image_url&&!data.image_url.includes('placeholder')?data.image_url:'');if(deleteButton){deleteButton.hidden=!data;deleteButton.disabled=!!data&&Number(data.product_count)>0;deleteButton.dataset.deleteKind='categoria';deleteButton.dataset.deleteName=data?.name||'';deleteButton.dataset.deleteAction=data?'/admin/categorii/'+data.id+'/stergere':'';deleteButton.dataset.tooltip=deleteButton.disabled?'Categoria conține produse și nu poate fi ștearsă':'Șterge categoria';deleteButton.toggleAttribute('data-admin-delete',!!data&&!deleteButton.disabled)}setStep(0);categoryDialog.showModal();requestAnimationFrame(()=>name.focus())};
    const categoryData=id=>{const node=document.getElementById('category-data-'+id);if(!node)return null;try{return JSON.parse(node.textContent)}catch{return null}};
    document.addEventListener('click',event=>{if(event.target.closest('[data-category-create]'))openCategory();const edit=event.target.closest('[data-category-edit]');if(edit){const data=categoryData(edit.dataset.categoryEdit);if(data)openCategory(data)}});
    categoryDialog.querySelectorAll('[data-category-editor-close]').forEach(button=>button.addEventListener('click',()=>categoryDialog.close()));
    stepButtons.forEach(button=>button.addEventListener('click',()=>setStep(Number(button.dataset.categoryStepButton))));
    previousStep?.addEventListener('click',()=>setStep(currentStep-1));
    nextStep?.addEventListener('click',()=>{if(currentStep===0&&!name.value.trim()){name.focus();name.reportValidity();return}setStep(currentStep+1)});
    categoryDialog.addEventListener('click',event=>{if(event.target===categoryDialog)categoryDialog.close()});
    categoryDialog.addEventListener('close',()=>{const url=new URL(location.href);if(url.searchParams.has('create')||url.searchParams.has('edit')){url.searchParams.delete('create');url.searchParams.delete('edit');history.replaceState(null,'',url.pathname+(url.searchParams.size?'?'+url.searchParams.toString():''))}});
    name?.addEventListener('input',()=>{if(createMode&&!slugTouched)slug.value=cleanSlug(name.value)});slug?.addEventListener('input',()=>{slugTouched=slug.value.trim()!==''});
    imageInput?.addEventListener('change',()=>{if(previewObjectUrl)URL.revokeObjectURL(previewObjectUrl);const file=imageInput.files?.[0];previewObjectUrl=file?URL.createObjectURL(file):'';setPreview(previewObjectUrl)});
    const params=new URLSearchParams(location.search);if(params.get('create')==='1')openCategory();else if(params.get('edit')){const data=categoryData(params.get('edit'));if(data)openCategory(data)}
  }
});
