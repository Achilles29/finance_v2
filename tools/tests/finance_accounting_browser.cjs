'use strict';
// Offline synthetic HTML through CDP pipes. No app/server connection or live financial data.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),{spawn,spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..');
const fixture=spawnSync('php',[path.join(__dirname,'finance_accounting_smoke.php'),'--render-json'],{encoding:'utf8',maxBuffer:4*1024*1024});
if(fixture.status!==0)throw Error('Synthetic render failed: '+fixture.stderr);
const marker=fixture.stdout.split('\n').find(s=>s.startsWith('GL_UI_JSON='));
if(!marker)throw Error('Render output missing');
const variants=JSON.parse(marker.slice(11));
const js=fs.readFileSync(path.join(root,'assets/js/finance-accounting.js'),'utf8');new vm.Script(js);
const css=['assets/vendor/css/core.css','assets/css/theme-custom.css','assets/css/app.css'].map(p=>fs.readFileSync(path.join(root,p),'utf8')).join('\n');
const profile=fs.mkdtempSync('/tmp/finance-accounting-browser-');
const chrome=spawn('/usr/bin/google-chrome',['--headless','--no-sandbox','--disable-dev-shm-usage','--disable-background-networking','--no-first-run','--disable-sync','--disable-extensions','--disable-component-update','--remote-debugging-pipe','--user-data-dir='+profile],{stdio:['ignore','ignore','pipe','pipe','pipe']});
let seq=0,buffer='',diagnostics='',checks=0;const pending=new Map();
const abort=e=>{for(const item of pending.values()){clearTimeout(item.timer);item.reject(e);}pending.clear();};
chrome.stderr.on('data',c=>{diagnostics=(diagnostics+c.toString()).slice(-2000);});
chrome.on('error',abort);chrome.on('exit',code=>{if(code!==0)abort(Error('Browser unavailable '+code+': '+diagnostics));});
for(const stream of [chrome.stdio[3],chrome.stdio[4]])stream.on('error',e=>abort(e));
chrome.stdio[4].on('data',chunk=>{buffer+=chunk.toString();let end;while((end=buffer.indexOf('\0'))>=0){const raw=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!raw)continue;const r=JSON.parse(raw),item=pending.get(r.id);if(!item)continue;pending.delete(r.id);clearTimeout(item.timer);r.error?item.reject(Error(r.error.message)):item.resolve(r.result);}});
const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser timeout'));},10000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})})+'\0');});
const check=(ok,label)=>{if(!ok)throw Error(label);checks++;};
(async()=>{
 try {
  await send('Browser.getVersion');
  for(const width of [360,768,1280])for(const [name,raw] of Object.entries(variants)){
   const {targetId}=await send('Target.createTarget',{url:'about:blank'}),{sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
   const cmd=(m,p)=>send(m,p,sessionId);
   const evaluate=async expression=>{const r=await cmd('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
   await cmd('Page.enable');await cmd('Network.enable');await cmd('Network.setBlockedURLs',{urls:['*']});
   await cmd('Emulation.setDeviceMetricsOverride',{width,height:960,deviceScaleFactor:1,mobile:false});
   const {frameTree}=await cmd('Page.getFrameTree');
   const stub="window.errors=[];addEventListener('error',e=>errors.push(e.message));window.calls=[];window.confirm=()=>true;window.fetch=async(url,p)=>{calls.push({url,...p});return {ok:false,json:async()=>({ok:false,message:'Synthetic rejected, retry same form'})};};";
   const html=raw.replace(/<script src="[^"]*finance-accounting\.js" defer><\/script>/,'')+'<script>'+js+'</script>';
   await cmd('Page.setDocumentContent',{frameId:frameTree.frame.id,html:'<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style><script>'+stub+'</script></head><body>'+html+'</body></html>'});
   check(await evaluate('errors.length===0'),'JS errors '+name);
   check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'overflow '+name+' '+width);
   check(await evaluate('document.querySelectorAll("img").length===0'),'XSS account name '+name);
   if(name.includes('readonly'))check(await evaluate('!document.getElementById("gl-entry")&&!document.getElementById("gl-reverse")'),'readonly writer '+name);
   if(name.startsWith('entry')){
    if(name==='entry-assistant'){
     check(await evaluate(`document.querySelector('[data-edit-line] [data-code]').value===''`),'assistant never silently prefills');
     await evaluate(`document.getElementById('gl-assistant-apply').click()`);
     check(await evaluate(`document.getElementById('gl-assistant-state').textContent.includes('centang')`),'assistant requires explicit confirmation');
     await evaluate(`(()=>{const c=document.getElementById('gl-assistant-choice');c.value='OPERATING_EXPENSE';c.dispatchEvent(new Event('change'));document.getElementById('gl-assistant-confirm').checked=true;document.getElementById('gl-assistant-apply').click();})()`);
     check(await evaluate(`document.getElementById('gl-difference').textContent==='Seimbang'&&document.querySelectorAll('[data-edit-line]').length===1`),'assistant fills balanced single counter');
     await evaluate(`(async()=>{const f=document.getElementById('gl-entry');f.elements.reference.value='Synthetic';f.elements.memo.value='Verified test';f.requestSubmit();await new Promise(r=>setTimeout(r,30));})()`);
     const payload=JSON.parse((await evaluate('calls'))[0].body);
     check(payload.assistant_scenario==='OPERATING_EXPENSE'&&payload.assistant_confirmed===true&&payload.assistant_hash.length===64,'assistant sends review proof');
     await evaluate(`(async()=>{const row=document.querySelector('[data-edit-line] [data-code]');row.value='5300';row.dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('gl-entry').requestSubmit();await new Promise(r=>setTimeout(r,30));})()`);
     check(!JSON.parse((await evaluate('calls'))[1].body).assistant_scenario,'manual edit clears assistant provenance');
    }
    if(name==='entry'){
     await evaluate(`(()=>{const rows=document.querySelectorAll('[data-edit-line]');rows[0].querySelector('[data-code]').value='5200';rows[0].querySelector('[data-debit]').value='10.10';rows[1].querySelector('[data-code]').value='2100';rows[1].querySelector('[data-credit]').value='10.10';document.getElementById('gl-lines').dispatchEvent(new Event('input',{bubbles:true}));})()`);
     check(await evaluate(`document.getElementById('gl-difference').textContent==='Seimbang'`),'exact client total');
    }
    if(['entry-transfer','entry-reversal'].includes(name))check(await evaluate(`document.querySelectorAll('[data-edit-line]').length===0&&document.getElementById('gl-add').hidden`),'automatic counter no manual required '+name);
    if(name==='entry'||name==='entry-transfer'||name==='entry-reversal'){
     await evaluate(`(async()=>{const f=document.getElementById('gl-entry');f.elements.reference.value='Synthetic';f.elements.memo.value='Verified test';f.requestSubmit();await new Promise(r=>setTimeout(r,30));})()`);
     const calls=await evaluate('calls');check(calls.length===1,'one POST '+name);
     check(calls[0].headers['X-Finance-Accounting-CSRF']==='a'.repeat(64),'CSRF header '+name);
     check(JSON.parse(calls[0].body).request_key.length===32,'request identity '+name);
     check(await evaluate(`!document.querySelector('#gl-entry [type=submit]').disabled&&document.getElementById('gl-message').textContent.includes('Synthetic rejected')`),'retry enabled on rejection '+name);
    }
   }
   if(name.startsWith('settings')){
    const editable=name==='settings';
    check(await evaluate(`!!document.querySelector('.gl-setup-form button')`)===editable,'setup buttons only when schema and RBAC allow');
    if(editable){
     await evaluate(`(async()=>{document.querySelector('.gl-setup-form').requestSubmit();await new Promise(r=>setTimeout(r,30));})()`);
     const call=(await evaluate('calls'))[0];
     check(call.url==='/finance-reports/accounting/settings/mapping','setup endpoint slash handling');
     check(call.headers['X-Finance-Accounting-CSRF']==='a'.repeat(64)&&JSON.parse(call.body).expected_hash.length===64,'setup CSRF and stale protection');
     check(await evaluate(`!document.querySelector('.gl-setup-form button').disabled&&document.querySelector('[data-gl-feedback]').textContent.includes('Synthetic rejected')`),'setup retry with inline feedback');
     await evaluate(`(()=>{const s=document.querySelector('[data-gl-search="mapping"]');s.value='unlikely-mapping-xyz';s.dispatchEvent(new Event('input'));})()`);
     check(await evaluate(`[...document.querySelectorAll('[data-gl-filter="mapping"]')].every(r=>r.hidden)`),'mapping filter does not require network');
    }
   }
   await send('Target.closeTarget',{targetId});
  }
  console.log('Accounting browser: '+checks+' PASS at 360/768/1280px, offline synthetic HTML only.');
 } finally {chrome.kill();abort(Error('Browser closed'));}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
