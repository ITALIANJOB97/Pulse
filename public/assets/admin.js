'use strict';
document.querySelectorAll('[data-confirm]').forEach(function(button){
  button.addEventListener('click',function(event){if(!window.confirm(button.dataset.confirm))event.preventDefault();});
});
const wizard=document.querySelector('[data-wizard]');
if(wizard){
  const steps=Array.from(wizard.querySelectorAll('[data-step]'));
  const markers=Array.from(wizard.querySelectorAll('.wizard-progress span'));
  const back=wizard.querySelector('[data-back]'),next=wizard.querySelector('[data-next]'),finish=wizard.querySelector('[data-finish]');
  let current=0;
  function show(index){
    current=index;
    steps.forEach(function(step,i){step.hidden=i!==index;});
    markers.forEach(function(marker,i){marker.classList.toggle('current',i===index);});
    back.hidden=index===0;next.hidden=index===steps.length-1;finish.hidden=index!==steps.length-1;
  }
  next.addEventListener('click',function(){
    const invalid=Array.from(steps[current].querySelectorAll('input,select')).find(function(input){return !input.checkValidity();});
    if(invalid){invalid.reportValidity();return;}
    show(Math.min(current+1,steps.length-1));
  });
  back.addEventListener('click',function(){show(Math.max(current-1,0));});
  wizard.closest('form').addEventListener('invalid',function(event){
    const index=steps.findIndex(function(step){return step.contains(event.target);});
    if(index>=0)show(index);
  },true);
  show(0);
}

// Preview the complete selected image before saving, without uploading it.
document.querySelectorAll('input[type=file][accept*="image/"]').forEach(function(input){
  let preview=null,url=null;
  input.addEventListener('change',function(){
    if(url){URL.revokeObjectURL(url);url=null;}
    if(preview){preview.remove();preview=null;}
    const file=input.files&&input.files[0];
    if(!file||!['image/jpeg','image/png','image/webp'].includes(file.type))return;
    preview=document.createElement('img');preview.className='upload-preview';preview.alt='';
    url=URL.createObjectURL(file);preview.src=url;input.insertAdjacentElement('afterend',preview);
  });
});


// Checks run after paint; the trusted service serializes installation and downloads.
const updateBackground=document.querySelector('[data-update-background]');
const updatePanel=document.getElementById('update-panel');
if(updateBackground){
  let updateKey=null,polling=false;
  async function updateRequest(url,options={}){
    const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),25000);
    try{
      const response=await fetch(url,{...options,signal:controller.signal,headers:{'Accept':'application/json',...(options.headers||{})}});
      if(!response.ok)throw new Error('Update request unavailable');
      return await response.json();
    }finally{clearTimeout(timeout);}
  }
  function showUpdate(data){
    if(!updatePanel)return;
    if(updateKey!==data.key){updatePanel.innerHTML=data.html;updateKey=data.key;}
    const container=updatePanel.querySelector('[data-download-progress]');
    if(container&&data.progress){
      container.hidden=false;
      const bar=container.querySelector('progress'),percent=data.progress.percent;
      if(percent===null)bar.removeAttribute('value');else bar.value=percent;
      container.querySelector('[data-progress-percent]').textContent=percent===null?'—':percent+'%';
      container.querySelector('[data-progress-detail]').textContent=data.progressText;
    }
    const error=updatePanel.querySelector('[data-update-client-error]');if(error)error.hidden=true;
  }
  function updateError(){const error=updatePanel&&updatePanel.querySelector('[data-update-client-error]');if(error)error.hidden=false;}
  async function refreshUpdates(){
    if(polling||document.hidden)return;
    polling=true;
    try{showUpdate(await updateRequest('/api/updates'));}catch(error){updateError();}finally{polling=false;}
  }
  async function backgroundCheck(){
    try{showUpdate(await updateRequest('/api/updates/check',{method:'POST',body:new URLSearchParams({csrf:updateBackground.dataset.csrf})}));}catch(error){updateError();}
  }
  setTimeout(backgroundCheck,1000);
  setInterval(backgroundCheck,6*60*60*1000);
  if(updatePanel){
    setInterval(refreshUpdates,2000);
    updatePanel.addEventListener('submit',async function(event){
      const form=event.target;
      if(!(form instanceof HTMLFormElement))return;
      event.preventDefault();
      const confirmation=form.querySelector('[data-update-confirm]');
      if(confirmation&&!window.confirm(confirmation.dataset.updateConfirm))return;
      const body=new FormData(form);
      form.querySelectorAll('button').forEach(button=>button.disabled=true);
      const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),25000);
      try{
        const response=await fetch(form.getAttribute('action'),{method:'POST',body,signal:controller.signal,headers:{'Accept':'application/json'}});
        if(!response.ok)throw new Error('Update action unavailable');
        const notice=await response.json();
        updateKey=null;
        showUpdate(await updateRequest('/api/updates'));
        const result=updatePanel.querySelector('[data-update-action-result]');
        if(result){result.textContent=notice.message;result.className='notice '+(notice.type==='error'?'error':'success');result.hidden=false;}
      }catch(error){updateError();form.querySelectorAll('button').forEach(button=>button.disabled=false);}
      finally{clearTimeout(timeout);}
    });
  }
}
