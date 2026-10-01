'use strict';
// Real browser/HTTPS acceptance. Credentials stay in memory and local CDP pipes, never shell args/output.
const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const fixturePath=process.argv[2];
if(!/^\/var\/lib\/finance-guided-[a-f0-9]+\/mock\/fixture\.json$/.test(fixturePath))throw Error('DISPOSABLE_ONLY');
const f=JSON.parse(fs.readFileSync(fixturePath)),base=path.dirname(path.dirname(fixturePath));
const secret=fs.readFileSync(path.join(f.root,'private/delivery/KODE-SETUP.txt'),'utf8');
const profile=fs.mkdtempSync(path.join(base,'browser-'));fs.chmodSync(profile,0o700);
const chrome=spawn('/usr/bin/google-chrome',['--headless','--no-sandbox','--disable-dev-shm-usage','--disable-background-networking','--no-first-run','--disable-sync','--disable-extensions','--disable-component-update','--ignore-certificate-errors','--remote-debugging-pipe','--user-data-dir='+profile],{stdio:['ignore','ignore','pipe','pipe','pipe']});
process.once('SIGTERM',()=>{chrome.kill('SIGTERM');process.exit(143);});
let serial=0,buffer='',session,stage='chrome-start',diagnostic='';
const pending=new Map(),pause=ms=>new Promise(r=>setTimeout(r,ms));
chrome.stderr.on('data',b=>{diagnostic=(diagnostic+b.toString()).slice(-1000);});
const abort=()=>{for(const p of pending.values()){clearTimeout(p.timer);p.reject(Error('BROWSER_CLOSED'));}pending.clear();};
chrome.on('error',abort);chrome.on('exit',abort);
chrome.stdio[4].on('data',chunk=>{buffer+=chunk.toString();let at;while((at=buffer.indexOf('\0'))>=0){const raw=buffer.slice(0,at);buffer=buffer.slice(at+1);if(!raw)continue;const m=JSON.parse(raw),p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.timer);m.error?p.reject(Error('CDP_FAILURE')):p.resolve(m.result);}}});
const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++serial,timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP_TIMEOUT'));},30000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})})+'\0');});
const cdp=(method,params={})=>send(method,params,session);
async function until(fn,limit=10000){const start=Date.now();do{if(await fn())return;await pause(200);}while(Date.now()-start<limit);throw Error('BROWSER_WAIT_TIMEOUT');}
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error('BROWSER_SCRIPT_FAILED');return r.result.value;}
function check(ok,label){if(!ok)throw Error(label);}
(async()=>{try{
  await send('Browser.getVersion');
  const {targetId}=await send('Target.createTarget',{url:'about:blank'});
  session=(await send('Target.attachToTarget',{targetId,flatten:true})).sessionId;
  stage='page-open';await cdp('Page.enable');await cdp('Network.enable');
  await cdp('Security.setIgnoreCertificateErrors',{ignore:true}); // Generated disposable TLS certificate ONLY.
  await cdp('Page.navigate',{url:f.base_url+'setup'});
  await until(()=>evaluate('document.documentElement.dataset.setupReady==="true"'),60000);
  if(process.argv.includes('--page-only')){console.log(JSON.stringify({status:'PASS',page:'SETUP_RENDERED'}));return;}
  await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  check(await evaluate('document.documentElement.scrollWidth<=window.innerWidth+1'),'MOBILE_HORIZONTAL_OVERFLOW');
  stage='authorize';await evaluate(`document.getElementById('setup-code').value=${JSON.stringify(secret)};document.getElementById('access-form').requestSubmit()`);
  await until(()=>evaluate('!document.getElementById("settings").hidden'));
  const values={base_url:f.base_url,host:f.database.host,port:f.database.port,database:f.database.name,db_user:f.database.user,db_password:f.database.password,username:f.owner.username,email:f.owner.email,password:f.owner.password};
  await evaluate(`(()=>{const f=document.getElementById('settings');for(const [k,v]of Object.entries(${JSON.stringify(values)})){f.elements[k].value=v;f.elements[k].dispatchEvent(new Event('input',{bubbles:true}));}})()`);
  await evaluate(`document.querySelector('[data-toggle="db-password"]').click()`);
  check(await evaluate('document.getElementById("db-password").type==="text"'),'PASSWORD_TOGGLE_FAILED');
  stage='database-probe';await evaluate(`document.querySelector('[data-toggle="db-password"]').click();document.getElementById('probe').click()`);
  await until(()=>evaluate('!document.getElementById("review-button").disabled'),100000);
  const probeId=await evaluate('JSON.parse(sessionStorage.getItem("finance.setup.command")).id');
  stage='review';await evaluate('document.getElementById("settings").requestSubmit()');
  check(await evaluate('!document.getElementById("review").hidden'),'REVIEW_NOT_VISIBLE');
  check(await evaluate(`!document.getElementById('summary').textContent.includes(${JSON.stringify(f.database.password)})`),'PASSWORD_IN_REVIEW');
  stage='submit-install';await evaluate('document.getElementById("confirm").click();document.getElementById("install").click()');
  await until(()=>evaluate('!document.getElementById("progress").hidden'));
  const installId=await evaluate('JSON.parse(sessionStorage.getItem("finance.setup.command")).id');
  // Confirm async POST reached the server before closing the browser; closing then tests resumability.
  await until(()=>fs.existsSync(path.join(f.root,`storage/inbox/command-${installId}.json`))||fs.existsSync(path.join(f.root,`storage/setup/result-${installId}.json`)));
  check(await evaluate('Object.keys(JSON.parse(sessionStorage.getItem("finance.setup.command"))).sort().join(",")==="id,kind"'),'SECRET_IN_BROWSER_STORAGE');
  stage='resume-after-reload';await cdp('Page.reload');
  await until(()=>evaluate('document.documentElement.dataset.setupReady==="true"'));
  await evaluate(`document.getElementById('setup-code').value=${JSON.stringify(secret)};document.getElementById('access-form').requestSubmit()`);
  await until(()=>evaluate('!document.getElementById("progress").hidden'));
  await evaluate('document.getElementById("resume").click()');
  check(await evaluate(`JSON.parse(sessionStorage.getItem('finance.setup.command')).id===${JSON.stringify(installId)}`),'RETRY_CREATED_NEW_ID');
  console.log(JSON.stringify({status:'PASS',probe_id:probeId,install_id:installId,mobile_width:390}));

}catch(e){console.error('BROWSER_STAGE='+stage);try{console.error(await evaluate('document.getElementById("notice")?.textContent||"NO_NOTICE"'));}catch{}throw e;
}finally{try{await send('Browser.close');}catch{}chrome.kill('SIGTERM');abort();}})().catch(e=>{console.error(e.message);process.exitCode=1;});
