'use strict';
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const view = fs.readFileSync('application/views/pos/report_daily_sales.php','utf8');
const source = view.match(/<script>([\s\S]*?)<\/script>/)[1];
let handler, calls = [], allow = true;
let response = {ok:true,json:async()=>({ok:true,message:'PDF masuk antrean'})};
const button = {disabled:false,dataset:{url:'/pos/reports/daily-sales/notify?date=2026-09-23&outlet_id=2',csrf:'fixture'},addEventListener:(event,fn)=>{handler=fn;}};
const notice = {textContent:''};
vm.runInNewContext(source, {
  document:{getElementById:id=>id==='daily-sales-send-wa'?button:notice},
  window:{confirm:()=>allow}, AbortController,setTimeout,clearTimeout,
  fetch:async(url,options)=>{calls.push({url,options});if(response instanceof Error)throw response;return response;}
});
let checks = 0;
const check=(value,label)=>{assert.ok(value,label);checks++;};
(async()=>{
  allow=false;await handler();check(calls.length===0,'cancel sends nothing');
  allow=true;button.disabled=true;await handler();check(calls.length===0,'disabled sends nothing');button.disabled=false;
  let finish;response={ok:true,json:()=>new Promise(resolve=>{finish=resolve;})};
  const pending=handler();await Promise.resolve();await handler();
  check(button.disabled && calls.length===1,'double click blocked');
  finish({ok:true,message:'PDF masuk antrean'});await pending;
  check(!button.disabled && notice.textContent==='PDF masuk antrean','success restores button and confirms queue only');
  check(calls[0].url.endsWith('date=2026-09-23&outlet_id=2'),'displayed filters used, not unsaved filter edits');
  check(calls[0].options.method==='POST' && calls[0].options.credentials==='same-origin','authenticated POST');
  check(calls[0].options.headers['X-Pos-Transaction-CSRF']==='fixture','existing POS CSRF');
  response={ok:false,json:async()=>({ok:false,message:'<img src=x onerror=alert(1)>'})};await handler();
  check(notice.textContent==='<img src=x onerror=alert(1)>' && notice.innerHTML===undefined,'server message displayed as text');
  response={ok:false,json:async()=>{throw Error('raw HTML or secret');}};await handler();
  check(!notice.textContent.includes('secret') && notice.textContent.includes('Periksa status'),'non-JSON response safely explained');
  response=new Error('network');await handler();check(!button.disabled && notice.textContent.includes('tidak dikirim ganda'),'network recovery no silent resend');
  console.log(`Daily Sales JS: ${checks} checks PASS; no external requests.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
