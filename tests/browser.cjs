// Workstation test. Requires Playwright, PHP and a running test server.
// PULSE_TEST_STORAGE must point to a fresh pulse-qa-* temporary directory.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),cp=require('node:child_process');
const {chromium}=require(process.env.PULSE_PLAYWRIGHT||'playwright');
const base=process.env.PULSE_TEST_URL||'http://127.0.0.1:8099';
const storage=process.env.PULSE_TEST_STORAGE;
if(!storage||!storage.includes('pulse-qa-'))throw new Error('Use an isolated test storage directory.');
const project=path.resolve(__dirname,'..');
function fixture(command){cp.execFileSync(process.env.PULSE_PHP||'php',[path.join(__dirname,'fixture.php'),command],{env:{...process.env,PULSE_TEST_MODE:'1',PULSE_STORAGE:storage}});}
(async()=>{
  fixture('image');
  const browser=await chromium.launch({headless:true,...(process.env.PULSE_BROWSER?{executablePath:process.env.PULSE_BROWSER}:{})});
  const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const page=await context.newPage(),errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  const form=(action)=>page.locator('form').filter({has:page.locator('input[name="action"][value="'+action+'"]')});
  async function submit(action){await form(action).locator('button.primary').click();await page.waitForLoadState('networkidle');}
  async function api(){return (await context.request.get(base+'/api/screen')).json();}
  await page.goto(base+'/setup');
  await page.locator('.languages button[value=en]').click();
  await page.locator('input[name=token]').fill(fs.readFileSync(path.join(storage,'setup-token'),'utf8').trim());
  await page.locator('[data-next]').click();
  await page.locator('input[name=store]').fill('Dietikon · QA');
  await page.locator('select[name=language]').selectOption('en');
  await page.locator('[data-next]').click();
  await page.locator('input[name=name]').fill('Test Admin');
  await page.locator('input[name=email]').fill('admin@example.invalid');
  await page.locator('input[name=password]').fill('test-only-phrase-123');
  await page.locator('input[name=password_repeat]').fill('test-only-phrase-123');
  await page.locator('[data-finish]').click();
  await page.waitForURL(base+'/admin');
  assert(await page.getByText('Ready for your team',{exact:true}).isVisible());
  await page.goto(base+'/setup');assert(page.url()===base+'/admin');
  for(const [name,role] of [['Alex Test','manager'],['Sam Test','deputy'],['Robin Test','member']]){
    await page.goto(base+'/admin?tab=employees');
    await page.locator('input[name=name]').fill(name);
    await page.locator('select[name=role]').selectOption(role);
    await page.locator('input[name=photo]').setInputFiles(path.join(storage,'fixture.jpg'));
    await submit('employee_save');
    assert(await page.getByText(name,{exact:true}).isVisible());
  }
  let state=await api();assert.equal(state.employees.length,3);
  const people=state.employees,alex=people.find(p=>p.name==='Alex Test');
  await context.request.post(base+'/action',{form:{action:'employee_delete',id:String(alex.id),return:'/admin?tab=employees'}});
  assert.equal((await api()).employees.length,3,'CSRF protection failed');
  await page.goto(base+'/admin?tab=content&module=spotlight');
  await page.locator('select[name=employee_id]').selectOption(String(alex.id));
  await page.locator('textarea[name=message_en]').fill('A great month, together.');
  await submit('content');
  await page.goto(base+'/admin?tab=content&module=podium');
  for(const [i,p] of people.entries())await page.locator('input[name="counts['+p.id+']"]').fill(String([248,193,216][i]));
  await submit('content');
  await page.goto(base+'/admin?tab=content&module=targets');
  for(const [key,value] of Object.entries({service_target:500,service_total:320,nps:78,nps_target:70,nps_previous:72,waiting:134,waiting_green:180,waiting_amber:300,quality:96.8,quality_target:95,quality_previous:95.6}))await page.locator('input[name="'+key+'"]').fill(String(value));
  await submit('content');
  assert.equal((await api()).content.targets.service_total,320,'Manual result was replaced with podium counts');
  assert.equal(await page.locator('input[name=service_total]').inputValue(),'320','Saved manual result missing from editor');
  assert.equal(await page.locator('input[name=service_target]').inputValue(),'500');
  await page.goto(base+'/admin?tab=content&module=daily');
  await page.locator('input[name=line_0_en]').fill('Prepare the service area');
  await submit('content');
  await page.goto(base+'/admin?tab=content&module=transfers');
  await page.locator('input[name=day_0]').fill('12');await submit('content');
  await page.goto(base+'/admin?tab=content&module=roles');
  await page.locator('select[name=employee_0]').selectOption(String(alex.id));
  await page.locator('input[name=role_0_en]').fill('Service lead');await submit('content');
  await page.goto(base+'/admin?tab=content&module=tasks');
  await page.locator('textarea[name=tasks_en]').fill('Store task one\nStore task two');await submit('content');
  await page.goto(base+'/admin?tab=gallery');
  await page.locator('input[name=label]').fill('Team image');
  await page.locator('input[name=image]').setInputFiles(path.join(storage,'fixture.jpg'));await submit('gallery_add');
  assert.equal((await api()).gallery.length,1);
  await page.goto(base+'/admin?tab=employees');
  await page.locator('input[name=name]').fill('Invalid upload');
  await page.locator('input[name=photo]').setInputFiles({name:'invalid.jpg',mimeType:'image/jpeg',buffer:Buffer.from('<?php echo "not an image";')});
  await submit('employee_save');assert(await page.locator('.notice.error').isVisible());assert.equal((await api()).employees.length,3);
  await page.goto(base+'/admin?tab=administrators');
  await form('admin_add').locator('input[name=name]').fill('Second Admin');
  await form('admin_add').locator('input[name=email]').fill('second@example.invalid');
  await form('admin_add').locator('input[name=password]').fill('second-test-phrase-123');await submit('admin_add');
  assert(await page.getByText('Second Admin',{exact:true}).isVisible());
  await page.goto(base+'/admin?tab=backups');await submit('backup');
  const download=page.locator('a[href^="/download-backup"]').first();
  const downloadPath=await download.getAttribute('href');
  const archive=await context.request.get(base+downloadPath);
  assert.equal(archive.status(),200);assert((await archive.body()).subarray(0,2).equals(Buffer.from('PK')));
  for(const tab of ['overview','content','employees','gallery','settings','administrators','backups','updates']){
    await page.goto(base+'/admin?tab='+tab);assert.equal(await page.locator('main.admin-main').count(),1);
  }
  await page.goto(base+'/admin?tab=updates');await submit('check_updates');
  assert(await page.getByText('No release source configured.',{exact:true}).isVisible());
  for(const lang of ['de','fr','en']){await page.locator('.languages button[value='+lang+']').click();assert.equal(await page.locator('html').getAttribute('lang'),lang);}
  const screenshots=process.env.PULSE_SCREENSHOTS||storage;
  await page.goto(base+'/admin');await page.screenshot({path:path.join(screenshots,'pulse-admin.png'),fullPage:true});
  await page.setViewportSize({width:390,height:900});
  await page.goto(base+'/admin?tab=settings');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile admin overflow');
  await page.setViewportSize({width:1920,height:1080});
  await page.goto(base+'/display');
  for(let i=0;i<9;i++){
    await page.locator('#pages button[data-page="'+i+'"]').click();
    assert.equal(await page.locator('#slide h1').count(),1);
    const overflow=await page.locator('#slide').evaluate(el=>el.scrollHeight>el.clientHeight+1||el.scrollWidth>el.clientWidth+1);
    assert(!overflow,'TV content overflow on slide '+i);
  }
  await page.locator('#pages button[data-page="0"]').click();assert(await page.getByText('Alex Test',{exact:true}).isVisible());
  await page.screenshot({path:path.join(screenshots,'pulse-tv.png')});
  await page.locator('#pages button[data-page="1"]').click();assert(await page.locator('.podium').isVisible());
  await page.locator('#pages button[data-page="5"]').click();assert(await page.getByText('96.8%',{exact:true}).isVisible());
  assert(await page.getByText('320',{exact:true}).isVisible(),'Manual team result not shown on TV');
  await page.clock.install();
  fixture('available');await page.clock.fastForward(31000);
  await page.waitForFunction(()=>!document.querySelector('#update-notice').hidden&&document.querySelector('#update-notice').textContent.includes('0.3.0'));
  fixture('installed');await page.clock.fastForward(31000);
  await page.waitForFunction(()=>document.querySelector('#update-notice').textContent.includes('Update installed'));
  fixture('clear-updates');
  const anonymous=await browser.newContext();const denied=await anonymous.request.get(base+'/admin');
  assert(denied.url().endsWith('/login'));
  const backupDenied=await anonymous.request.get(base+downloadPath);
  assert(backupDenied.url().endsWith('/login'));
  await anonymous.close();
  assert.deepEqual(errors,[]);
  await browser.close();
  console.log('Browser checks passed: setup, authentication, employees, uploads, all modules, backup, languages, responsive admin, TV, both update notices.');
})().catch(error=>{console.error(error);process.exitCode=1;});
