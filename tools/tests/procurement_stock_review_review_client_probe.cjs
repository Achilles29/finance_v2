'use strict';
// PR-03 regression: reuse the synthetic DOM fixture, executing the actual client.
// Never contacts any server. Exit 1 means timeout recovery regressed.
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const smoke=fs.readFileSync(path.join(__dirname,'procurement_stock_review_client_smoke.cjs'),'utf8');
const marker=smoke.indexOf('(async()=>{');
if(marker<0)throw Error('Fixture structure changed; review this probe.');
const context={require,__dirname,console,AbortController,setImmediate};
vm.runInNewContext(smoke.slice(0,marker)+'\nglobalThis.makeReviewFixture=fixture;',context);
const fixture=context.makeReviewFixture();
// The fake fetch intentionally never resolves. Any application timeout/retry would
// be registered in the fixture's timer and would be executed here immediately.
fixture.timer(12000);
setImmediate(()=>{
    const recovered=!fixture.nodes.refresh.disabled && fixture.nodes.status.textContent.includes('terlalu lama');
    if(!recovered || !fixture.submit().blocked){
        console.error('FAIL PR-03 timeout must enable retry without allowing unverified submission.');process.exitCode=1;
    }else console.log('PASS PR-03 timeout recovery; retry enabled, verification still blocked. Synthetic DOM only.');
});
