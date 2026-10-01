'use strict';
// Dependency-free CDP over pipes. Synthetic HTML only; all network requests blocked.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),{spawn}=require('node:child_process');
const dir=process.argv[2],root=path.resolve(__dirname,'../..');
if(!/^\/tmp\/finance-allocation-ui-[A-Za-z0-9]+$/.test(dir||''))throw Error('Pass the isolated UI_FIXTURE directory');
const profile=fs.mkdtempSync('/tmp/finance-allocation-chrome-');
const chrome=spawn('/usr/bin/google-chrome',['--headless','--no-sandbox','--disable-dev-shm-usage','--disable-background-networking','--no-first-run','--disable-sync','--disable-extensions','--disable-component-update','--remote-debugging-pipe','--user-data-dir='+profile],{stdio:['ignore','ignore','pipe','pipe','pipe']});
let sequence=0,buffer='',checks=0;const pending=new Map();
let diagnostics='';chrome.stderr.on('data',c=>{diagnostics=(diagnostics+c.toString()).slice(-2500);});
const abort=error=>{for(const item of pending.values()){clearTimeout(item.timer);item.reject(error);}pending.clear();};
chrome.on('error',abort);chrome.on('exit',code=>{if(code!==0)abort(Error('Browser exited '+code+': '+diagnostics));});
for(const stream of [chrome.stdio[3],chrome.stdio[4]])stream.on('error',e=>abort(Error(e.message+' '+diagnostics)));
chrome.stdio[4].on('data',chunk=>{
 buffer+=chunk.toString();let end;
 while((end=buffer.indexOf('\0'))>=0){const raw=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!raw)continue;const data=JSON.parse(raw),item=pending.get(data.id);if(!item)continue;
  pending.delete(data.id);clearTimeout(item.timer);data.error?item.reject(Error(data.error.message)):item.resolve(data.result);
 }
});
const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{
 const id=++sequence,timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout '+method));},15000);
 pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})})+'\0');
});
const check=(ok,label)=>{if(!ok)throw Error(label);checks++;};
const css=['assets/vendor/css/core.css','assets/css/theme-custom.css','assets/css/app.css'].map(f=>fs.readFileSync(path.join(root,f),'utf8')).join('\n');
const stub=`window.uiErrors=[];window.addEventListener('error',e=>uiErrors.push(e.message));window.calls=[];window.confirm=()=>true;
window.fetch=async(url,options={})=>{calls.push({url,...options});let body={};try{body=JSON.parse(options.body||'{}')}catch(e){}
 if(url.endsWith('/statement-import')&&!body.confirm_hash)return {ok:true,json:async()=>({ok:true,preview:true,preview_hash:'b'.repeat(64),file_hash:'f'.repeat(64),rows:[{statement_date:'2026-09-14',reference_no:'<img src=x onerror=alert(1)>',direction:'IN',amount:'100.25'}],message:'Preview'})};
 if(options.method==='POST')return {ok:false,json:async()=>({ok:false,message:'Fixture: no write performed.'})};
 return {ok:true,json:async()=>({ok:true,rows:[],page:1,pages:1,total:0,more:false})};};`;
(async()=>{
 try {
  for(const tab of ['settlement','cash-plan','bank-review','revenue','cash'])for(const readonly of [false,true])for(const width of [360,768,1280]){
   let html=fs.readFileSync(path.join(dir,tab+(readonly?'-readonly':'')+'.html'),'utf8');
   const deferred=[];
   html=html.replace(/<script src="\/(assets\/js\/finance-[a-z-]+\.js)" defer><\/script>/g,(_,file)=>{deferred.push(fs.readFileSync(path.join(root,file),'utf8'));return '';});
   html+=deferred.map(js=>'<script>'+js+'</script>').join('');
   for(const [,js] of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(js);
   const {targetId}=await send('Target.createTarget',{url:'about:blank'}),{sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
   const cmd=(m,p)=>send(m,p,sessionId);
   const evaluate=async expression=>{const r=await cmd('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
   await cmd('Page.enable');await cmd('Network.enable');await cmd('Network.setBlockedURLs',{urls:['*']});
   await cmd('Emulation.setDeviceMetricsOverride',{width,height:960,deviceScaleFactor:1,mobile:false});
   const {frameTree}=await cmd('Page.getFrameTree');
   await cmd('Page.setDocumentContent',{frameId:frameTree.frame.id,html:'<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style><script>'+stub+'</script></head><body>'+html+'</body></html>'});
   check(await evaluate('uiErrors.length===0'),'browser JS errors '+tab);
   check(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'page overflow '+tab+' '+width);
   if(readonly&&tab!=='revenue')check(await evaluate('document.querySelectorAll("[data-save],.fc-allocation-form,#fc-bank-import").length===0'),'read-only writers '+tab);
   if(!readonly&&tab==='settlement'){
    await evaluate(`(async()=>{const f=document.querySelector('.fc-allocation-form');f.closest('details').open=true;f.elements.notes.value='Verified synthetic allocation';f.requestSubmit();await new Promise(r=>setTimeout(r,30));})()`);
    const call=await evaluate(`calls.find(c=>c.url.endsWith('/receipt-distribute'))`);
    check(!!call&&call.headers['X-Finance-Control-CSRF'].length===64,'allocation POST scoped CSRF');
    const body=JSON.parse(call.body);check(JSON.parse(body.allocations).length===1&&body.request_key.length===32,'allocation payload exact rows and retry identity');
   }
   if(!readonly&&tab==='cash-plan')check(await evaluate(`document.querySelector('[data-save="plan-allocate"] [name="amount"]').required`),'partial plan amount required');
   if(!readonly&&tab==='bank-review'){
    await evaluate(`(async()=>{const f=document.getElementById('fc-bank-import');f.closest('details').open=true;const dt=new DataTransfer();dt.items.add(new File(['Date;Ref;In;Out\\n14/09/2026;B-1;100,25;'], 'fixture.csv', {type:'text/csv'}));f.querySelector('[data-role="csv"]').files=dt.files;f.requestSubmit();await new Promise(r=>setTimeout(r,100));})()`);
    check(await evaluate('document.querySelectorAll("#fc-bank-preview img").length===0&&document.getElementById("fc-bank-preview").textContent.includes("<img")'),'CSV references rendered as text, no HTML execution');
    check(await evaluate('!document.querySelector("[data-role=confirm]").disabled'),'preview enables explicit confirmation');
    await evaluate(`document.querySelector('[data-role=confirm]').click()`);
    const body=JSON.parse((await evaluate(`calls.filter(c=>c.url.endsWith('/statement-import')).at(-1)`)).body);
    check(body.column_date===0&&body.column_out===3&&body.confirm_hash==='b'.repeat(64),'CSV mapping one-based to zero-based, binds preview token');
    await evaluate(`const f=document.getElementById('fc-bank-import');f.elements.account_id.dispatchEvent(new Event('change',{bubbles:true}))`);
    check(await evaluate('document.querySelector("[data-role=confirm]").disabled'),'changed mapping clears prior confirmation');
   }
   if(!readonly&&tab==='revenue'){
    check(await evaluate(`(()=>{const card=[...document.querySelectorAll('.rr-method')].find(c=>!c.querySelector('[name="resolution_type"]').disabled);const t=card.querySelector('[name="resolution_type"]'),cat=card.querySelector('.rr-category');t.value='OUT';t.dispatchEvent(new Event('change'));const both=!cat.querySelector('[value="BALANCE_CORRECTION"]').disabled;t.value='TRANSFER';t.dispatchEvent(new Event('change'));return both&&cat.disabled&&!card.querySelector('.rr-counter-label').hidden;})()`),'revenue BOTH category retained, transfer shows account selection');
    check(await evaluate(`(()=>{const card=[...document.querySelectorAll('.rr-method')].find(c=>!c.querySelector('[name="resolution_type"]').disabled);const a=card.querySelector('[name="account_id"]'),counter=card.querySelector('.rr-counter-account'),m=card.querySelector('.rr-counter');a.value='1';a.dispatchEvent(new Event('change'));const sameDisabled=counter.querySelector('[value="1"]').disabled;counter.value='2';counter.dispatchEvent(new Event('change'));return sameDisabled&&[...m.options].every(o=>!o.value||o.disabled===(o.dataset.account!=='2'))&&card.querySelector('.rr-settlement').hidden;})()`),'transfer excludes primary account, filters optional methods and hides unrelated fee selector');
   }
   check(await evaluate('uiErrors.length===0'),'no interaction JS errors '+tab);
   await send('Target.closeTarget',{targetId});
  }
  console.log('Finance allocation browser: '+checks+' PASS; offline synthetic HTML at 360/768/1280px.');
 } finally {chrome.kill();for(const item of pending.values()){clearTimeout(item.timer);item.reject(Error('Browser closed'));}pending.clear();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
