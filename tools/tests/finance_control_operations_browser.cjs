'use strict';
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const puppeteer=require(process.env.FINANCE_BROWSER_TEST_PUPPETEER||'puppeteer-core');
const dir=process.argv[2],root=path.resolve(__dirname,'../..');
if(!/^\/tmp\/finance-control-ops-ui-[a-f0-9]{12}$/.test(dir||''))throw Error('Pass isolated operations UI directory');
const css=['assets/vendor/css/core.css','assets/css/theme-custom.css','assets/css/app.css'].map(f=>fs.readFileSync(path.join(root,f),'utf8')).join('\n');
const inline=html=>html.replace(/<script src="\/(assets\/js\/finance-(?:control-operations|settlement-picker)\.js)" defer><\/script>/g,(_,file)=>'<script>'+fs.readFileSync(path.join(root,file),'utf8')+'</script>');
const stub=`window.calls=[];window.confirm=()=>true;window.fetch=async(url,options={})=>{window.calls.push({url,...options});if(options.method==='POST')return{ok:false,json:async()=>({ok:false,message:'Fixture: operasi tidak dijalankan.'})};let data={ok:true,rows:[],page:1,pages:1,total:1};if(url.includes('settlements'))data.rows=[{id:1,revenue_date:'2026-09-14',payment_method_id:1,provider_reference:'OLD-205',method_name:'Platform fixture'}];if(url.includes('mutations'))data.rows=[{id:702,mutation_no:'M-702',mutation_date:'2026-09-14',account_name:'Fixture bank',mutation_type:'OUT',amount:80}];if(url.includes('charges'))data.rows=[{id:1,document_no:'INV-ONE',line_reference:'FEE-1',charge_date:'2026-09-14',category:'PLATFORM_FEE',direction:'OUT',amount:100,mutation_id:10}];return{ok:true,json:async()=>data};};`;
(async()=>{
 const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking']});let checks=0;const check=(ok,label)=>{if(!ok)throw Error(label);checks++;};
 try{
  for(const tab of ['settlement','cash-plan','approvals','settings'])for(const readonly of [false,true])for(const width of [360,390,768,1280]){
   const raw=fs.readFileSync(path.join(dir,tab+(readonly?'-readonly':'')+'.html'),'utf8');check(!/Warning:|Fatal error:/.test(raw),'PHP render '+tab);const html=inline(raw);
   for(const [,js]of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(js);
   const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.setViewport({width,height:960});await page.setRequestInterception(true);page.on('request',r=>r.abort());
   await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style><script>'+stub+'</script></head><body>'+html+'</body></html>');
   check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),'no page overflow '+tab+' '+width);
   check(await page.$$eval('.fc-tabs a',rows=>rows.length===6),'six compact tabs');
   if(readonly)check(await page.$$eval('[data-save],#fc-evidence-upload,[data-charge]',rows=>rows.length===0),'read-only hides all operations');
   if(!readonly&&tab==='settlement'){
    check(await page.$eval('[name="received_amount"]',e=>e.readOnly),'detailed aggregate readonly');
    await page.evaluate(()=>{const f=document.querySelector('[data-save="receipt"]');f.elements.reference_no.value='BANK-BROWSER';f.elements.amount.value='12.34';f.elements.notes.value='Fixture transfer';f.requestSubmit();});
    await page.waitForFunction(()=>window.calls.some(c=>c.url.endsWith('/receipt')));
    const call=await page.evaluate(()=>window.calls.find(c=>c.url.endsWith('/receipt'))),payload=JSON.parse(call.body);
    check(call.headers['X-Finance-Control-CSRF'].length===64 && payload.amount==='12.34' && payload.request_key.length===32,'receipt exact cents CSRF and retry identity');
    await page.evaluate(()=>{const b=document.querySelector('[data-charge]');if(b)b.click();});
    if(await page.$('[data-charge]'))check(await page.$eval('#fc-charge-form',f=>Number(f.elements.id.value)>0&&Number(f.elements.revision.value)>0),'charge edit keeps ID and revision');
    await page.evaluate(()=>{document.querySelector('#fc-charge-form button[type="reset"]').click();});
    await page.waitForFunction(()=>document.querySelector('#fc-charge-form').elements.id.value==='0');
    check(await page.$eval('#fc-charge-form',f=>f.elements.revision.value==='0'&&f.elements.request_key.value.length===32),'new charge resets persisted identity');
    await page.evaluate(()=>{const f=document.getElementById('fc-history-search');f.elements.q.value='OLD-205';f.requestSubmit();});
    await page.waitForFunction(()=>document.getElementById('fc-history-results').textContent.includes('OLD-205'));
    check(await page.$eval('#fc-history-results a',a=>a.href.includes('method_id=1')&&a.href.includes('date=')),'history links to exact case date/method');
   }
   if(!readonly&&tab==='cash-plan'){
    await page.evaluate(()=>document.getElementById('fc-mutation-search').requestSubmit());await page.waitForFunction(()=>document.getElementById('fc-mutation-options').options.length===2);
    await page.evaluate(()=>{const f=document.getElementById('fc-plan-link-form');f.elements.plan_id.selectedIndex=1;f.elements.plan_id.dispatchEvent(new Event('change'));f.elements.mutation_id.value='702';f.elements.notes.value='Fixture actual';f.requestSubmit();});
    await page.waitForFunction(()=>window.calls.some(c=>c.url.endsWith('/plan-link')));
    const payload=JSON.parse(await page.evaluate(()=>window.calls.find(c=>c.url.endsWith('/plan-link')).body));check(Number(payload.revision)>0&&payload.mutation_id==='702','link payload carries actual mutation ID and plan revision');
   }
   check(errors.length===0,'browser errors '+tab+' '+errors.join(';'));
   if(!readonly&&width===390)await page.screenshot({path:path.join(dir,tab+'-390.png'),fullPage:false});await page.close();
  }
  const page=await browser.newPage();await page.setRequestInterception(true);page.on('request',r=>r.abort());await page.setContent('<html><head><script>'+stub+'</script></head><body>'+inline(fs.readFileSync(path.join(dir,'picker.html'),'utf8'))+'</body></html>');
  await page.waitForFunction(()=>document.querySelector('[data-role="settlement-charge"]').options.length===2);
  check(await page.$eval('[data-role="settlement-control"]',e=>e.value==='1'&&e.selectedOptions[0].textContent.includes('OLD-205')),'historical selection not lost outside initial options');
  check(await page.$eval('[data-role="settlement-charge"]',e=>e.value==='1'&&e.selectedOptions[0].textContent.includes('INV-ONE')),'posted existing charge selection retained');await page.close();
  console.log('PASS '+checks+' operations browser checks; offline, no business writes. Screenshots '+dir);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
