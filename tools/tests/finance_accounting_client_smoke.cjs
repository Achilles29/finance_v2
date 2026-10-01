'use strict';
// Execute the real browser script with a small synthetic DOM adapter. No browser/network/DB.
// This tests event/payload behavior, not rendering, native form validation or visual layout.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const script=fs.readFileSync(path.resolve(__dirname,'../../assets/js/finance-accounting.js'),'utf8');
let checks=0;const check=(ok,label)=>{assert.ok(ok,label);checks++;};
class Element {
 constructor(data={}){this.dataset=data;this.listeners={};this.value='';this.textContent='';this.checked=false;this.disabled=false;this.hidden=false;this.children=[];}
 addEventListener(type,fn){(this.listeners[type]??=[]).push(fn);}
 async fire(type,target=this){for(const fn of this.listeners[type]??[])await fn({target,preventDefault(){}});await new Promise(r=>setImmediate(r));}
 setAttribute(){}
 appendChild(child){this.children.push(child);child.parent=this;}
 remove(){this.parent.children=this.parent.children.filter(c=>c!==this);}
 closest(selector){return selector==='[data-remove]'?this.removeButton?this:null:selector==='tr'?this.row:null;}
 querySelector(selector){return this.fields?.[selector]??null;}
 querySelectorAll(){return [];}
}
function fixture(mode='assistant',trailing=true){
 const nodes={},el=(id,data={})=>(nodes[id]=new Element(data));
 const root=el('root',{glUrl:'/accounting/post',glHome:'/accounting',glSetup:'/accounting/settings'+(trailing?'/':''),glCsrf:'a'.repeat(64)});
 el('gl-message');el('gl-debit');el('gl-credit');el('gl-difference');
 const forms=[],searches=[],filters=[],calls=[],confirms=[];let confirmResult=true,reloads=0,responseMode='reject';
 const form=mode==='assistant'?el('gl-entry',{kind:'MUTATION',source:'1',hash:'b'.repeat(64),key:'c'.repeat(32),autoCounter:'0'}):null;
 if(form){
  form.elements={date:{value:'2026-07-10'},reference:{value:'Synthetic'},memo:{value:'Verified'},cashflow_class:new Element()};
  form.button=new Element();form.fields={'[type="submit"]':form.button};
  const body=el('gl-lines');body.children=[new Element({fixedDebit:'0',fixedCredit:'7.25'})];
  body.querySelectorAll=selector=>selector==='tr'?body.children:body.children.filter(r=>r.editable);
  body.querySelector=selector=>body.querySelectorAll(selector)[0]??null;
  const createRow=()=>{const row=new Element();row.editable=true;row.fields={};for(const key of ['code','debit','credit']){row.fields[`[data-${key}]`]=new Element();row.fields[`[data-${key}]`].value=key==='code'?'':'0';}row.removeControl=new Element();row.removeControl.removeButton=true;row.removeControl.row=row;return row;};
  el('gl-line-template').content={cloneNode:createRow};el('gl-add');
  el('gl-suggestion',{code:'BAD_LEGACY_PREFILL',direction:'OUT',amount:'7.25'});
  const choice=el('gl-assistant-choice');choice.selectedOptions=[new Element()];
  const selected=new Element({account:'5200',flow:'OPERATING',hash:'d'.repeat(64),direction:'OUT',help:'Periksa biaya.',configured:'0'});
  choice.select=async()=>{choice.value='OPERATING_EXPENSE';choice.selectedOptions=[selected];await choice.fire('change');};
  el('gl-assistant-confirm');el('gl-assistant-apply');el('gl-assistant-state');el('gl-assistant-explanation');
 }else{
  const settings=new Element({kind:'mapping'});settings.button=mode==='readonly'?null:new Element();
  settings.fields={button:settings.button};settings.querySelector=selector=>selector==='[data-gl-feedback]'?settings.children[0]??null:settings.fields[selector]??null;
  settings.entries=[['scenario_code','OPERATING_EXPENSE'],['account_code','5200'],['expected_hash','d'.repeat(64)],['cashflow_class','OPERATING'],['is_enabled','1']];forms.push(settings);
  const search=new Element({glSearch:'mapping'});searches.push(search);
  for(const text of ['Biaya listrik','Pokok pinjaman']){const row=new Element({glFilter:'mapping'});row.textContent=text;filters.push(row);}
 }
 const context={document:{querySelector:()=>root,getElementById:id=>nodes[id]??null,createElement:()=>new Element(),querySelectorAll:s=>s==='.gl-setup-form'?forms:s==='[data-gl-search]'?searches:s==='[data-gl-filter]'?filters:[]},
  window:{confirm:message=>{confirms.push(message);return confirmResult;},location:{href:'https://fixture.invalid/accounting',assign:()=>{},reload:()=>{reloads++;}}},URL,
  fetch:async(url,options)=>{calls.push({url,...options});if(responseMode==='network')throw Error('Synthetic offline');return {ok:responseMode==='success',json:async()=>{if(responseMode==='invalid')throw Error('Synthetic invalid JSON');return {ok:responseMode==='success',message:'<img src=x> Synthetic rejected'};}};},
  FormData:class {constructor(f){this.values=f.entries;}[Symbol.iterator](){return this.values[Symbol.iterator]();}}};
 vm.runInNewContext(script,context);
 return {nodes,form,forms,searches,filters,calls,confirms,setConfirm:value=>{confirmResult=value;},setResponse:value=>{responseMode=value;},reloads:()=>reloads};
}
(async()=>{
 const f=fixture(),n=f.nodes;
 check(n['gl-lines'].children.length===2&&n['gl-lines'].querySelector('[data-edit-line]').fields['[data-code]'].value==='','no silent legacy/default prefill');
 await n['gl-assistant-apply'].fire('click');check(n['gl-assistant-state'].textContent.includes('centang'),'missing selection/confirmation refused');
 await n['gl-assistant-choice'].select();check(!n['gl-assistant-confirm'].checked&&n['gl-assistant-explanation'].textContent.includes('belum dikonfirmasi'),'selection explains unconfirmed default');
 const apply=async()=>{n['gl-assistant-confirm'].checked=true;await n['gl-assistant-apply'].fire('click');};
 const post=async()=>{await f.form.fire('submit');return JSON.parse(f.calls.at(-1).body);};
 await apply();check(n['gl-difference'].textContent==='Seimbang'&&n['gl-lines'].children.length===2,'assistant fills exact matching counter, preserves fixed cash');
 const payload=await post();check(payload.assistant_confirmed===true&&payload.assistant_scenario==='OPERATING_EXPENSE'&&payload.assistant_hash==='d'.repeat(64),'posting includes validated mapping provenance');
 check(payload.lines.length===1&&payload.lines[0].debit==='7.25'&&payload.lines[0].credit==='0'&&payload.cashflow_class==='OPERATING','counter amounts and class correct');
 check(f.calls[0].headers['X-Finance-Accounting-CSRF']==='a'.repeat(64)&&!f.form.button.disabled,'CSRF sent, retry enabled after rejection');
 check(n['gl-message'].textContent==='<img src=x> Synthetic rejected','server errors displayed as text');
 const again=await post();check(again.request_key===payload.request_key,'retry reuses request identity');
 await n['gl-lines'].fire('input');check(!(await post()).assistant_scenario,'manual amount input clears assistant claim');
 await apply();await n['gl-lines'].fire('change');check(!(await post()).assistant_scenario,'manual account select clears assistant claim');
 await apply();await f.form.elements.cashflow_class.fire('change');check(!(await post()).assistant_scenario,'flow edit clears assistant claim');
 await apply();await n['gl-add'].fire('click');check(!(await post()).assistant_scenario&&n['gl-lines'].children.length===3,'add line exits assistant');
 await apply();const row=n['gl-lines'].querySelector('[data-edit-line]');await n['gl-lines'].fire('click',row.removeControl);check(!(await post()).assistant_scenario&&n['gl-lines'].children.length===1,'remove counter exits assistant without removing fixed cash');
 await apply();n['gl-assistant-confirm'].checked=false;await n['gl-assistant-confirm'].fire('change');check(!(await post()).assistant_scenario,'uncheck exits assistant');
 await apply();await n['gl-assistant-choice'].select();check(!(await post()).assistant_scenario,'change scenario exits assistant');
 await apply();f.setConfirm(false);const count=f.calls.length;await f.form.fire('submit');check(f.calls.length===count,'cancel posting sends nothing');
 await n['gl-assistant-apply'].fire('click');check(n['gl-lines'].children.length===2,'cancel replacing entered lines preserves data');
 f.setConfirm(true);f.setResponse('invalid');await post();check(n['gl-message'].textContent.includes('Respons tidak dapat dibaca')&&!f.form.button.disabled,'invalid server JSON safely retries');
 for(const trailing of [true,false]){
  const s=fixture('settings',trailing),form=s.forms[0];await form.fire('submit');const call=s.calls[0];
  check(call.url==='/accounting/settings/mapping','normalized settings endpoint');
  check(call.method==='POST'&&call.headers['X-Finance-Accounting-CSRF']==='a'.repeat(64)&&JSON.parse(call.body).expected_hash.length===64,'settings request has CSRF/concurrency proof');
  check(form.children[0].textContent.includes('Synthetic rejected')&&!form.button.disabled,'settings inline error and retry');
  s.setResponse('invalid');await form.fire('submit');check(form.children[0].textContent.includes('Respons tidak terbaca'),'settings invalid JSON safe');
  s.setResponse('network');await form.fire('submit');check(!form.button.disabled,'settings network error can retry');
  s.searches[0].value='LISTRIK';await s.searches[0].fire('input');check(!s.filters[0].hidden&&s.filters[1].hidden,'case insensitive local mapping filter');
  s.searches[0].value='';await s.searches[0].fire('input');check(s.filters.every(row=>!row.hidden),'clearing search restores mappings');
  s.setConfirm(false);const count=s.calls.length;await form.fire('submit');check(s.calls.length===count,'cancel settings sends nothing');
  s.setConfirm(true);s.setResponse('success');await form.fire('submit');check(s.reloads()===1,'successful save reloads fresh hashes');
 }
 const read=fixture('readonly');await read.forms[0].fire('submit');check(read.calls.length===0,'readonly form cannot trigger network');
 console.log('Accounting client: '+checks+' PASS (synthetic DOM; not browser/layout UAT).');
})().catch(error=>{console.error(error);process.exitCode=1;});
