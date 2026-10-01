'use strict';
// Execute the production client against a synthetic DOM. No HTTP or real records.
const fs=require('node:fs'), vm=require('node:vm'), assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../../assets/js/procurement-stock-review.js'),'utf8');
let checks=0;
function check(ok,label){ assert.ok(ok,label);checks++; }
function element(){ return {value:'',textContent:'',hidden:false,disabled:false,checked:false,children:[],listeners:{},
    append(...nodes){this.children.push(...nodes);},replaceChildren(){this.children=[];},
    addEventListener(type,fn){this.listeners[type]=fn;},scrollIntoView(){this.scrolled=true;}}; }
function fixture(verify=true,absent=false){
    const nodes={}; for(const key of ['status','rows','confirmation','contact','reason','confirmed','refresh'])nodes[key]=element();
    const panel=element(); panel.dataset={url:'/procurement/division-po-sr/stock-preview',csrf:'fixture-token',requestId:'12',verify:verify?'1':'0'};
    panel.querySelector=q=>nodes[q.replace('[data-stock-','').replace(']','')];
    const form=element(),lines=element(),hidden=element();
    const division={value:'1'},destination={value:'BAR'};
    form.elements={namedItem:name=>name==='division_id'?division:destination};
    lines.value=JSON.stringify([{item_id:1,qty_content_requested:500}]);
    const document={listeners:{},getElementById:id=>({procurementStockReview:absent?null:panel,divisionRequestForm:form,fieldLinesJson:lines,stockReviewJson:hidden})[id],
        createElement:element,addEventListener(type,fn){this.listeners[type]=fn;}};
    const requests=[],timers=new Map();let timerId=0;
    vm.runInNewContext(source,{document,window:{},AbortController,fetch:(url,options)=>new Promise((resolve,reject)=>{
        requests.push({url,options,resolve}); options.signal?.addEventListener('abort',()=>reject(Object.assign(new Error('aborted'),{name:'AbortError'})));
    }), setTimeout:(fn,ms)=>{timers.set(++timerId,{fn,ms});return timerId;},clearTimeout:id=>timers.delete(id)});
    const finish=async(data,index=requests.length-1,httpOk=true)=>{requests[index].resolve({ok:httpOk,json:async()=>data}); await new Promise(setImmediate);};
    const submit=()=>{const event={preventDefault(){this.blocked=true;},stopImmediatePropagation(){this.stopped=true;}};form.listeners.submit?.(event);return event;};
    const confirm=()=>{nodes.contact.value='Penanggung jawab BAR';nodes.reason.value='Kebutuhan event esok hari';nodes.confirmed.checked=true;};
    return {nodes,panel,form,lines,hidden,requests,document,destination,finish,submit,confirm,
        timer:(ms=450)=>{for(const [id,t] of timers) if(t.ms===ms){timers.delete(id);t.fn();break;}}};
}
const result=(overrides={})=>({ok:true,data:{has_materials:true,needs_confirmation:true,ready:true,token:'signed-token',checked_at:'fixture-time',rows:[{
    line:1,name:'<script>not executable</script>',requested:500,uom:'GR',needs_confirmation:true,
    division:{qty:1000,message:'Saldo sistem',latest_at:'fixture-time'},warehouse:{qty:null,message:'Belum diketahui',latest_at:null}}],...overrides}});
function content(el){return [el.textContent,...el.children.map(content)].join(' ');}
(async()=>{
    let f=fixture();check(f.requests.length===1,'initial preview');
    check(f.requests[0].options.method==='POST' && f.requests[0].options.credentials==='same-origin' && f.requests[0].options.headers['X-Procurement-Mutation-Csrf']==='fixture-token','same-origin CSRF request');
    check(f.submit().blocked,'cannot submit during preview');
    await f.finish(result());
    check(content(f.nodes.rows).includes('1.000 GR') && content(f.nodes.rows).includes('Belum diketahui'),'known stock formatted and unknown not zero');
    check(content(f.nodes.rows).includes('<script>not executable</script>'),'untrusted name assigned as literal text');
    check(f.submit().blocked && f.submit().stopped,'confirmation required before legacy submit');
    f.confirm();f.form.listeners.input();check(f.nodes.confirmed.checked,'contact input does not invalidate unchanged lines');
    check(!f.submit().blocked && JSON.parse(f.hidden.value).confirmed_with==='Penanggung jawab BAR','valid confirmation serialized');
    f.lines.value=JSON.stringify([{item_id:1,qty_content_requested:600}]);f.document.listeners['procurement-lines-changed']();
    check(!f.nodes.confirmed.checked && f.hidden.value==='' && f.submit().blocked,'line change invalidates proof');
    f.timer();await f.finish(result({token:'new-token'}));f.confirm();check(!f.submit().blocked && JSON.parse(f.hidden.value).token==='new-token','fresh confirmation after change');
    f.destination.value='BAR_EVENT';f.form.listeners.change();check(f.submit().blocked && f.nodes.contact.value==='','location change invalidates proof');
    f.timer();await f.finish({ok:false,message:'Lokasi tidak diizinkan'},undefined,false);
    check(f.nodes.status.textContent==='Lokasi tidak diizinkan' && f.submit().blocked,'scope/HTTP failure cannot approve');
    f=fixture();await f.finish(result({ready:false}));f.confirm();check(f.nodes.status.textContent.includes('2026-09-16a') && f.submit().blocked,'migration not applied visible and blocks only unsafe save');
    f=fixture();await f.finish(result({has_materials:false,needs_confirmation:false,ready:false,rows:[],token:''}));
    check(!f.submit().blocked && f.nodes.confirmation.hidden,'operational request does not need review schema');
    f=fixture();await f.finish(result({needs_confirmation:false}));check(!f.submit().blocked,'known zero no extra confirmation');
    f=fixture();f.nodes.refresh.listeners.click();check(f.requests.length===2,'manual refresh while request outstanding');
    await f.finish(result({token:'new'}),1);await f.finish(result({token:'old'}),0);f.confirm();f.submit();
    check(JSON.parse(f.hidden.value).token==='new' && !f.nodes.refresh.disabled,'late response cannot overwrite newer preview');
    f=fixture(false);check(!f.submit().blocked,'creating SUBMITTED request is not verification');
    f=fixture();f.timer(12000);await new Promise(setImmediate);
    check(!f.nodes.refresh.disabled && f.nodes.status.textContent.includes('terlalu lama'),'timeout recovers refresh with clear message');
    check(f.submit().blocked,'verification stays closed after timeout');
    f.nodes.refresh.listeners.click();await f.finish(result());f.confirm();check(!f.submit().blocked,'retry after timeout works');
    f=fixture(true,true);check(f.requests.length===0,'unrelated pages untouched');
    console.log(`Procurement stock review client: ${checks} PASS (synthetic DOM; not visual UAT).`);
})().catch(error=>{console.error(error);process.exitCode=1;});
