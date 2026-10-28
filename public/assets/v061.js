(() => {
 'use strict';
 const more=document.querySelector('header .menu-more');
 if(more){const mobile=matchMedia('(max-width:760px)');const sync=()=>{more.open=!mobile.matches;};sync();mobile.addEventListener('change',sync);document.addEventListener('click',e=>{if(mobile.matches&&!more.contains(e.target))more.open=false;});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&mobile.matches&&more.open){more.open=false;more.querySelector('summary').focus();}});}

 const queue=document.querySelector('[data-achievement-queue]');
 if(queue){
  const notices=[...queue.querySelectorAll('[data-achievement-notice]')],live=queue.querySelector('.achievement-announcement');let timer;
  const next=()=>{clearTimeout(timer);live.replaceChildren();const item=notices.shift();if(!item)return;
   const icon=document.createElement('span');icon.className='notice-icon';icon.textContent='✦';icon.setAttribute('aria-hidden','true');
   const close=document.createElement('button');close.type='button';close.textContent='Fechar';close.setAttribute('aria-label','Fechar notificação de conquista');close.addEventListener('click',next);
   const title=document.createElement('h2');title.textContent=item.dataset.title;
   const desc=document.createElement('p');desc.textContent=item.dataset.description;
   const points=document.createElement('p');points.textContent=item.dataset.g?`+${item.dataset.g} R`:'';
   const link=document.createElement('a');link.href='/conquistas';link.textContent='Ver conquistas';live.append(icon,close,title,desc,points,link);timer=setTimeout(()=>{if(live.contains(document.activeElement))return;next();},5000);
  };
  live.addEventListener('mouseenter',()=>clearTimeout(timer));live.addEventListener('focusin',()=>clearTimeout(timer));
  live.addEventListener('mouseleave',()=>{if(!live.contains(document.activeElement))timer=setTimeout(next,5000);});live.addEventListener('focusout',()=>{timer=setTimeout(()=>{if(!live.contains(document.activeElement))next();},5000);});
  requestAnimationFrame(next);
 }
 const form=document.querySelector('form[action="/feedback"]');
 if(form){
  const comment=form.querySelector('textarea'),counter=document.getElementById('feedback-count');
  const count=()=>{counter.textContent=`${[...comment.value].length} de 1.000 caracteres`;};comment.addEventListener('input',count);count();
  let pending=false;form.addEventListener('submit',async e=>{
   e.stopImmediatePropagation(); // This form owns its feedback-specific retry state.
   if(pending){e.preventDefault();return;}pending=true;const button=form.querySelector('button[type="submit"]'),status=form.querySelector('[data-feedback-status]');
   button.disabled=true;status.textContent='Enviando sua avaliação…';if(!window.fetch||!window.AbortController)return;
   e.preventDefault();const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),45000);
   try{
    const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',signal:controller.signal});const html=await response.text();
    if(new URL(response.url).origin!==location.origin)throw Error('Não foi possível confirmar a resposta.');
    if(!response.ok){const doc=new DOMParser().parseFromString(html,'text/html');throw Error(doc.querySelector('.alert-danger')?.textContent?.trim()||'Não foi possível receber sua avaliação.');}
    clearTimeout(timer);history.replaceState(null,'',response.url);document.open();document.write(html);document.close();
   }catch(error){clearTimeout(timer);pending=false;button.disabled=false;delete form.dataset.busy;form.classList.remove('submitting');form.removeAttribute('aria-busy');status.textContent=error.name==='AbortError'?'A confirmação demorou. Tente enviar novamente; o mesmo envio não será duplicado.':(error.message==='Failed to fetch'?'Não foi possível conectar. Tente novamente com o mesmo formulário.':error.message);}
  },true);
  window.addEventListener('pageshow',()=>{pending=false;form.querySelector('button[type="submit"]').disabled=false;});
 }
})();
