'use strict';
// Run fixture first: php tools/tests/finance_control_workspace_smoke.php --export-ui
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const puppeteer=require(process.env.FINANCE_BROWSER_TEST_PUPPETEER||'puppeteer-core');
const dir=process.argv[2];if(!/^\/tmp\/finance-control-ui-[a-f0-9]{12}$/.test(dir||''))throw Error('Pass isolated UI_FIXTURE directory');
const root=path.resolve(__dirname,'../..');
const css=['assets/vendor/css/core.css','assets/css/theme-custom.css','assets/css/app.css'].map(f=>fs.readFileSync(path.join(root,f),'utf8')).join('\n');
(async()=>{
 const browser=await puppeteer.launch({executablePath:process.env.FINANCE_BROWSER_TEST_CHROME||'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking']});
 let checks=0;const check=(ok,label)=>{if(!ok)throw Error(label);checks++;};
 try{
  for(const tab of ['settlement','quality','cash-plan','profit-loss'])for(const readonly of [false,true]){
   const html=fs.readFileSync(path.join(dir,tab+(readonly?'-readonly':'')+'.html'),'utf8');check(!/Warning:|Fatal error:/.test(html),'PHP render '+tab);
   for(const [,js]of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(js);
   for(const width of [360,390,768,1280]){
    const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.setViewport({width,height:960});
    await page.setRequestInterception(true);page.on('request',r=>r.abort());
    const stub=`window.calls=[];window.fetch=async(url,options)=>{window.calls.push({url,...options});return{ok:false,json:async()=>({ok:false,message:'Fixture: penyimpanan tidak dijalankan.'})};};`;
    await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style><script>'+stub+'</script></head><body>'+html+'</body></html>');
    check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),'page overflow '+tab+' '+width);
    check(await page.$$eval('.fc-tabs a',e=>e.length===4),'four compact tabs');
    check(errors.length===0,'JS load '+errors.join(';'));
    if(readonly)check(await page.$$eval('[data-save], [data-plan]',e=>e.length===0),'read-only hides writes');
    if(!readonly&&['settlement','cash-plan'].includes(tab)){
      await page.evaluate(tab=>{
       const form=document.querySelector('[data-save]');
       if(tab==='cash-plan'){form.elements.namedItem('title').value='Fixture plan';form.elements.namedItem('amount').value='12.34';form.elements.namedItem('notes').value='Synthetic only';}
       form.requestSubmit();
      },tab);
      await page.waitForFunction(()=>window.calls.length===1);
      const call=await page.evaluate(()=>window.calls[0]);const body=JSON.parse(call.body);
      check(call.method==='POST'&&call.headers['X-Finance-Control-CSRF'].length===64,'scoped CSRF request');
      if(tab==='settlement'){
       check(typeof body.settlement_complete==='boolean'&&body.source_fingerprint.length===64,'snapshot and explicit completion flag');
       await page.evaluate(()=>{const f=document.querySelector('[data-save="void-adjustment"]');f.elements.namedItem('reason').value='Fixture correction';window.confirm=()=>false;f.requestSubmit();});
       check(await page.evaluate(()=>window.calls.length===1),'cancel confirmation never requests VOID');
       await page.evaluate(()=>{window.confirm=()=>true;document.querySelector('[data-save="void-adjustment"]').requestSubmit();});
       await page.waitForFunction(()=>window.calls.length===2);
       check(await page.evaluate(()=>window.calls[1].url.endsWith('/void-adjustment')&&Number(JSON.parse(window.calls[1].body).mutation_id)>0),'confirmed linked VOID uses guarded endpoint');
      }
      if(tab==='cash-plan'){
       check(body.amount==='12.34'&&body.request_key.length===32,'cents and retry identity');
       await page.waitForFunction(()=>!document.querySelector('[data-save] button').disabled);
       await page.evaluate(()=>document.querySelector('[data-plan]').click());
       check(await page.$eval('#fc-plan-form',f=>Number(f.elements.namedItem('id').value)>0&&Number(f.elements.namedItem('revision').value)>0),'edit loads persisted revision');
      }
      check(await page.$eval('#fc-message',e=>e.textContent.includes('Fixture:')),'safe actionable server message');
    }
    check(errors.length===0,'interaction errors '+errors.join(';'));
    if(!readonly&&(width===390||width===1280))await page.screenshot({path:path.join(dir,tab+'-'+width+'.png'),fullPage:false});
    await page.close();
   }
  }
  console.log('PASS '+checks+' Finance control browser checks. All network blocked. Screenshots: '+dir);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
