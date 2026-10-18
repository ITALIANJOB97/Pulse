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
