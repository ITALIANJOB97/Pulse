'use strict';
(function(global){
  function visible(data,now){
    if(data.archive)return [];
    return (Array.isArray(data.countdowns)?data.countdowns:[]).filter(function(event){
      return ['gta','doomsday'].includes(event.id)&&event.enabled!==false&&Number.isFinite(event.ends_at)&&now<event.ends_at;
    });
  }
  function parts(end,now){
    let seconds=Math.max(0,Math.ceil(end-now));
    return [Math.floor(seconds/86400),Math.floor(seconds%86400/3600),Math.floor(seconds%3600/60),seconds%60];
  }
  function signature(data,now){return visible(data,now).map(function(e){return e.id;}).join(',');}
  function createBackgroundSelector(random){
    random=random||Math.random;const states={};
    return function(event){
      const images=(event.images||[]).filter(function(path){return /^\/assets\/countdowns\/(gta|doomsday)-\d{2}\.jpg$/.test(path);});
      if(!images.length)return '';
      const fingerprint=images.join('|');let state=states[event.id];
      if(!state||state.fingerprint!==fingerprint)state=states[event.id]={fingerprint:fingerprint,bag:[],last:''};
      if(!state.bag.length){
        state.bag=images.slice();
        for(let i=state.bag.length-1;i>0;i--){const j=Math.floor(random()*(i+1));[state.bag[i],state.bag[j]]=[state.bag[j],state.bag[i]];}
        if(state.bag.length>1&&state.bag[state.bag.length-1]===state.last)[state.bag[0],state.bag[state.bag.length-1]]=[state.bag[state.bag.length-1],state.bag[0]];
      }
      state.last=state.bag.pop();return state.last;
    };
  }
  const api={visible:visible,parts:parts,signature:signature,createBackgroundSelector:createBackgroundSelector};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;else global.PulseCountdowns=api;
})(typeof window!=='undefined'?window:globalThis);
