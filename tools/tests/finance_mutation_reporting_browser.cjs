'use strict';

// Real PHP views/CSS, synthetic data, blocked network, no application bootstrap.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const puppeteer = require(process.env.FINANCE_BROWSER_TEST_PUPPETEER || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const css = ['assets/vendor/css/core.css','assets/css/theme-custom.css','assets/css/app.css']
  .map(file => fs.readFileSync(path.join(root,file),'utf8').replace(/^\uFEFF/, '')).join('\n');
(async () => {
  const browser = await puppeteer.launch({executablePath:process.env.FINANCE_BROWSER_TEST_CHROME || '/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking']});
  const screenshots = process.argv.includes('--screenshots') ? fs.mkdtempSync(path.join(os.tmpdir(),'finance-mutation-ui-')) : '';
  let checks=0;
  const check=(ok,message)=>{if(!ok)throw new Error(message);checks++;};
  try {
    for(const view of ['estimation','mutations','revenue','cash']) {
      const html=execFileSync('php',[path.join(__dirname,'finance_mutation_reporting_smoke.php'),'--render='+view],{maxBuffer:4*1024*1024}).toString();
      check(!/Warning:|Fatal error:/.test(html),view+' PHP warning');
      for(const [,script] of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)) new vm.Script(script);
      for(const width of [360,390,768,1280]) {
        const page=await browser.newPage();
        const errors=[]; page.on('pageerror',error=>errors.push(error.message));
        await page.setRequestInterception(true);page.on('request',request=>request.abort());
        await page.setViewport({width,height:950,deviceScaleFactor:1});
        const stub=`window.testRequests=[];window.confirm=()=>true;window.bootstrap={Modal:class{show(){}hide(){}}};window.fetch=async(url,options)=>{window.testRequests.push({url,...options});return {ok:false,json:async()=>({ok:false,message:'Fixture: tidak ada data server yang diubah.'})};};`;
        await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style><script>'+stub+'</script></head><body><main class="container-xxl container-p-y">'+html+'</main></body></html>');
        await page.evaluate(()=>document.getAnimations().forEach(a=>{if(Number.isFinite(a.effect.getComputedTiming().endTime))a.finish();}));
        check(errors.length===0,view+' JS errors: '+errors.join(';'));
        const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth+1);
        check(!overflow,view+' viewport overflows '+width);
        if(view==='estimation') {
          check(await page.$$eval('.fin-est-kpi',els=>els.length===6),'six operational summaries');
          check(await page.$eval('.fin-est-table tbody tr',el=>el.children.length===8),'eight daily columns');
        }
        if(view==='mutations') {
          const result=await page.evaluate(()=>{
            const type=document.getElementById('mutation_type'),category=document.getElementById('report_category');
            const incoming=[...category.options].map(o=>o.value);
            type.value='OUT';type.dispatchEvent(new Event('change'));
            const outgoing=[...category.options].map(o=>o.value);
            category.value='PLATFORM_FEE';category.dispatchEvent(new Event('change'));
            document.getElementById('account_id').value='1';document.getElementById('amount').value='20';
            document.getElementById('reference_no').value='SETTLEMENT-FIXTURE';document.getElementById('settlement-confirmed').checked=true;
            document.getElementById('btn-save-mutation').click();
            return {incoming,outgoing,visible:!document.getElementById('settlement-confirm-wrap').classList.contains('d-none'),request:window.testRequests[0]};
          });
          check(result.incoming.includes('OWNER_CAPITAL')&&!result.incoming.includes('PLATFORM_FEE')&&result.outgoing.includes('PLATFORM_FEE')&&!result.outgoing.includes('OWNER_CAPITAL'),'manual category follows direction');
          check(result.visible&&result.request.headers['X-Purchase-Mutation-CSRF'].length===64&&JSON.parse(result.request.body).report_category==='PLATFORM_FEE','manual category/CSRF included');
          const key=JSON.parse(result.request.body).client_request_key;
          await page.waitForFunction(()=>!document.getElementById('btn-save-mutation').disabled);
          await page.evaluate(()=>document.getElementById('btn-save-mutation').click());
          check(await page.evaluate(key=>JSON.parse(window.testRequests[1].body).client_request_key===key,key),'retry retains same request identity');
        }
        if(view==='revenue') {
          const request=await page.evaluate(()=>{document.querySelector('.rr-save').click();return window.testRequests[0];});
          check(request&&request.headers['X-Finance-Reconciliation-CSRF'].length===64,'revenue CSRF included');
          const payload=JSON.parse(request.body);
          check(payload.actual_amount==='80,25'&&payload.report_category==='PLATFORM_FEE','revenue category and cents preserved');
        }
        if(view==='cash') {
          const request=await page.evaluate(()=>{const select=document.querySelector('[data-role="report-category"]');select.value='OPERATING_EXPENSE';select.dispatchEvent(new Event('change'));return window.testRequests[0];});
          check(request&&request.headers['X-Finance-Reconciliation-CSRF'].length===64&&JSON.parse(request.body).report_category==='OPERATING_EXPENSE','cash category auto-save includes CSRF');
        }
        check(errors.length===0,view+' interaction JS errors: '+errors.join(';'));
        if(screenshots&&(width===390||width===1280))await page.screenshot({path:path.join(screenshots,view+'-'+width+'.png'),fullPage:false});
        await page.close();
      }
    }
    console.log('PASS '+checks+' browser checks; all HTTP requests blocked.'+(screenshots?' Screenshots: '+screenshots:''));
  } finally {await browser.close();}
})().catch(error=>{console.error(error.message);process.exitCode=1;});
