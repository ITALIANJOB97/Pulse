const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const context={module:{exports:{}}};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/assets/seasonal.js'),'utf8'),context);
const {activeSeason,mottoVisible,flightPlan,christmasFlightPlan,christmasVisitPlan,artwork,create}=context.module.exports;
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
  set innerHTML(html){this.html=html;this.bats=Array.from({length:(html.match(/season-(?:bat|roamer)"/g)||[]).length},()=>new Element());for(const m of html.matchAll(/data-christmas-visitor="([a-z-]+)"/g)){const visitor=new Element();visitor.dataset.christmasVisitor=m[1];this.bats.push(visitor);}}
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

for(const [key,roamers,particles] of [['christmas',1,32],['easter',4,10],['carnival',3,24]]){
 const html=artwork(key);assert.equal((html.match(/season-roamer"/g)||[]).length,roamers);
 assert.equal((html.match(/season-fall/g)||[]).length,particles);
 const start=animations.length,r=new Element();
 const moving=roamers+(key==='christmas'?2:0);
 const d={effects:true,server_time:150,seasonal:{enabled:true,windows:[{key,starts_at:100,ends_at:200}]}};
 const c=create(r,d);assert.equal(animations.length,start+moving);
 c.update(d);c.tick();assert.equal(animations.length,start+moving);
 c.setPaused(true);assert(animations.slice(start).every(a=>a.playState==='paused'));
 c.setPaused(false);assert(animations.slice(start).every(a=>a.playState==='running'));
 c.update({...d,effects:false});assert(animations.slice(start).every(a=>a.cancelled));
 c.update(d);assert.equal(animations.length,start+2*moving);
 media.matches=true;media.change();assert(animations.slice(start).every(a=>a.cancelled));
 media.matches=false;media.change();c.update({...d,server_time:200});assert(r.child.hidden);
 c.destroy();assert(r.child.removed);
}
console.log('Christmas, Easter, Carnival: bounded particles, moving routes, polling, pause, reduced motion, expiry and cleanup passed.');

// The larger Santa must stay out of the central reading area, travel in both
// directions, face forward and enter/leave completely outside the viewport.
const santaDirections=new Set();
for(let i=0;i<100;i++){
 const p=christmasFlightPlan(random);
 assert(p.duration>=26000&&p.duration<=34000);
 const points=p.frames.map(f=>f.transform.match(/translate3d\(([-\d.]+)px,([-\d.]+)px,0\).*scaleX\((-?1)\)/).slice(1).map(Number));
 const direction=points[0][2];santaDirections.add(direction);
 assert.equal(p.frames[0].opacity,0);assert.equal(p.frames.at(-1).opacity,0);
 assert(points.every(([x,y])=>Number.isFinite(x)&&y>=816&&y<=869));
 assert(points[0][0]+320<0||points[0][0]>1920);
 assert(points.at(-1)[0]+320<0||points.at(-1)[0]>1920);
 for(let j=1;j<points.length;j++)assert((points[j][0]-points[j-1][0])*direction>0);
}
assert.equal(santaDirections.size,2);
const christmas=artwork('christmas');
const assets=[...new Set([...christmas.matchAll(/src="(\/assets\/christmas\/[^\"]+)"/g)].map(m=>m[1]))];
assert.equal(assets.length,5);
for(const asset of assets)assert(fs.existsSync(path.join(__dirname,'../public',asset)),'Missing bundled ornament '+asset);
console.log('Christmas artwork: 100 safe sleigh routes, both directions, correct facing, and all 5 local images passed.');

for(const kind of ['reindeer','nutcracker']){
 const behaviours=new Set(),directions=new Set(),visits=new Set();
 for(let i=0;i<120;i++){
  const p=christmasVisitPlan(kind,random),points=p.frames.map(f=>f.transform.match(/translate3d\(([-\d.]+)px,([-\d.]+)px,0\).*scaleX\((-?1)\)/).slice(1).map(Number));
  assert(p.delay>=5000&&p.delay<=15000);assert(p.duration>=10000&&p.duration<=29000);
  assert.equal(p.frames[0].opacity,0);assert.equal(p.frames.at(-1).opacity,0);
  assert(points.every(([x,y])=>Number.isFinite(x)&&y>=180&&y<=850));
  assert(points[0][0]+120<0||points[0][0]>1920);
  assert(points.at(-1)[0]+120<0||points.at(-1)[0]>1920);
  if(p.behaviour!=='peek')assert(points.every(([,y])=>y>=780),'Marches/hops stay below the reading centre');
  for(let j=1;j<p.frames.length;j++)assert(p.frames[j].offset>p.frames[j-1].offset);
  behaviours.add(p.behaviour);directions.add(points[0][2]);visits.add(JSON.stringify(p.frames));
 }
 assert.equal(behaviours.size,2);assert.equal(directions.size,2);assert(visits.size>100);
}
const visitorRoot=new Element(),visitorData={effects:true,server_time:150,seasonal:{enabled:true,windows:[{key:'christmas',starts_at:100,ends_at:200}]}};
const visitorController=create(visitorRoot,visitorData);
const initial=animations.slice(-3),beforeRenew=animations.length;
initial[1].onfinish();
assert(initial[1].cancelled,'Finished animations must be released before a new visit');
assert.equal(animations.length,beforeRenew+1);
assert(animations.at(-1).options.delay>=5000,'Visits must have a genuine hidden interval');
visitorController.update(visitorData);visitorController.tick();assert.equal(animations.length,beforeRenew+1,'Polling cannot duplicate visitors');
visitorController.setPaused(true);assert.equal(animations.at(-1).playState,'paused','Waiting for the next visit pauses too');
visitorController.setPaused(false);assert.equal(animations.at(-1).playState,'running');
visitorController.update({...visitorData,archive:true});assert(animations.at(-1).cancelled);
visitorController.destroy();
console.log('Random visitors: 240 plans, both sides, peeking/hopping/marching, hidden intervals, pause during delay, polling and cleanup passed.');
