(()=>{const q=(s,r=document)=>r.querySelector(s),qa=(s,r=document)=>[...r.querySelectorAll(s)];
const side=q('[data-sidebar]'),toggle=q('[data-sidebar-toggle]'),back=q('[data-sidebar-backdrop]');function closeSide(){side?.classList.remove('open');back?.classList.remove('show');toggle?.setAttribute('aria-expanded','false')}toggle?.addEventListener('click',()=>{const o=!side.classList.contains('open');side.classList.toggle('open',o);back?.classList.toggle('show',o);toggle.setAttribute('aria-expanded',String(o))});back?.addEventListener('click',closeSide);qa('.admin-sidebar a').forEach(a=>a.addEventListener('click',closeSide));
qa('.profile-menu').forEach(d=>document.addEventListener('click',e=>{if(d.open&&!d.contains(e.target))d.open=false}));
qa('.action-menu').forEach(menu=>{menu.addEventListener('toggle',()=>{if(!menu.open)return;qa('.action-menu[open]').forEach(other=>{if(other!==menu)other.open=false});const nav=q('nav',menu);menu.classList.remove('drop-up');if(nav){const rect=nav.getBoundingClientRect();if(rect.bottom>window.innerHeight-12)menu.classList.add('drop-up');}});q('nav',menu)?.addEventListener('click',e=>{if(e.target.closest('a,button'))menu.open=false;});});

qa('[data-stat]').forEach(el=>{const target=parseInt(el.dataset.stat||el.textContent,10)||0;let start=0;const dur=500,t0=performance.now();function tick(t){const p=Math.min(1,(t-t0)/dur);el.textContent=Math.round(target*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(tick)}requestAnimationFrame(tick)});
qa('.animate-in').forEach((el,i)=>{el.style.animationDelay=Math.min(i*45,360)+'ms'});
const flash=q('[data-flash-message]');if(flash&&window.showNotify){showNotify(flash.dataset.flashMessage||'',flash.dataset.flashType||'info');}
const modal=q('[data-image-modal]'),mimg=q('[data-modal-image]'),mcap=q('[data-modal-caption]');function openModal(src,cap=''){if(!modal||!mimg)return;mimg.src=src;mcap.textContent=cap;modal.classList.add('open');modal.setAttribute('aria-hidden','false')}function closeModal(){modal?.classList.remove('open');modal?.setAttribute('aria-hidden','true');if(mimg)mimg.src=''}qa('[data-preview-src]').forEach(b=>b.addEventListener('click',()=>openModal(b.dataset.previewSrc||'',b.dataset.previewCaption||'')));q('[data-modal-close]')?.addEventListener('click',closeModal);modal?.addEventListener('click',e=>{if(e.target===modal)closeModal()});document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal()});
function initUpload(zone){const input=q('input[type=file]',zone),preview=q('[data-upload-preview]',zone),name=q('[data-upload-name]',zone);if(!input)return;const multiple=input.multiple;let files=[];function render(){if(!preview)return;preview.innerHTML='';files.forEach((f,i)=>{if(!f.type.startsWith('image/'))return;const url=URL.createObjectURL(f),card=document.createElement('div');card.className='upload-preview-item';card.innerHTML='<img alt="Selected image"><button type="button" aria-label="Remove">×</button>';card.querySelector('img').src=url;card.querySelector('button').addEventListener('click',()=>{files.splice(i,1);sync();});preview.appendChild(card)});if(name)name.textContent=files.length?(files.length+' file'+(files.length>1?'s':'')+' selected'):'Drop, paste or choose image'+(multiple?'s':'');}
function sync(){const dt=new DataTransfer();files.forEach(f=>dt.items.add(f));input.files=dt.files;render()}function add(list){const incoming=[...list].filter(f=>f.type.startsWith('image/'));files=multiple?[...files,...incoming].slice(0,8):(incoming.length?[incoming[0]]:files);sync()}input.addEventListener('change',()=>{files=[...input.files];render()});zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('dragover')});zone.addEventListener('dragleave',()=>zone.classList.remove('dragover'));zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('dragover');add(e.dataTransfer.files)});zone.addEventListener('paste',e=>{const fs=[...e.clipboardData.items].filter(x=>x.kind==='file').map(x=>x.getAsFile()).filter(Boolean);if(fs.length){e.preventDefault();add(fs)}});zone.tabIndex=0;zone.addEventListener('click',e=>{if(e.target.closest('button'))return;if(e.target!==input)input.click()});render();}
qa('[data-upload-zone]').forEach(initUpload);
qa('[data-method-select]').forEach(sel=>{const form=sel.closest('form');const update=()=>{qa('[data-method]',form).forEach(el=>el.hidden=el.dataset.method!==sel.value)};sel.addEventListener('change',update);update()});

qa('form[data-swal-confirm]').forEach(form=>form.addEventListener('submit',async e=>{if(form.dataset.swalApproved==='1')return;e.preventDefault();const result=window.Swal?await Swal.fire({icon:'warning',title:form.dataset.swalConfirm||'Confirm action',text:form.dataset.swalText||'Please confirm this action.',showCancelButton:true,confirmButtonText:form.dataset.swalConfirmText||'Yes, continue',cancelButtonText:'Cancel',allowOutsideClick:false}):{isConfirmed:false};if(result.isConfirmed){form.dataset.swalApproved='1';if(form.requestSubmit)form.requestSubmit();else form.submit();}}));

qa('[data-confirm-text]').forEach(form=>form.addEventListener('submit',e=>{const expected=form.dataset.confirmText,input=q('[name=confirmation]',form);if(input&&input.value.trim()!==expected){e.preventDefault();input.focus();if(window.showNotify)showNotify('Type '+expected+' exactly to continue.','warning');else input.setCustomValidity('Type '+expected+' exactly to continue.');}}));
// Live content editor preview while typing.
qa('.cms-form [name]').forEach(field=>field.addEventListener('input',()=>{const iframe=q('.live-preview-panel iframe');if(!iframe||!field.name)return;try{const doc=iframe.contentDocument;doc?.querySelectorAll('[data-content-key="'+CSS.escape(field.name)+'"]').forEach(el=>el.textContent=field.value);}catch(err){}}));
// Secure logout confirmation.
qa('[data-logout-confirm]').forEach(link=>link.addEventListener('click',async e=>{e.preventDefault();const href=link.getAttribute('href');const result=window.Swal?await Swal.fire({icon:'question',title:'Log out of the administrator?',text:'Your current admin session will be closed.',showCancelButton:true,confirmButtonText:'Yes, log out',cancelButtonText:'Stay signed in',allowOutsideClick:false}):{isConfirmed:window.confirm('Log out of the administrator?')};if(result.isConfirmed)window.location.href=href;}));
qa('[data-sortable-list]').forEach(list=>{let dragged=null;const sync=()=>qa('.section-sort-card',list).forEach((card,i)=>{const value=(i+1)*10;const input=q('[data-sort-order]',card),label=q('[data-order-label]',card);if(input)input.value=value;if(label)label.textContent=value;});qa('.section-sort-card',list).forEach(card=>{card.addEventListener('dragstart',()=>{dragged=card;card.classList.add('dragging')});card.addEventListener('dragend',()=>{card.classList.remove('dragging');dragged=null;sync()});card.addEventListener('dragover',e=>{e.preventDefault();if(!dragged||dragged===card)return;const r=card.getBoundingClientRect();list.insertBefore(dragged,e.clientY<r.top+r.height/2?card:card.nextSibling)});});});
})();
// Premium custom select UI. The original <select> remains the submitted value.
(()=>{
  const all=[...document.querySelectorAll('select:not([multiple])')];
  const closeAll=(except=null)=>document.querySelectorAll('.cr-select.open').forEach(w=>{if(w!==except){w.classList.remove('open');w.querySelector('.cr-select-button')?.setAttribute('aria-expanded','false')}});
  all.forEach((select,index)=>{
    if(select.dataset.customSelectReady==='1')return;
    select.dataset.customSelectReady='1';
    select.classList.add('cr-native-select');
    const wrap=document.createElement('div');wrap.className='cr-select';
    const btn=document.createElement('button');btn.type='button';btn.className='cr-select-button';btn.setAttribute('aria-haspopup','listbox');btn.setAttribute('aria-expanded','false');
    const list=document.createElement('div');list.className='cr-select-list';list.setAttribute('role','listbox');list.id='cr-select-'+index;btn.setAttribute('aria-controls',list.id);
    select.insertAdjacentElement('afterend',wrap);wrap.append(btn,list);
    const sync=()=>{
      const selected=select.options[select.selectedIndex];
      btn.textContent=selected?selected.textContent:'Select an option';
      [...list.children].forEach((o,i)=>o.setAttribute('aria-selected',String(i===select.selectedIndex)));
    };
    [...select.options].forEach((opt,i)=>{
      const item=document.createElement('button');item.type='button';item.className='cr-select-option';item.setAttribute('role','option');item.textContent=opt.textContent;item.disabled=opt.disabled;
      item.addEventListener('click',()=>{if(opt.disabled)return;select.selectedIndex=i;select.dispatchEvent(new Event('change',{bubbles:true}));sync();wrap.classList.remove('open');btn.setAttribute('aria-expanded','false');btn.focus();});
      list.appendChild(item);
    });
    btn.addEventListener('click',e=>{e.stopPropagation();const willOpen=!wrap.classList.contains('open');closeAll(wrap);wrap.classList.toggle('open',willOpen);btn.setAttribute('aria-expanded',String(willOpen));if(willOpen){const current=list.children[select.selectedIndex];current?.focus();}});
    btn.addEventListener('keydown',e=>{if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(!wrap.classList.contains('open'))btn.click();}});
    select.addEventListener('change',sync);sync();
  });
  document.addEventListener('click',e=>{if(!e.target.closest('.cr-select'))closeAll();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAll();});
})();
