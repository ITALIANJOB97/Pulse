// Browser regression: real forms, SQLite persistence, TV output and immediate-update consent.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),cp=require('node:child_process'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PULSE_PLAYWRIGHT||'playwright');
const project=path.resolve(__dirname,'..'),php=process.env.PULSE_PHP||'php';
const storage=fs.mkdtempSync(path.join(os.tmpdir(),'pulse-qa-targets-'));
const updater=path.join(storage,'updater');fs.mkdirSync(updater);
const env={...process.env,PULSE_STORAGE:storage,PULSE_UPDATER_STATE:updater,PULSE_UPDATER_CONFIG:path.join(updater,'config.json')};
function command(code){return cp.execFileSync(php,['-r',"require $argv[1].'/app/bootstrap.php';boot();"+code,project],{env,encoding:'utf8'});}
const release={version:'9.0.0',url:'https://www.luigipranzo.com/myfile/Pulse/pulse-9.0.0.zip',mandatory:false,size:100,notes:{en:'Browser test only'},release_notes:{fixes:['Browser test only']}};
function status(phase,updaterVersion=4){fs.writeFileSync(path.join(updater,'status.json'),JSON.stringify({updater_version:updaterVersion,phase,checked_epoch:Date.now()/1000,release}));}
fs.writeFileSync(path.join(updater,'config.json'),JSON.stringify({schema:3,program:'Pulse',manifest:'https://www.luigipranzo.com/myfile/Pulse/update.json'}));status('available');
command("initialize(['token'=>setupToken(),'name'=>'Targets QA','email'=>'targets@example.invalid','password'=>'targets-browser-test-123','store'=>'Test','language'=>'en','duration'=>20]);");
const base=process.env.PULSE_TEST_URL||'http://127.0.0.1:8108';
const server=cp.spawn(php,['-S',new URL(base).host,'-t',path.join(project,'public'),path.join(project,'public/router.php')],{cwd:project,env,windowsHide:true,stdio:'ignore'});
let browser;
(async()=>{
  for(let i=0;i<100;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await new Promise(r=>setTimeout(r,200));if(i===99)throw new Error('PHP server did not start');}
  browser=await chromium.launch({headless:true,...(process.env.PULSE_BROWSER?{executablePath:process.env.PULSE_BROWSER}:{})});
  const context=await browser.newContext({viewport:{width:1920,height:1080},reducedMotion:'reduce'}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/login');await page.locator('.languages button[value=en]').click();
  await page.locator('input[name=email]').fill('targets@example.invalid');await page.locator('input[name=password]').fill('targets-browser-test-123');
  await page.locator('button.primary').click();await page.waitForURL(base+'/admin');
  const form=()=>page.locator('form').filter({has:page.locator('input[name=action][value=content]')});
  for(const [target,total] of [['0.8','0.6'],['0,8','0,6'],['1.25','0.01'],['0.00001','0.000001'],['0','0'],['','']]){
    await page.goto(base+'/admin?tab=content&module=targets');
    await page.locator('input[name=service_target]').fill(target);await page.locator('input[name=service_total]').fill(total);
    assert(await form().evaluate(f=>f.checkValidity()),'Browser blocked decimal '+total);
    await form().locator('button.primary').click();await page.waitForLoadState('load');
    await page.reload();
    const reloaded=await page.locator('input[name=service_total]').inputValue();
    assert.equal(total===''?reloaded:Number(reloaded),total===''?'':Number(total.replace(',','.')));
    assert(await form().evaluate(f=>f.checkValidity()),'Reloaded decimal blocked the next save');
    const data=await (await context.request.get(base+'/api/screen')).json();
    assert.equal(data.content.targets.service_total,total===''?null:Number(total.replace(',','.')));
  }
  await page.locator('input[name=service_target]').fill('0,8');await page.locator('input[name=service_total]').fill('0,6');
  await form().locator('button.primary').click();await page.waitForLoadState('load');
  for(const lang of ['en','de','fr']){
    command("setSetting('language','"+lang+"');");
    await page.goto(base+'/display');await page.locator('#pages button[data-page="5"]').click();
    const card=page.locator('.metric').last(),decimal=lang==='en'?'0.6':'0,6';
    assert((await card.innerText()).includes(decimal),'TV rounded decimal in '+lang);
    assert((await card.innerText()).includes('75%'),'Team percentage incorrect');
  }
  if(process.env.PULSE_SCREENSHOTS){fs.mkdirSync(process.env.PULSE_SCREENSHOTS,{recursive:true});await page.screenshot({path:path.join(process.env.PULSE_SCREENSHOTS,'PULSE-Team-Targets-0.2.8.png')});}
  await page.goto(base+'/admin?tab=updates');
  const install=()=>page.locator('form').filter({has:page.locator('input[name=action][value=install_update]')});
  assert.equal(await install().locator('input[name=version]').inputValue(),'9.0.0');
  page.once('dialog',d=>d.dismiss());await install().locator('button').click();
  assert.equal(command("echo json_encode(setting('update_install',[]));"),'[]','Declined confirmation queued update');
  const sent=page.waitForResponse(r=>r.url().endsWith('/action')&&r.request().method()==='POST');
  page.once('dialog',d=>d.accept());await install().locator('button').click();await sent;
  assert.equal(JSON.parse(command("echo json_encode(setting('update_install',[]));")).version,'9.0.0');
  status('downloading');await page.reload();assert.equal(await install().count(),0,'Busy updater exposes install button');
  status('available',1);await page.reload();assert.equal(await install().count(),0,'Legacy service exposes unsupported action');
  assert((await page.locator('.notice').allTextContents()).join(' ').includes('aggiorna-ora.sh'),'Legacy immediate instructions missing');
  assert.deepEqual(errors,[]);console.log('Browser passed: dot/comma decimals, reload, zero/blank, EN/DE/FR TV, 75% progress, confirm/cancel, version-bound request, busy/legacy states. Storage: '+storage);
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();server.kill();});
