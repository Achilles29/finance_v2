'use strict';
// Execute the actual inline functions with a tiny DOM fixture, no server/API.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../application/views/pos/cashier_index.php'), 'utf8');
function method(name) {
  const start = source.indexOf('function ' + name + '(');
  assert(start >= 0, name + ' exists');
  const end = source.indexOf('\n  }\n', start);
  assert(end > start, name + ' complete');
  const prefix = name === 'openCashierSession' ? 'async ' : '';
  return prefix + source.slice(start, end + 5).replace(/<\?php[\s\S]*?\?>/g, '/fixture');
}
const options = [
  {value:'', hidden:false, disabled:false, dataset:{}},
  {value:'1', hidden:false, disabled:true, dataset:{outletId:'1'}},
  {value:'2', hidden:false, disabled:false, dataset:{outletId:'1'}},
  {value:'3', hidden:false, disabled:false, dataset:{outletId:'2'}},
];
const terminal = {options, value:'1', get selectedOptions() { return options.filter(o => o.value === this.value); }};
const availability = {textContent:''};
let calls = 0, reloads = 0, alerts = 0, resolvePost;
const ctx = vm.createContext({
  launchOutlet:{value:'1'}, launchTerminal:terminal, launchOpeningCash:{value:'100000'}, launchNotes:{value:''},
  openButton:{disabled:false}, document:{getElementById: () => availability},
  confirmDailyReconGate:async () => true,
  postPosTransactionJson:() => { calls++; return new Promise(resolve => { resolvePost = resolve; }); },
  alert:() => { alerts++; }, window:{location:{reload:() => { reloads++; }}},
});
vm.runInContext('let cashierOpening = false;\n' + method('filterLaunchTerminalOptions') + '\n' + method('openCashierSession'), ctx);
let checks = 0;
function check(ok, label) { assert(ok, label); checks++; console.log('PASS: '+label); }
(async () => {
  ctx.filterLaunchTerminalOptions();
  check(terminal.value==='2', 'busy default automatically replaced with free terminal');
  check(!options[1].hidden && options[1].disabled, 'busy terminal remains visible but not selectable');
  check(options[3].hidden && !ctx.openButton.disabled, 'other outlet excluded; open enabled for free terminal');
  ctx.launchOutlet.value='2';ctx.filterLaunchTerminalOptions();
  check(terminal.value==='3', 'changing outlet selects only its free terminal');
  ctx.launchOutlet.value='1';options[2].disabled=true;ctx.filterLaunchTerminalOptions();
  check(terminal.value==='' && ctx.openButton.disabled, 'all busy clears selection and disables opening');
  check(availability.textContent.includes('Belum ada terminal tersedia'), 'no-terminal recovery guidance shown');
  options[2].disabled=false;ctx.filterLaunchTerminalOptions();
  const first=ctx.openCashierSession();const second=ctx.openCashierSession();
  await new Promise(resolve => setImmediate(resolve));
  check(calls===1 && ctx.openButton.disabled, 'double click sends exactly one open request');
  resolvePost({already_open:false});await Promise.all([first,second]);
  check(reloads===1 && alerts===1, 'successful open reloads once');
  ctx.postPosTransactionJson=async () => { calls++; throw new Error('TERMINAL_BUSY'); };
  await assert.rejects(ctx.openCashierSession(), /TERMINAL_BUSY/);
  check(!ctx.openButton.disabled, 'failed request releases local busy flag for retry');
  ctx.confirmDailyReconGate=async () => false;
  const before=calls;await ctx.openCashierSession();
  check(calls===before && !ctx.openButton.disabled, 'cancelled recon gate does not open session or strand button');
  terminal.value='1';await ctx.openCashierSession();
  check(calls===before, 'programmatically selected occupied option cannot submit');
  console.log(`PASS: ${checks} multi-cashier UI behavior checks.`);
})().catch(error => { console.error(error); process.exitCode=1; });
