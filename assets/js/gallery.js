document.addEventListener('DOMContentLoaded',()=>{
  const lightbox=document.querySelector('[data-lightbox]');

  document.querySelectorAll('[data-gallery]').forEach(gallery=>{
    const mainButton=gallery.querySelector('[data-gallery-main]');
    const mainImage=mainButton?.querySelector('img');
    const stage=gallery.querySelector('[data-gallery-stage]');
    const thumbs=[...gallery.querySelectorAll('[data-gallery-thumb]')];
    const currentLabel=gallery.querySelector('[data-gallery-current]');
    if(!mainButton||!mainImage||!stage||!thumbs.length)return;

    let current=Math.max(0,thumbs.findIndex(thumb=>thumb.classList.contains('active')));
    let startX=0,startY=0,dragX=0,dragging=false,horizontalDrag=false,suppressClick=false;

    const show=(requested,direction=0)=>{
      current=(requested+thumbs.length)%thumbs.length;
      const thumb=thumbs[current];
      thumbs.forEach((item,index)=>{
        const active=index===current;
        item.classList.toggle('active',active);
        item.setAttribute('aria-current',String(active));
      });
      mainImage.src=thumb.dataset.galleryThumb;
      mainImage.alt=thumb.dataset.galleryAlt||'';
      if(currentLabel)currentLabel.textContent=String(current+1);
      mainImage.style.removeProperty('transform');
      mainImage.style.removeProperty('opacity');
      mainImage.classList.remove('gallery-change-next','gallery-change-prev');
      void mainImage.offsetWidth;
      if(direction)mainImage.classList.add(direction>0?'gallery-change-next':'gallery-change-prev');
      thumb.scrollIntoView({block:'nearest',inline:'nearest',behavior:'smooth'});
    };

    thumbs.forEach((thumb,index)=>thumb.addEventListener('click',()=>show(index,index>=current?1:-1)));
    gallery.querySelector('[data-gallery-prev]')?.addEventListener('click',event=>{event.stopPropagation();show(current-1,-1)});
    gallery.querySelector('[data-gallery-next]')?.addEventListener('click',event=>{event.stopPropagation();show(current+1,1)});

    mainButton.addEventListener('keydown',event=>{
      if(event.key==='ArrowLeft'){event.preventDefault();show(current-1,-1)}
      if(event.key==='ArrowRight'){event.preventDefault();show(current+1,1)}
    });

    stage.addEventListener('pointerdown',event=>{
      if(event.button!==0||event.target.closest('.gallery-arrow'))return;
      startX=event.clientX;startY=event.clientY;dragX=0;dragging=true;horizontalDrag=false;
      gallery.classList.add('is-dragging');
      stage.setPointerCapture?.(event.pointerId);
    });
    stage.addEventListener('pointermove',event=>{
      if(!dragging)return;
      const dx=event.clientX-startX,dy=event.clientY-startY;
      if(!horizontalDrag&&Math.abs(dy)>Math.abs(dx)+8)return;
      if(Math.abs(dx)>7)horizontalDrag=true;
      if(!horizontalDrag)return;
      event.preventDefault();
      dragX=dx;
      const resisted=Math.max(-150,Math.min(150,dx));
      mainImage.style.transform=`translateX(${resisted}px) scale(.985)`;
      mainImage.style.opacity=String(Math.max(.62,1-Math.abs(resisted)/420));
    });
    const finishDrag=event=>{
      if(!dragging)return;
      dragging=false;gallery.classList.remove('is-dragging');
      try{stage.releasePointerCapture?.(event.pointerId)}catch(_){ }
      mainImage.style.removeProperty('transform');mainImage.style.removeProperty('opacity');
      if(horizontalDrag&&Math.abs(dragX)>=45){
        suppressClick=true;
        show(current+(dragX<0?1:-1),dragX<0?1:-1);
        setTimeout(()=>{suppressClick=false},260);
      }
      horizontalDrag=false;dragX=0;
    };
    stage.addEventListener('pointerup',finishDrag);
    stage.addEventListener('pointercancel',finishDrag);

    mainButton.addEventListener('click',()=>{
      if(suppressClick)return;
      if(lightbox){
        const image=lightbox.querySelector('img');
        if(image){image.src=mainImage.src;image.alt=mainImage.alt}
        lightbox.showModal();
      }
    });
  });

  document.querySelector('[data-lightbox-close]')?.addEventListener('click',()=>lightbox?.close());
  lightbox?.addEventListener('click',event=>{if(event.target===lightbox)lightbox.close()});
});
