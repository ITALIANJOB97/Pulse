const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const context={module:{exports:{}}};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/assets/seasonal.js'),'utf8'),context);
const {activeSeason,mottoVisible,flightPlan,artwork,create}=context.module.exports;
const state={enabled:true,windows:[{key:'halloween',starts_at:100,ends_at:200}]};
assert.equal(activeSeason(state,99),'');assert.equal(activeSeason(state,100),'halloween');assert.equal(activeSeason(state,199.999),'halloween');assert.equal(activeSeason(state,200),'');
assert.equal(activeSeason({...state,enabled:false},150),'');assert.equal(activeSeason({},150),'');
const data={motto_ends_at:200,content:{motto:{text:{de:'Gemeinsam.',en:''}}}};
assert.equal(mottoVisible(data,100),true);assert.equal(mottoVisible(data,200),false);assert.equal(mottoVisible({...data,archive:true},201),true);
assert.equal(mottoVisible({content:{motto:{text:{de:'  '}}}},100),false);
assert.equal(mottoVisible({content:{}},100),false);
console.log('11 seasonal browser logic checks passed: boundaries, disabled state, missing legacy data, weekly expiry and archive.');

// Routes must cover upper/lower content, enter from every edge and vary on each flight.
let seed=73;const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
const routes=new Set(),edges=new Set();
for(let i=0;i<160;i++){
  const plan=flightPlan(random),frames=plan.frames;
  assert(plan.duration>=22000&&plan.duration<=42000);
  assert.equal(frames[0].offset,0);assert.equal(frames.at(-1).offset,1);
  const points=frames.map(f=>f.transform.match(/translate3d\(([-\d.]+)px,([-\d.]+)px,0\)/).slice(1).map(Number));
  assert(points.every(p=>p.every(Number.isFinite)));
  assert(Math.min(...points.map(p=>p[1]))<350,'A flight must reach the upper portion');
  assert(Math.max(...points.map(p=>p[1]))>730,'A flight must reach the lower portion');
  assert.equal(frames[0].opacity,0);assert.equal(frames.at(-1).opacity,0);
  for(let j=1;j<frames.length;j++)assert(frames[j].offset>frames[j-1].offset);
  const [x,y]=points[0];edges.add(x<0?'left':x>1920?'right':y<0?'top':'bottom');
  assert(points.at(-1)[0]<0||points.at(-1)[0]>1920||points.at(-1)[1]<0||points.at(-1)[1]>1080);
  routes.add(frames.map(f=>f.transform).join('|'));
}
assert.equal(routes.size,160);assert.equal(edges.size,4);
const halloween=artwork('halloween');
assert.equal((halloween.match(/season-bat"/g)||[]).length,4);
assert.equal((halloween.match(/season-web"/g)||[]).length,4);
assert.equal((halloween.match(/season-spider-rig"/g)||[]).length,3);
assert(!artwork('christmas').includes('season-spider'));assert.equal(artwork(''), '');

// Exercise the lifecycle with controlled browser primitives, no real timers.
const animations=[];
class Element{
  constructor(){this.dataset={};this.listeners={};this.classes=new Set();this.classList={toggle:(key,on)=>on?this.classes.add(key):this.classes.delete(key)};}
  set innerHTML(html){this.html=html;this.bats=Array.from({length:(html.match(/season-bat"/g)||[]).length},()=>new Element());}
  querySelectorAll(){return this.bats||[];}
  setAttribute(){} appendChild(child){this.child=child;}
  addEventListener(name,fn){this.listeners[name]=fn;} removeEventListener(name){delete this.listeners[name];} remove(){this.removed=true;}
  animate(frames,options){const a={frames,options,playState:'running',pause(){this.playState='paused';},play(){this.playState='running';},cancel(){this.playState='idle';this.cancelled=true;}};animations.push(a);return a;}
}
const media={matches:false,addEventListener(name,fn){this.change=fn;},removeEventListener(){this.change=null;}};
context.document={hidden:false,createElement:()=>new Element()};context.matchMedia=()=>media;
const root=new Element(),fresh={effects:true,server_time:150,seasonal:state};
const controller=create(root,fresh);assert.equal(animations.length,4);
controller.update(fresh);controller.tick();assert.equal(animations.length,4,'Polling must not restart or accumulate flights');
controller.setPaused(true);assert(animations.every(a=>a.playState==='paused'));
controller.setPaused(false);assert(animations.every(a=>a.playState==='running'));
const oldRoute=animations[0].frames;animations[0].onfinish();assert.equal(animations.length,5);assert.notDeepEqual(animations[4].frames,oldRoute);
media.matches=true;media.change();assert(root.child.classes.has('season-still'));assert(animations[4].cancelled);
let count=animations.length;controller.tick();assert.equal(animations.length,count,'Reduced motion cannot start flights');
media.matches=false;media.change();assert.equal(animations.length,count+4);
controller.update({...fresh,effects:false});assert(animations.slice(-4).every(a=>a.cancelled));
controller.update(fresh);assert.equal(animations.length,count+8);
controller.update({...fresh,archive:true});assert(root.child.hidden);assert(animations.slice(-4).every(a=>a.cancelled));
controller.update({...fresh,server_time:201});assert(root.child.hidden,'Season expiry must remove decorations');
controller.destroy();assert(root.child.removed);assert.equal(media.change,null);
console.log('Halloween checks passed: 160 distinct full-screen routes; all entry edges; webs/spiders; repeated flights; pause/resume; reduced motion; effects off; season expiry; archives; cleanup.');
