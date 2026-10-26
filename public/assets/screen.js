'use strict';
(function(){
  let data=JSON.parse(document.getElementById('screen-data').textContent);
  const root=document.getElementById('screen'),main=document.getElementById('slide');
  const ticker=document.getElementById('timeline'),pageNav=document.getElementById('pages');
  const offline=document.getElementById('offline');
  const keys=['spotlight','podium','team','roles','transfers','targets','daily','gallery','tasks'];
  let slides=[],index=0,timer=null,fxTimer=null,pollTimer=null,paused=false;
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function label(key){return data.labels[key]||key;}
  function translated(value){if(!value)return '';if(typeof value==='string')return value;return value[data.language]||value.en||value.de||value.fr||'';}
  function person(id){return data.employees.find(function(p){return Number(p.id)===Number(id);});}
  function initials(name){return name.trim().split(/\s+/).slice(0,2).map(function(s){return Array.from(s)[0]||'';}).join('');}
  function photo(p,cls){
    if(!p)return '';
    const image=p.photo?'<img src="/media?id='+Number(p.photo)+'" alt="'+esc(p.name)+'">':'<span class="initials">'+esc(initials(p.name))+'</span>';
    return '<div class="'+cls+'">'+image+'</div>';
  }
  function number(v,digits){return v==null?'—':new Intl.NumberFormat(data.language,{maximumFractionDigits:digits==null?1:digits}).format(v);}
  function serviceNumber(v){return new Intl.NumberFormat(data.language,{maximumSignificantDigits:15}).format(v);}
  function chunks(list,size){const result=[];for(let i=0;i<list.length;i+=size)result.push(list.slice(i,i+size));return result.length?result:[[]];}
  function buildSlides(){
    const out=[];
    keys.forEach(function(key){
      if(data.pages[key]===false)return;
      let groups=[null];
      if(key==='team')groups=[data.employees.slice().sort(function(a,b){const roles=['manager','deputy','supervisor','member'];return roles.indexOf(a.role)-roles.indexOf(b.role);})];
      if(key==='roles')groups=chunks((data.content.roles||[]).filter(function(r){return person(r.employee_id);}),6);
      if(key==='tasks')groups=chunks((data.content.tasks[data.language]||data.content.tasks.en||data.content.tasks.de||data.content.tasks.fr||[]).filter(function(s){return s.trim();}),6);
      if(key==='gallery')groups=data.gallery.length?data.gallery:[null];
      groups.forEach(function(items,i){out.push({key:key,items:items,part:i+1,parts:groups.length});});
    });
    return out.length?out:[{key:'daily',items:null,part:1,parts:1}];
  }
  function empty(){return '<div class="empty"><strong>'+esc(label('ready'))+'</strong><span>'+esc(label('empty'))+'</span></div>';}
  function trend(value,previous){
    if(value==null||previous==null)return '<div class="trend flat">—</div>';
    const delta=Math.round((value-previous)*10)/10;
    return '<div class="trend '+(delta<0?'down':delta===0?'flat':'')+'">'+(delta>0?'↑ ':delta<0?'↓ ':'→ ')+number(Math.abs(delta))+' '+esc(label('points'))+' '+esc(label('previous_month'))+'</div>';
  }
  function metric(title,value,target,extra){
    return '<section class="metric"><h2>'+esc(title)+'</h2><div class="value">'+value+'</div><div class="target">'+esc(label('target'))+' '+target+'</div>'+extra+'</section>';
  }
  function renderBody(slide){
    const key=slide.key,c=data.content[key]||[];
    if(key==='spotlight'){
      const p=person(c.employee_id);if(!p)return empty();
      return '<div class="hero">'+photo(p,'portrait')+'<div><span class="award-tag">'+esc(label('spotlight'))+'</span><h2>'+esc(p.name)+'</h2><p>'+esc(translated(c.message))+'</p><p class="thanks">'+esc(label('thanks'))+'</p></div></div>';
    }
    if(key==='podium'){
      const all=(Array.isArray(c)?c:[]).filter(function(r){return person(r.employee_id)&&r.count>0;}).sort(function(a,b){return b.count-a.count||a.employee_id-b.employee_id;});
      const ranked=all.slice(0,3).map(function(r,i){return Object.assign({},r,{rank:i>0&&all[i-1].count===r.count?(all[0].count===r.count?1:2):i+1});});
      if(!ranked.length)return empty();
      const ordered=ranked.length===3?[ranked[1],ranked[0],ranked[2]]:ranked.length===2?[ranked[1],ranked[0]]:ranked;
      return '<div class="podium"'+(ranked.length===1?' style="grid-template-columns:1fr;max-width:850px;margin:auto"':'')+'>'+ordered.map(function(r){
        const p=person(r.employee_id),position=ranked.indexOf(r)+1;
        const tie=all.some(function(other){return other.employee_id!==r.employee_id&&other.count===r.count;});
        return '<section class="person '+(position===1?'first':position===3?'third':'second')+'">'+photo(p,'avatar')+'<h2>'+esc(p.name)+'</h2><div class="service-total">'+serviceNumber(r.count)+'</div><div class="service-label">'+esc(label('count'))+'</div>'+(tie?'<div class="tie">'+esc(label('tied'))+'</div>':'')+'<div class="step"><span class="rank">'+String(r.rank).padStart(2,'0')+'</span></div></section>';
      }).join('')+'</div>';
    }
    if(key==='team'){
      if(!slide.items.length)return empty();
      function group(role){
        const members=slide.items.filter(function(p){return p.role===role;});
        if(!members.length)return '';
        const columns=role==='member'?(members.length<=8?4:members.length<=24?6:7):(members.length>1?2:1);
        return '<section class="team-group '+(role==='member'?'team-members':'')+'"><h2>'+esc(label(role))+'</h2><div class="team-cards" style="--team-columns:'+columns+'">'+members.map(function(p){
          return '<div class="team-person">'+photo(p,'team-photo')+'<strong>'+esc(p.name)+'</strong></div>';
        }).join('')+'</div></section>';
      }
      const leaders=['manager','deputy','supervisor'].map(group).filter(Boolean);
      return '<div class="team-board">'+(leaders.length?'<div class="team-leadership" style="--leadership-columns:'+leaders.length+'">'+leaders.join('')+'</div>':'')+group('member')+'</div>';
    }
    if(key==='roles'){
      if(!slide.items.length)return empty();
      return '<div class="task-list role-list">'+slide.items.map(function(row){return '<div class="task"><p>'+esc(translated(row.title))+'</p><strong>'+esc(person(row.employee_id).name)+'</strong></div>';}).join('')+'</div>';
    }
    if(key==='transfers'){
      if(!c.days)return empty();
      const days=c.days.slice(0,data.show_sunday===false?6:7);
      const total=days.reduce(function(a,b){return a+b;},0),max=Math.max(1,...days);
      const week=String(data.week).match(/^(\d+)-W(\d+)$/);
      const first=new Date(Date.UTC(Number(week[1]),0,4));
      first.setUTCDate(first.getUTCDate()-(first.getUTCDay()||7)+1+(Number(week[2])-1)*7);
      return '<div class="transfer-summary"><div><p>'+esc(label('completed'))+'</p><div class="value">'+number(total,0)+'</div></div><strong>'+number(c.pending,0)+' · '+esc(label('pending'))+'</strong></div><div class="bars" style="--day-columns:'+days.length+'" role="img" aria-label="'+esc(label('completed')+': '+days.join(', '))+'">'+days.map(function(n,i){
        const d=new Date(first);d.setUTCDate(first.getUTCDate()+i);
        const day=new Intl.DateTimeFormat(data.language,{weekday:'short',timeZone:'UTC'}).format(d);
        return '<div class="bar-column"><strong>'+number(n,0)+'</strong><div class="bar" style="height:'+(n/max*215)+'px"></div><span class="bar-day">'+esc(day)+'</span></div>';
      }).join('')+'</div>';
    }
    if(key==='targets'){
      const seconds=c.waiting;
      const state=seconds==null?null:seconds<=c.waiting_green?'green':seconds<=c.waiting_amber?'amber':'red';
      const signal='<div class="signals">'+['green','amber','red'].map(function(s){return '<span aria-hidden="true" class="signal '+s+(s===state?' active':'')+'"></span>';}).join('')+'<span class="status-label">'+esc(state?label(state):'—')+'</span></div>';
      const time=seconds==null?'—':Math.floor(seconds/60)+'<small>m</small>'+String(Math.round(seconds%60)).padStart(2,'0')+'<small>s</small>';
      return '<div class="metrics">'+metric(label('nps'),number(c.nps),number(c.nps_target),trend(c.nps,c.nps_previous))+
        metric(label('waiting_title'),time,c.waiting_green==null?'—':'≤ '+number(c.waiting_green/60)+' min',signal)+
        metric(label('quality_title'),number(c.quality)+(c.quality==null?'':'<small>%</small>'),number(c.quality_target)+(c.quality_target==null?'':'%'),trend(c.quality,c.quality_previous))+
        metric(label('service_team'),number(c.service_total,0),number(c.service_target,0),'<div class="trend flat">'+(c.service_total!=null&&c.service_target>0?number(c.service_total/c.service_target*100,0)+'% · ':'')+esc(label('team'))+'</div>')+
        '</div><div class="thresholds"><span>'+esc(label('green'))+': ≤ '+number(c.waiting_green)+' s</span><span>'+esc(label('amber'))+': '+number(c.waiting_green)+'–'+number(c.waiting_amber)+' s</span><span>'+esc(label('red'))+': &gt; '+number(c.waiting_amber)+' s</span></div>';
    }
    if(key==='daily'||key==='tasks'){
      const lines=key==='daily'?Array.from({length:6},function(_,i){return translated(c[i]);}):slide.items;
      if(!lines.length)return empty();
      return '<div class="task-list">'+lines.map(function(text,i){return '<div class="task"><span class="num">'+String((slide.part-1)*6+i+1).padStart(2,'0')+'</span><p>'+esc(text)+'</p></div>';}).join('')+'</div>';
    }
    if(key==='gallery'){
      if(!slide.items)return empty();
      return '<figure class="gallery-frame"><div class="gallery-photo"><img src="/media?id='+Number(slide.items.id)+'" alt="'+esc(slide.items.label)+'"></div>'+(slide.items.label?'<figcaption class="photo-label">'+esc(slide.items.label)+'</figcaption>':'')+'</figure>';
    }
    return empty();
  }
  function fitTeam(){
    const board=main.querySelector('.team-board');if(!board)return;
    const available=main.clientHeight-board.offsetTop-parseFloat(getComputedStyle(main).paddingBottom);
    for(let size=28;size>=12;size--){
      board.style.setProperty('--team-font',size+'px');
      if(board.scrollHeight<=available+1)break;
    }
    // Keep every employee on the same page even for unusually large teams.
    if(board.scrollHeight>available){
      const scale=available/board.scrollHeight;
      board.style.transform='scale('+scale+')';
      board.style.transformOrigin='top center';
    }
  }
  function celebrate(key){
    clearTimeout(fxTimer);
    if(!data.effects||reduced.matches||!['spotlight','podium'].includes(key)||main.querySelector('.empty'))return;
    const layer=document.createElement('div');layer.className='fx-layer';layer.setAttribute('aria-hidden','true');
    for(let i=0;i<18;i++){
      const particle=document.createElement('span');particle.className='confetti';
      particle.style.setProperty('--x',(4+(i*37)%92)+'%');
      particle.style.setProperty('--drift',((i%2?1:-1)*(25+(i*17)%90))+'px');
      particle.style.setProperty('--turn',((i%2?1:-1)*(130+i*29))+'deg');
      particle.style.setProperty('--duration',(2.2+(i%5)*.18)+'s');
      particle.style.setProperty('--delay',((key==='podium'?1.7:.6)+(i%6)*.085)+'s');
      layer.appendChild(particle);
    }
    main.appendChild(layer);fxTimer=setTimeout(function(){layer.remove();},5400);
  }
  function show(){
    clearTimeout(timer);clearTimeout(fxTimer);
    if(index>=slides.length)index=0;
    const slide=slides[index],title=slide.key==='spotlight'?'award_title':slide.key==='podium'?'podium_title':slide.key==='targets'?'targets_title':slide.key;
    document.documentElement.lang=data.language;
    document.getElementById('store').textContent=data.store;
    main.dataset.page=slide.key;
    main.classList.toggle('motion',Boolean(data.effects)&&!reduced.matches);
    main.innerHTML='<div class="heading"><div><div class="eyebrow">'+esc(label(slide.key))+'</div><h1>'+esc(label(title))+'</h1></div>'+(slide.parts>1?'<span class="subpage">'+slide.part+' / '+slide.parts+'</span>':'')+'</div>'+renderBody(slide);
    const period=slide.key==='transfers'?data.week:slide.key==='daily'?data.date:data.month;
    document.getElementById('period').textContent=(data.archive?label('archive')+' · ':'')+period;
    document.getElementById('page-count').textContent=String(index+1).padStart(2,'0')+' / '+String(slides.length).padStart(2,'0');
    pageNav.innerHTML=slides.map(function(s,i){return '<button type="button" data-page="'+i+'" aria-label="'+esc(label(s.key))+' '+s.part+'" aria-current="'+(i===index)+'"></button>';}).join('');
    fitTeam();celebrate(slide.key);schedule();clock();
  }
  function schedule(){
    clearTimeout(timer);ticker.style.transition='none';ticker.style.transform='scaleX(0)';
    if(paused)return;
    const duration=Math.min(120,Math.max(10,Number(data.duration)||20))*1000;
    if(!reduced.matches)requestAnimationFrame(function(){requestAnimationFrame(function(){if(!paused){ticker.style.transition='transform '+duration+'ms linear';ticker.style.transform='scaleX(1)';}});});
    timer=setTimeout(function(){index=(index+1)%slides.length;show();},duration);
  }
  function clock(){
    document.getElementById('clock').textContent=new Intl.DateTimeFormat(data.language,{hour:'2-digit',minute:'2-digit',timeZone:data.timezone}).format(new Date());
  }
  async function poll(){
    try{
      const response=await fetch('/api/screen',{cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(10000)});
      if(!response.ok)throw new Error('screen');
      const fresh=await response.json();offline.hidden=true;
      if(fresh.version!==data.version){
        location.reload();return;
      }
      const changed=fresh.revision!==data.revision||fresh.date!==data.date;
      data=fresh;
      if(changed){const previous=slides[index];slides=buildSlides();const next=slides.findIndex(function(s){return s.key===previous.key&&s.part===previous.part;});index=next>=0?next:0;show();}

    }catch(error){offline.textContent=label('offline');offline.hidden=false;}
    pollTimer=setTimeout(poll,30000);
  }
  function fit(){
    const scale=Math.min(innerWidth/1920,innerHeight/1080);
    root.style.transform='scale('+scale+')';root.style.left=((innerWidth-1920*scale)/2)+'px';root.style.top=((innerHeight-1080*scale)/2)+'px';
  }
  pageNav.addEventListener('click',function(event){const button=event.target.closest('[data-page]');if(button){index=Number(button.dataset.page);show();}});
  document.addEventListener('keydown',function(event){
    if(event.key==='ArrowRight'){index=(index+1)%slides.length;show();}
    if(event.key==='ArrowLeft'){index=(index-1+slides.length)%slides.length;show();}
    if(event.code==='Space'){event.preventDefault();paused=!paused;schedule();}
  });
  document.addEventListener('visibilitychange',function(){if(document.hidden){clearTimeout(timer);clearTimeout(pollTimer);}else{schedule();if(!data.archive)poll();}});
  addEventListener('resize',fit);reduced.addEventListener('change',show);
  slides=buildSlides();fit();show();setInterval(clock,30000);
  if(!data.archive)pollTimer=setTimeout(poll,30000);
})();
