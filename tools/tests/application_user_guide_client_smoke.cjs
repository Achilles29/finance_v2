'use strict';
// Real client JS, synthetic DOM; no browser, network or commands executed.
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../assets/js/finance-user-guide.js'), 'utf8');
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; }
function fixture(mode) {
    const status = {textContent:''};
    const code = {textContent:'php example.php <placeholder>'};
    const button = () => ({hidden:true, dataset:{guideCopy:'ug-code-0'}, listeners:{}, addEventListener(type, fn) { this.listeners[type] = fn; }});
    const copy = button(), print = button(); let prints = 0;
    const writes = [];
    const root = {querySelector:()=>status, contains:node=>mode!=='outside' && node===code,
        querySelectorAll:selector=>selector==='[data-guide-print]'?[print]:[copy]};
    const context = {document:{getElementById:id=>id==='finance-user-guide' ? (mode==='absent'?null:root) : (mode==='missing'?null:code)},
        window:{print:()=>{prints++;}}, navigator:mode==='unsupported'?{}:{clipboard:{writeText:async text=>{if(mode==='denied')throw Error('denied');writes.push(text);}}}};
    vm.runInNewContext(source, context);
    return {copy,print,status,writes,prints:()=>prints};
}
(async function () {
    let f = fixture('ok');
    check(!f.copy.hidden && !f.print.hidden, 'progressive enhancement buttons');
    await f.copy.listeners.click();
    check(f.writes.length===1 && f.writes[0]==='php example.php <placeholder>', 'copies literal text');
    check(f.status.textContent.includes('Tidak ada perintah'), 'copy is not execution');
    check(f.prints()===0, 'copy does not print');
    f.print.listeners.click(); check(f.prints()===1, 'explicit print only');
    for (const mode of ['denied','unsupported']) {
        f = fixture(mode); await f.copy.listeners.click();
        check(f.writes.length===0 && f.status.textContent.includes('secara manual'), 'clipboard failure fallback '+mode);
    }
    for (const mode of ['outside','missing']) {
        f = fixture(mode); await f.copy.listeners.click();
        check(f.writes.length===0, 'cannot copy outside guide '+mode);
    }
    f = fixture('absent'); check(f.copy.hidden && f.print.hidden, 'unrelated pages untouched');
    console.log(`Application guide client: ${checks} PASS (synthetic DOM, not visual UAT).`);
}()).catch(error=>{console.error(error);process.exitCode=1;});
