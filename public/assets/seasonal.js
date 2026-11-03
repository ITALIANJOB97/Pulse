/* Inline seasonal vectors; compositor transforms, with no per-frame JS loop. */
(function(global){
  'use strict';
  function activeSeason(state, now){
    if(!state || state.enabled===false || !Array.isArray(state.windows))return '';
    const found=state.windows.find(function(w){return ['halloween','christmas','easter','carnival'].includes(w.key)&&Number.isFinite(w.starts_at)&&Number.isFinite(w.ends_at)&&now>=w.starts_at&&now<w.ends_at;});
    return found?found.key:'';
  }
  function mottoVisible(data,now){
    const item=(data.content||{}).motto||{},text=item.text||{};
    const hasText=typeof text==='string'?text.trim():['de','en','fr'].some(function(lang){return typeof text[lang]==='string'&&text[lang].trim();});
    return Boolean(hasText)&&(data.archive||!Number.isFinite(data.motto_ends_at)||now<data.motto_ends_at);
  }
  const drawings={
    bunny:'<ellipse cx="37" cy="26" rx="10" ry="25" fill="#fff7e8" transform="rotate(-12 37 26)"/><ellipse cx="65" cy="25" rx="10" ry="25" fill="#fff7e8" transform="rotate(12 65 25)"/><path d="M37 10L40 36M65 10L61 36" stroke="#f3a6ba" stroke-width="6" stroke-linecap="round"/><ellipse cx="50" cy="78" rx="30" ry="22" fill="#fff7e8"/><circle cx="51" cy="53" r="27" fill="#fffaf0"/><circle cx="42" cy="49" r="3" fill="#284b6b"/><circle cx="61" cy="49" r="3" fill="#284b6b"/><path d="M47 59L56 59L51 65Z" fill="#ea8ca9"/><path d="M51 65Q42 74 39 65M51 65Q60 74 63 65" stroke="#a67580" fill="none" stroke-width="2"/><ellipse cx="29" cy="91" rx="13" ry="7" fill="#f2dfe6"/><ellipse cx="73" cy="91" rx="13" ry="7" fill="#f2dfe6"/>',
    sleigh:'<path d="M10 62Q36 78 70 60L63 81H24Z" fill="#ee5270"/><path d="M9 85Q44 99 88 78M24 78L22 88M61 77L65 85" stroke="#ffdf87" stroke-width="4" fill="none"/><path d="M56 30Q63 13 73 27L83 24L78 35L67 38L64 52" fill="#b48968"/><path d="M72 25L74 10M74 19L85 13" stroke="#ffd79b" stroke-width="3" fill="none"/><path d="M62 48L88 52L91 62M68 51L68 62M82 52L77 65" stroke="#c79a73" stroke-width="5" fill="none" stroke-linecap="round"/><path d="M60 43L34 55" stroke="#ffdf87" fill="none" stroke-width="2"/><rect x="17" y="49" width="17" height="16" rx="3" fill="#7bd9bb"/><path d="M25 49V65M17 55H34" stroke="#fff1ae" stroke-width="3"/><circle cx="42" cy="41" r="11" fill="#ffd6bb"/><path d="M30 36Q34 8 53 32L62 30" stroke="#ed5273" fill="#ed5273" stroke-width="5"/><circle cx="61" cy="30" r="5" fill="#fff"/><path d="M32 42Q42 65 52 42" fill="#fff"/><path d="M30 61Q42 53 53 63" stroke="#ed5273" stroke-width="10"/>',
    mask:'<path d="M16 36Q3 14 15 5Q38 11 38 37M61 35Q63 5 82 5Q94 26 79 40" fill="#e871b5"/><path d="M24 37Q29 7 45 12L51 37M54 37Q43 10 59 5Q73 19 67 39" fill="#69d8d6"/><path d="M5 38Q27 21 49 39Q73 21 95 38L87 68Q66 84 50 66Q31 85 12 67Z" fill="#a991ef" stroke="#ffe598" stroke-width="3"/><path d="M17 48Q29 40 41 51Q29 65 17 48M59 51Q71 40 83 48Q71 65 59 51" fill="#00559d" stroke="#ffe598" stroke-width="3"/><path d="M50 38L55 61L50 68L45 61Z" fill="#ffe598"/><circle cx="12" cy="39" r="3" fill="#fff"/><circle cx="89" cy="39" r="3" fill="#fff"/>',
    ribbon:'<path d="M26 0C85 12 15 28 62 46S12 70 65 88S31 97 49 100" fill="none" stroke="#f3d768" stroke-width="9"/><path d="M68 0C8 16 82 32 34 48S86 74 36 92" fill="none" stroke="#ee86bf" stroke-width="7"/>',
    petal:'<path d="M18 74Q8 12 80 20Q94 83 18 74" fill="#f7c8e5"/><path d="M22 69L73 27" stroke="#fff0d5" stroke-width="3"/>',
    gift:'<rect x="14" y="34" width="73" height="57" rx="5" fill="#ed698b"/><path d="M12 32H89V46H12Z" fill="#f5809e"/><path d="M44 32H57V91H44Z" fill="#ffe18b"/><path d="M50 32C8 27 20 -1 41 17L50 32C93 26 79 -2 59 17Z" fill="none" stroke="#ffe18b" stroke-width="7"/>',
    pumpkin:'<path d="M48 25Q44 12 57 7L61 14Q50 17 56 27" fill="#83d79b"/><ellipse cx="50" cy="58" rx="45" ry="34" fill="#ffab38"/><ellipse cx="50" cy="58" rx="29" ry="34" fill="#ff8330"/><ellipse cx="50" cy="58" rx="13" ry="34" fill="#ffb449"/><path d="M24 53L36 40L40 55ZM60 55L64 40L77 53ZM26 66L37 73L45 67L52 75L60 67L71 69Q52 92 26 66" fill="#162d55"/>',
    ghost:'<path d="M17 86V40a33 33 0 0166 0v46l-16-10-17 11-17-11z" fill="#edf7ff" stroke="#c7ddff" stroke-width="2"/><ellipse cx="38" cy="43" rx="5" ry="8" fill="#183957"/><ellipse cx="62" cy="43" rx="5" ry="8" fill="#183957"/><ellipse cx="50" cy="63" rx="6" ry="8" fill="#416185"/><ellipse cx="28" cy="57" rx="6" ry="3" fill="#f6a6c5"/><ellipse cx="72" cy="57" rx="6" ry="3" fill="#f6a6c5"/>',
    bat:'<path class="bat-wing left" d="M48 50Q30 15 2 31Q18 35 12 58Q27 45 34 70L49 62" fill="#201f46" stroke="#c9b7ff" stroke-width="2"/><path class="bat-wing right" d="M52 50Q70 15 98 31Q82 35 88 58Q73 45 66 70L51 62" fill="#201f46" stroke="#c9b7ff" stroke-width="2"/><path d="M41 46L39 24L49 35L61 24L59 46Q69 72 50 78Q31 72 41 46" fill="#30234e"/><circle cx="45" cy="50" r="3" fill="#ffbf4d"/><circle cx="55" cy="50" r="3" fill="#ffbf4d"/>',
    web:'<g fill="none" stroke="#dbebff" stroke-width="1.4" stroke-linecap="round"><path d="M0 0L100 0M0 0L95 31M0 0L79 63M0 0L48 88M0 0L0 100"/><path d="M20 0Q15 3 19 6Q14 7 16 13Q10 13 10 18Q3 17 0 20M40 0Q31 6 38 12Q28 16 32 25Q21 26 19 35Q6 34 0 40M60 0Q47 9 57 19Q42 23 47 38Q31 38 29 53Q9 51 0 60M80 0Q62 12 76 25Q56 31 63 50Q41 51 38 70Q12 68 0 80M100 0Q78 15 95 31Q70 39 79 63Q52 64 48 88Q15 85 0 100"/></g>',
    spider:'<g fill="none" stroke="#d9caff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M40 42L25 28L18 8M39 49L18 43L7 25M39 57L17 63L8 83M41 65L28 79L25 96M60 42L75 28L82 8M61 49L82 43L93 25M61 57L83 63L92 83M59 65L72 79L75 96"/></g><ellipse cx="50" cy="60" rx="20" ry="25" fill="#201f46" stroke="#bfa5ec" stroke-width="2"/><circle cx="50" cy="35" r="12" fill="#30234e"/><circle cx="45" cy="33" r="2.5" fill="#ffbf4d"/><circle cx="55" cy="33" r="2.5" fill="#ffbf4d"/>',
    snow:'<path d="M50 8V92M14 29L86 71M14 71L86 29M40 17L50 27L60 17M40 83L50 73L60 83M17 42L31 39L28 25M72 75L69 61L83 58M17 58L31 61L28 75M72 25L69 39L83 42" fill="none" stroke="#e5f5ff" stroke-width="5" stroke-linecap="round"/>',
    bauble:'<path d="M50 0V24" stroke="#f4d779" stroke-width="2"/><rect x="40" y="23" width="20" height="13" rx="3" fill="#f5da85"/><circle cx="50" cy="64" r="30" fill="#ed6581"/><path d="M22 57Q50 74 78 57M26 78Q50 65 74 78" fill="none" stroke="#fff0d4" stroke-width="5"/><ellipse cx="37" cy="49" rx="6" ry="9" fill="#fff" opacity=".5"/>',
    tree:'<path d="M44 79H56V98H44" fill="#eab884"/><path d="M50 15L21 46H33L12 69H28L5 90H95L72 69H88L67 46H79Z" fill="#6cdec2"/><path d="M50 15L50 90H95L72 69H88L67 46H79Z" fill="#39b9a2"/><path d="M50 0L54 10L65 10L56 17L59 28L50 21L41 28L44 17L35 10L46 10Z" fill="#ffe18b"/><path d="M31 47Q50 58 70 46M21 70Q50 81 79 70" fill="none" stroke="#fff0ba" stroke-width="3"/><circle cx="42" cy="60" r="4" fill="#ff7189"/><circle cx="63" cy="82" r="4" fill="#ff7189"/>',
    egg:'<path d="M18 61C18 39 35 7 50 7S82 39 82 61a32 32 0 01-64 0" fill="#f7c2e4"/><path d="M23 41L34 48L45 41L56 48L67 41L77 48M21 69L32 62L43 69L54 62L65 69L79 62" fill="none" stroke="#9d6cdb" stroke-width="6"/><circle cx="43" cy="25" r="4" fill="#fff7bf"/><circle cx="56" cy="82" r="4" fill="#fff7bf"/>',
    flower:'<path d="M49 55Q73 77 54 98" fill="none" stroke="#8ce2bc" stroke-width="5"/><path d="M58 81Q78 61 84 81Q75 96 58 88" fill="#92e6ba"/><g fill="#ffd4eb"><ellipse cx="50" cy="26" rx="12" ry="19"/><ellipse cx="30" cy="43" rx="19" ry="12" transform="rotate(20 30 43)"/><ellipse cx="70" cy="43" rx="19" ry="12" transform="rotate(-20 70 43)"/><ellipse cx="38" cy="60" rx="12" ry="18" transform="rotate(25 38 60)"/><ellipse cx="62" cy="60" rx="12" ry="18" transform="rotate(-25 62 60)"/></g><circle cx="50" cy="44" r="12" fill="#ffda78"/>',
    butterfly:'<path class="bat-wing left" d="M49 51C5 1 0 63 40 61C4 86 40 99 49 60" fill="#ffd57e"/><path class="bat-wing right" d="M51 51C95 1 100 63 60 61C96 86 60 99 51 60" fill="#f6a3d4"/><path d="M50 38V76M50 42L40 30M50 42L60 30" stroke="#fff3cd" stroke-width="4" fill="none" stroke-linecap="round"/>'
  };
  function icon(name,cls,style){return '<span class="season-piece '+cls+'" style="'+style+'"><svg viewBox="0 0 100 100" focusable="false" aria-hidden="true">'+drawings[name]+'</svg></span>';}
  function artwork(key){
    let html='';
    if(key==='halloween'){
      html+=icon('pumpkin','season-corner','left:3px;bottom:91px;--size:67px');
      html+=icon('pumpkin','season-corner','right:5px;bottom:91px;--size:59px');
      html+=icon('ghost','season-bob','left:6px;top:260px;--size:55px;--delay:-2s');
      html+=icon('ghost','season-bob','right:6px;top:630px;--size:53px;--delay:-5s');
      html+=icon('web','season-web','left:0;top:0;--size:148px');
      html+=icon('web','season-web','right:0;top:0;--size:148px;transform:scaleX(-1)');
      html+=icon('web','season-web','left:0;bottom:0;--size:112px;transform:scaleY(-1)');
      html+=icon('web','season-web','right:0;bottom:0;--size:112px;transform:scale(-1)');
      for(let i=0;i<4;i++)html+=icon('bat','season-bat','--size:'+(56+i*5)+'px;--rest-x:'+(340+i*410)+'px');
      [28,1460,1850].forEach(function(x,i){
        html+='<span class="season-spider-rig" style="left:'+x+'px;--size:'+(i===1?34:42)+'px;--drop:'+(i===1?220:370+i*110)+'px;--duration:'+(20+i*4)+'s;--delay:'+(-i*7)+'s"><span class="season-thread"></span>'+icon('spider','season-spider-body','')+'</span>';
      });
    }else if(key==='christmas'){
      html+=icon('tree','season-corner','left:0;bottom:87px;--size:72px');
      html+=icon('tree','season-corner','right:0;bottom:87px;--size:72px');
      html+=icon('gift','season-corner','left:6px;top:510px;--size:50px');
      html+=icon('gift','season-corner','right:5px;top:690px;--size:49px');
      let lights='<svg class="season-lights" viewBox="0 0 1920 42" aria-hidden="true"><path d="M0 0';
      for(let x=0;x<1920;x+=240)lights+='Q'+(x+120)+' 54 '+(x+240)+' 0';
      lights+='" fill="none" stroke="#7cd7bc" stroke-width="3"/>';
      for(let i=0;i<16;i++)lights+='<circle class="season-light" cx="'+(60+i*120)+'" cy="20" r="6" fill="'+(['#ffe49c','#ff8aa6','#c0fff0'][i%3])+'" style="--delay:'+(-i*.4)+'s"/>';
      html+=lights+'</svg>';
      for(let i=0;i<4;i++)html+=icon('bauble','season-sway','left:'+(400+i*360)+'px;top:-8px;--size:43px;--delay:'+(-i*2)+'s');
      for(let i=0;i<18;i++)html+=icon('snow','season-fall season-snow','left:'+(35+i*104)+'px;top:-32px;--size:'+(12+i%3*5)+'px;--duration:'+(16+i%4*3)+'s;--drift:'+(-55+i%5*27)+'px;--delay:'+(-i*2.7)+'s');
      html+=icon('sleigh','season-roamer','--size:106px;--rest-x:1630px');
    }else if(key==='easter'){
      html+=icon('bunny','season-bunny','left:0;bottom:88px;--size:68px');
      html+=icon('bunny','season-bunny','right:0;bottom:88px;--size:68px;--delay:-4s');
      html+=icon('flower','season-sway','left:1px;top:170px;--size:54px');
      html+=icon('flower','season-sway','right:1px;top:170px;--size:54px;--delay:-3s');
      for(let i=0;i<4;i++)html+=icon('egg','season-bob',(i%2?'right:':'left:')+'8px;top:'+(350+Math.floor(i/2)*280)+'px;--size:42px;--delay:'+(-i*2)+'s');
      for(let i=0;i<4;i++)html+=icon('butterfly','season-roamer','--size:'+(44+i*4)+'px;--rest-x:'+(330+i*390)+'px');
      for(let i=0;i<10;i++)html+=icon('petal','season-fall season-petal','left:'+(80+i*183)+'px;top:-40px;--size:'+(12+i%3*3)+'px;--duration:'+(22+i%4*3)+'s;--drift:'+(-90+i%4*60)+'px;--delay:'+(-i*3.1)+'s');
    }else if(key==='carnival'){
      for(let i=0;i<4;i++)html+=icon('ribbon','season-sway',(i%2?'right:':'left:')+'1px;top:'+(180+Math.floor(i/2)*530)+'px;--size:60px;--delay:'+(-i*2)+'s');
      for(let i=0;i<3;i++)html+=icon('mask','season-roamer','--size:'+(61+i*4)+'px;--rest-x:'+(470+i*470)+'px');
      for(let i=0;i<24;i++)html+='<span class="season-confetti season-fall" style="left:'+(20+i*79)+'px;top:-30px;--size:8px;--drift:'+(-85+i%6*34)+'px;--duration:'+(16+i%5*3)+'s;--delay:'+(-i*1.7)+'s;background:'+(['#ffe79b','#ef8cc8','#8ce8d5','#c8b6ff'][i%4])+'"></span>';
    }
    return html;
  }
  // A new smooth route is sampled once per flight, then the browser animates
  // transforms on the compositor. Coordinates match the TV's 1920x1080 canvas.
  function flightPlan(random,width,height){
    random=random||Math.random;width=width||1920;height=height||1080;
    const margin=100,side=Math.floor(random()*4);
    function edge(which){
      const along=.08+random()*.84;
      return which===0?{x:-margin,y:height*along}:which===1?{x:width*along,y:-margin}:which===2?{x:width+margin,y:height*along}:{x:width*along,y:height+margin};
    }
    const start=edge(side),end=edge((side+2)%4),upper=random()<.5;
    const points=[start,{x:width*(.18+random()*.64),y:height*((upper?.12:.68)+random()*.18)},
      {x:width*(.18+random()*.64),y:height*((upper?.68:.12)+random()*.18)},end];
    const samples=[];
    function spline(a,b,c,d,t){return .5*((2*b)+(-a+c)*t+(2*a-5*b+4*c-d)*t*t+(-a+3*b-3*c+d)*t*t*t);}
    for(let segment=0;segment<3;segment++){
      const a=points[Math.max(0,segment-1)],b=points[segment],c=points[segment+1],d=points[Math.min(3,segment+2)];
      for(let step=segment===0?0:1;step<=20;step++){
        const t=step/20;
        samples.push({x:spline(a.x,b.x,c.x,d.x,t),y:spline(a.y,b.y,c.y,d.y,t)});
      }
    }
    let distance=0;
    samples.forEach(function(point,i){if(i)distance+=Math.hypot(point.x-samples[i-1].x,point.y-samples[i-1].y);point.distance=distance;});
    const frames=samples.map(function(point,i){
      const before=samples[Math.max(0,i-1)],after=samples[Math.min(samples.length-1,i+1)];
      const bank=Math.atan2(after.y-before.y,Math.abs(after.x-before.x))*.24*180/Math.PI;
      const offset=point.distance/distance;
      return {offset:offset,transform:'translate3d('+point.x.toFixed(2)+'px,'+point.y.toFixed(2)+'px,0) rotate('+bank.toFixed(2)+'deg)',opacity:Math.min(.9,offset*18,(1-offset)*18)};
    });
    return {frames:frames,duration:Math.max(22000,Math.min(42000,distance/(105+random()*30)*1000))};
  }
  function create(root,initial){
    const layer=document.createElement('div');layer.className='seasonal-layer';layer.setAttribute('aria-hidden','true');root.appendChild(layer);
    const reduced=typeof global.matchMedia==='function'?global.matchMedia('(prefers-reduced-motion: reduce)'):null;
    let state={},data={},received=Date.now(),server=received/1000,key='',paused=Boolean(document.hidden),still=false,destroyed=false,flights=[];
    function now(){return server+(Date.now()-received)/1000;}
    function stopFlights(){flights.forEach(function(item){item.animation.onfinish=null;item.animation.cancel();});flights=[];}
    function launch(item,delay){
      const plan=flightPlan();
      item.animation=item.element.animate(plan.frames,{duration:plan.duration*(key==='christmas'?1.7:key==='halloween'?1:1.2),delay:delay,fill:'both',easing:'linear'});
      item.animation.onfinish=function(){
        if(!destroyed&&key&&!still&&flights.includes(item))launch(item,Math.random()*1800);
      };
      if(paused)item.animation.pause();
    }
    function tick(){
      if(destroyed)return;
      const next=activeSeason(state,now());
      if(next!==key){stopFlights();key=next;layer.innerHTML=artwork(key);layer.dataset.season=key;}
      still=data.effects===false||Boolean(reduced&&reduced.matches);
      layer.hidden=!key;layer.classList.toggle('season-still',still);layer.classList.toggle('season-paused',paused);
      if(!key||still){stopFlights();return;}
      if(!flights.length){
        layer.querySelectorAll('.season-bat,.season-roamer').forEach(function(element,i){
          if(typeof element.animate!=='function')return;
          const item={element:element,animation:null};flights.push(item);launch(item,i*1800);
        });
      }
      flights.forEach(function(item){
        if(paused&&item.animation.playState!=='paused')item.animation.pause();
        else if(!paused&&item.animation.playState==='paused')item.animation.play();
      });
    }
    function spiderCycle(event){
      if(event.animationName!=='season-spider-drop'||still||paused||key!=='halloween')return;
      const rig=event.target.parentElement;
      if(!rig||!rig.classList.contains('season-spider-rig'))return;
      const central=rig.offsetLeft>300&&rig.offsetLeft<1700;
      rig.style.setProperty('--drop',Math.round((central?130:240)+Math.random()*(central?160:510))+'px');
    }
    function update(fresh){data=fresh;state=fresh.archive?{}:(fresh.seasonal||{});received=Date.now();server=Number.isFinite(fresh.server_time)?fresh.server_time:received/1000;tick();}
    layer.addEventListener('animationiteration',spiderCycle);
    if(reduced&&reduced.addEventListener)reduced.addEventListener('change',tick);
    update(initial);
    return {update:update,tick:tick,now:now,setPaused:function(value){paused=value;tick();},destroy:function(){
      destroyed=true;stopFlights();layer.removeEventListener('animationiteration',spiderCycle);
      if(reduced&&reduced.removeEventListener)reduced.removeEventListener('change',tick);layer.remove();
    }};
  }
  const api={activeSeason:activeSeason,mottoVisible:mottoVisible,flightPlan:flightPlan,artwork:artwork,create:create};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;else global.PulseSeasonal=api;
})(typeof window!=='undefined'?window:globalThis);
