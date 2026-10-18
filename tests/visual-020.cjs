'use strict';
// Asset regression checks use synthetic data only. No deployed server or database is touched.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),os=require('node:os');
const {chromium}=require(process.env.PULSE_PLAYWRIGHT||'playwright');
const root=path.resolve(__dirname,'..'),assets=path.join(root,'public','assets');
const labels=JSON.parse(fs.readFileSync(path.join(root,'app','locales.json'),'utf8'));
const output=process.env.PULSE_SCREENSHOTS||fs.mkdtempSync(path.join(os.tmpdir(),'pulse-visual-020-'));
const employees=Array.from({length:30},(_,i)=>({id:i+1,name:i===0?'Alexandra Müller-Meier':i===1?'Jean-François Dupont':i===2?'Maximilian von Hohenberg':i===20?'Anne-Sophie Marie-Christine de La Roche':'Team Member '+String(i+1).padStart(2,'0'),role:i===0?'manager':i===1?'deputy':i<4?'supervisor':'member',photo:i%3?i+1:null}));
const base={version:'0.2.0',revision:1,archive:true,language:'de',store:'Dietikon',timezone:'Europe/Zurich',date:'2026-10-17',month:'2026-10',week:'2026-W42',pages:{},show_sunday:true,effects:true,duration:120,employees,gallery:[{id:100,label:'Unser Team in Dietikon – gemeinsam für einen ausgezeichneten Service. '.repeat(3).slice(0,200)}],content:{spotlight:{employee_id:2,message:{de:'Danke für deinen Einsatz und deine Unterstützung im Team!',en:'Thank you for your commitment and support!',fr:'Merci pour ton engagement et ton soutien !'}},podium:[{employee_id:1,count:248},{employee_id:2,count:220},{employee_id:3,count:193}],roles:[{employee_id:1,title:{en:'Service lead'}}],transfers:{days:[1,2,3,4,5,6,7],pending:8},targets:{nps:78,nps_target:70,nps_previous:72,waiting:134,waiting_green:180,waiting_amber:300,quality:96.8,quality_target:95,quality_previous:95.6,service_total:661,service_target:800},daily:[{en:'Prepare the service area'}],tasks:{en:['Store task one','Store task two']}}};
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.PULSE_BROWSER?{executablePath:process.env.PULSE_BROWSER}:{})});
 try{
  const page=await browser.newPage({viewport:{width:1920,height:1080},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://pulse-visual.test/media?*',async route=>{
   const id=Number(new URL(route.request().url()).searchParams.get('id'));
   const w=id%2?480:1280,h=id%2?1280:480;
   await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="'+w+'" height="'+h+'"><rect width="100%" height="100%" fill="#d3e7f7"/><rect x="10" y="10" width="'+(w-20)+'" height="'+(h-20)+'" fill="none" stroke="#ed243e" stroke-width="20"/><circle cx="'+w/2+'" cy="'+h/2+'" r="90" fill="#00559d"/><text x="50%" y="50%" text-anchor="middle" fill="white" font-size="60">'+id+'</text></svg>'});
  });
  async function load(overrides={}){
   const data={...base,...overrides};data.labels=labels[data.language];
   await page.goto('about:blank');
   await page.setContent('<html lang="'+data.language+'"><head><base href="http://pulse-visual.test/"><style>'+fs.readFileSync(path.join(assets,'screen.css'),'utf8')+'</style></head><body><div id="screen"><header><div class="brand">PULSE<span></span></div><div class="store" id="store"></div><div class="clock" id="clock"></div></header><main id="slide"></main><footer><span id="period"></span><nav id="pages"></nav><span id="page-count"></span></footer><div class="timeline"><div id="timeline"></div></div><aside id="update-notice" hidden></aside><aside id="offline" hidden></aside></div><script id="screen-data" type="application/json">'+JSON.stringify(data).replace(/</g,'\\u003c')+'</script><script>'+fs.readFileSync(path.join(assets,'screen.js'),'utf8')+'</script></body></html>');
   await page.locator('#slide h1').waitFor();
  }
  async function slide(i){await page.locator('#pages button[data-page="'+i+'"]').click();await page.evaluate(async()=>{await Promise.all([...document.images].map(i=>i.decode().catch(()=>{})));});}
  async function fits(label){
   const overflow=await page.locator('#slide').evaluate(el=>({height:el.scrollHeight-el.clientHeight,width:el.scrollWidth-el.clientWidth}));
   assert(overflow.height<=1&&overflow.width<=1,label+': '+JSON.stringify(overflow));
  }
  for(const language of ['de','fr','en']){
   await load({language});
   assert.equal(await page.locator('#pages button').count(),9,'Team must occupy exactly one page');
   for(let i=0;i<9;i++){await slide(i);await fits(language+' slide '+i);}
   await slide(5);assert.equal(await page.locator('.metric').count(),4);assert(await page.locator('.metric').last().textContent().then(t=>t.includes('661')&&t.includes('800')));await page.screenshot({path:path.join(output,'pulse-targets-'+language+'.png')});
   await slide(2);
   assert.equal(await page.locator('.team-person').count(),30);
   assert.equal(await page.locator('.team-group').count(),4);
   const teamSize=Number(await page.locator('.team-person strong').first().evaluate(el=>parseFloat(getComputedStyle(el).fontSize))); await page.screenshot({path:path.join(output,'pulse-team-'+language+'.png')}); console.log(language+' team font '+teamSize+'; '+output); assert(teamSize>=24,'30-person team text should remain readable');
  }
  await page.screenshot({path:path.join(output,'pulse-team-30.png')});
  await slide(0);
  assert.equal(await page.locator('.portrait img').evaluate(el=>getComputedStyle(el).objectFit),'contain');
  await slide(1);
  assert.equal(await page.locator('.avatar img').first().evaluate(el=>getComputedStyle(el).objectFit),'contain');
  assert(await page.locator('.avatar img').evaluateAll(images=>images.every(el=>{const image=el.getBoundingClientRect(),frame=el.parentElement.getBoundingClientRect();return image.height<=frame.height+1&&image.width<=frame.width+1})),'Portrait img boxes must remain inside their frames');
  await page.screenshot({path:path.join(output,'pulse-podium.png')});
  await slide(7);
  const rects=await page.evaluate(()=>{const image=document.querySelector('.gallery-photo').getBoundingClientRect(),caption=document.querySelector('.photo-label').getBoundingClientRect(),footer=document.querySelector('footer').getBoundingClientRect();return{imageBottom:image.bottom,captionTop:caption.top,captionBottom:caption.bottom,footerTop:footer.top}});
  assert(rects.imageBottom<=rects.captionTop&&rects.captionBottom<rects.footerTop,'Caption must not overlap image or footer');
  assert.equal(await page.locator('.photo-label').textContent(),base.gallery[0].label);
  await page.screenshot({path:path.join(output,'pulse-gallery.png')});
  await load({gallery:[{id:101,label:'W'.repeat(200)}]});await slide(7);await fits('unbroken 200-character gallery caption');
  for(const show_sunday of [false,true]){
   await load({show_sunday});await slide(4);
   assert.equal(await page.locator('.bar-column').count(),show_sunday?7:6);
   assert.equal(await page.locator('.transfer-summary .value').textContent(),show_sunday?'28':'21');
  }
  await load({employees:employees.concat(employees.map(p=>({...p,id:p.id+30,name:p.name+' II'})))});await slide(2);await fits('60 employees one page');assert.equal(await page.locator('.team-person').count(),60);
  await load({employees:employees.map(p=>({...p,role:p.id<=10?'manager':p.id<=20?'deputy':'supervisor'}))});await slide(2);await fits('30 employees distributed among leadership roles');
  await page.setViewportSize({width:3840,height:2160});await load();await slide(2);
  assert.equal(await page.locator('#screen').evaluate(el=>Math.round(el.getBoundingClientRect().width)),3840);
  await fits('4K team');
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('about:blank');
  await page.setContent('<html><head><style>'+fs.readFileSync(path.join(assets,'app.css'),'utf8')+'</style></head><body><form><label class="field"><span>Photo</span><input type="file" accept="image/jpeg,image/png,image/webp"></label></form><script>'+fs.readFileSync(path.join(assets,'admin.js'),'utf8')+'</script></body></html>');
  await page.locator('input').setInputFiles({name:'preview.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7C8AAAAASUVORK5CYII=','base64')});
  assert.equal(await page.locator('.upload-preview').count(),1,'Selected employee/gallery image gets a local preview');
  assert.equal(await page.locator('.upload-preview').evaluate(el=>getComputedStyle(el).objectFit),'contain');
  await page.locator('input').setInputFiles([]);
  assert.equal(await page.locator('.upload-preview').count(),0,'Clearing a file clears its preview');
  assert.deepEqual(errors,[]);
  console.log('Visual asset checks passed: all 9 modules in EN/DE/FR, single-page teams (30/60), 30-person readability, full photos, long gallery captions, Sunday totals, and 4K scaling.');
  console.log('Screenshots: '+output);
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});