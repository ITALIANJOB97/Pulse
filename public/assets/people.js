/* Selection logic is independent from rendering and polling. */
(function(global){
  'use strict';
  function awardPeople(content,employees){
    content=content||{};const ids=Array.isArray(content.employee_ids)?content.employee_ids:[content.employee_id];
    return [...new Set(ids.map(Number))].slice(0,2).map(id=>employees.find(p=>Number(p.id)===id)).filter(Boolean);
  }
  function hallPool(members,now){
    const all=Array.isArray(members)?members:[];
    const priority=all.filter(p=>Number.isFinite(p.priority_until)&&now<p.priority_until&&(!Number.isFinite(p.added_at)||now>=p.added_at));
    return priority.length?priority:all;
  }
  function createHallSelector(random){
    random=random||Math.random;let signature='',bag=[],previous=null;
    return function(members,now){
      const pool=hallPool(members,now),next=pool.map(p=>Number(p.id)).sort((a,b)=>a-b).join(',');
      if(next!==signature){bag=[];signature=next;}
      if(!pool.length){previous=null;return null;}
      if(!bag.length){
        bag=pool.map(p=>Number(p.id));
        for(let i=bag.length-1;i>0;i--){const j=Math.floor(random()*(i+1));[bag[i],bag[j]]=[bag[j],bag[i]];}
        if(bag.length>1&&bag[bag.length-1]===previous)[bag[0],bag[bag.length-1]]=[bag[bag.length-1],bag[0]];
      }
      previous=bag.pop();return pool.find(p=>Number(p.id)===previous)||null;
    };
  }
  const api={awardPeople:awardPeople,hallPool:hallPool,createHallSelector:createHallSelector};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;else global.PulsePeople=api;
})(typeof window!=='undefined'?window:globalThis);
