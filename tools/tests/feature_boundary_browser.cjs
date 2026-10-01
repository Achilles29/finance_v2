'use strict';
// Disposable HTTPS only. Cookie values are never command-line arguments or printed.
const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const base=process.argv[2],url=process.argv[3];
if(!/^\/var\/lib\/finance-portable-[a-f0-9]{16}$/.test(base)||!/^https:\/\/127\.0\.0\.1:\d+$/.test(url))throw Error('DISPOSABLE_ONLY');
const profile=fs.mkdtempSync(path.join(base,'browser-'));
const chrome=spawn('/usr/bin/google-chrome',['--headless','--no-sandbox','--disable-dev-shm-usage','--disable-background-networking','--no-first-run','--disable-sync','--disable-extensions','--ignore-certificate-errors','--remote-debugging-pipe','--user-data-dir='+profile],{stdio:['ignore','ignore','ignore','pipe','pipe']});
let id=0,buffer='',session;const pending=new Map();
chrome.stdio[4].on('data',b=>{buffer+=b.toString();let n;while((n=buffer.indexOf('\0'))>=0){const m=JSON.parse(buffer.slice(0,n));buffer=buffer.slice(n+1);const p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.timer);m.error?p.reject(Error('CDP_FAILURE')):p.resolve(m.result);}}});
const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const key=++id,timer=setTimeout(()=>{pending.delete(key);reject(Error('CDP_TIMEOUT'));},20000);pending.set(key,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id:key,method,params,...(sessionId?{sessionId}:{})})+'\0');});
const cdp=(m,p={})=>send(m,p,session);
const evalJs=async expression=>{const r=await cdp('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error('BROWSER_SCRIPT_FAILED');return r.result.value;};
(async()=>{try{
 await send('Browser.getVersion');const {targetId}=await send('Target.createTarget',{url:'about:blank'});session=(await send('Target.attachToTarget',{targetId,flatten:true})).sessionId;
 await cdp('Page.enable');await cdp('Network.enable');await cdp('Security.setIgnoreCertificateErrors',{ignore:true});
 const cookies=fs.readFileSync(path.join(base,'login-cookies'),'utf8').split('\n').filter(l=>l&&(!l.startsWith('#')||l.startsWith('#HttpOnly_'))).map(l=>{const p=l.replace(/^#HttpOnly_/,'').split('\t');return {name:p[5],value:p[6],url,secure:true,path:p[2]};});
 for(const c of cookies)await cdp('Network.setCookie',c);
 await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:900,deviceScaleFactor:1,mobile:false});
 await cdp('Page.navigate',{url:url+'/payroll/bonus'});
 let ready=false;for(let n=0;n<100;n++){ready=await evalJs(`!!document.querySelector('.card a.btn-primary[href$="/system/feature-access"]')`);if(ready)break;await new Promise(r=>setTimeout(r,100));}
 if(!ready)throw Error('LOCK_PAGE_NOT_RENDERED '+JSON.stringify(await evalJs('({url:location.pathname,title:document.title,text:document.body?.innerText.slice(0,180)})')));
 // Use actual sidebar toggles to expand groups; the lock must have a painted box.
 const visible=await evalJs(`(()=>{for(const t of document.querySelectorAll('#layout-menu .menu-toggle')){if(!t.closest('.menu-item')?.classList.contains('open'))t.click();}return [...document.querySelectorAll('#layout-menu .finance-feature-lock svg')].some(s=>{const r=s.getBoundingClientRect(),c=getComputedStyle(s);return r.width>=12&&r.height>=12&&c.visibility!=='hidden'&&c.display!=='none';});})()`);
 if(!visible)throw Error('SIDEBAR_LOCK_NOT_VISIBLE');
 console.log(JSON.stringify({status:'PASS',browser:'Chrome',locked_page:true,sidebar_svg_painted:true}));
 }catch(e){console.error('Feature browser: '+e.message);process.exitCode=1;}finally{for(const p of pending.values())clearTimeout(p.timer);chrome.kill('SIGTERM');}})();
