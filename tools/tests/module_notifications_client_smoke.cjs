const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/module-notifications.js', 'utf8');
const clicks = [];
let response = {ok:true,text:async()=>JSON.stringify({ok:true,message:'Masuk antrean'})};
let calls = 0;
let allow = true;
let lastRequest;
let notice;
const button = {disabled:false,dataset:{moduleNotify:'WA',notifyUrl:'/fixture/12',notifyCsrf:'csrf-fixture'},parentElement:{querySelector:()=>notice,appendChild:(n)=>{notice=n;}}};
const context = vm.createContext({
  window:{confirm:()=>allow},
  document:{addEventListener:(name,fn)=>clicks.push(fn),createElement:()=>({dataset:{},setAttribute(){},textContent:''})},
  fetch:async(url,options)=>{calls++;lastRequest={url,options};if(response instanceof Error)throw response;return response;},
  AbortController,setTimeout,clearTimeout
});
vm.runInContext(source,context);vm.runInContext(source,context);
const event = {target:{closest:()=>button},preventDefault(){}};
(async()=>{
  assert.equal(clicks.length,1);
  allow=false;await clicks[0](event);assert.equal(calls,0);
  allow=true;
  let resolve;
  response={ok:true,text:()=>new Promise(r=>{resolve=r;})};
  const first=clicks[0](event);
  await Promise.resolve();
  assert.equal(button.disabled,true);
  await clicks[0](event);assert.equal(calls,1);
  resolve(JSON.stringify({ok:true,message:'Masuk antrean'}));await first;
  assert.equal(button.disabled,false);assert.equal(notice.textContent,'Masuk antrean');
  assert.equal(lastRequest.options.method,'POST');
  assert.equal(lastRequest.options.credentials,'same-origin');
  assert.equal(lastRequest.options.headers['X-Procurement-Mutation-Csrf'],'csrf-fixture');
  assert.equal(lastRequest.options.body,'{"channel":"WA"}');
  response={ok:false,text:async()=>JSON.stringify({message:'<img src=x onerror=alert(1)>'})};
  await clicks[0](event);assert.equal(notice.textContent,'<img src=x onerror=alert(1)>');assert.equal(notice.innerHTML,undefined);
  response=new Error('timeout');await clicks[0](event);
  assert.equal(notice.textContent,'timeout');assert.equal(button.disabled,false);
  response={ok:false,text:async()=>'<html>Login</html>'};await clicks[0](event);
  assert.equal(button.disabled,false);
  console.log('JS client: 15 checks PASS; synthetic network/DOM, no external sends.');
})().catch(error=>{console.error(error);process.exitCode=1;});
