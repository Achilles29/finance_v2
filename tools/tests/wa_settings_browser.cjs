'use strict';
// Real Chrome, real settings views/Bootstrap. Synthetic recipients and bot responses only.
const fs = require('node:fs'), path = require('node:path'), os = require('node:os');
const assert = require('node:assert/strict'), {spawn, execFileSync} = require('node:child_process');
const http = require('node:http');
const root = path.resolve(__dirname, '../..');
const views = JSON.parse(execFileSync('/www/server/php/81/bin/php', [path.join(__dirname, 'module_notifications_ui_smoke.php'), '--wa-page-fixture']));
const evidence = fs.mkdtempSync(path.join(os.tmpdir(), 'finance-wa-settings-browser-'));
const chrome = spawn('/usr/bin/google-chrome', ['--headless', '--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking', '--no-first-run', '--remote-debugging-pipe', '--user-data-dir=' + path.join(evidence, 'chrome')], {stdio:['ignore','ignore','pipe','pipe','pipe']});
let serial = 0, buffer = '', session, checks = 0, pageHtml;
const server = http.createServer((request, response) => {
  const page = request.url === '/wa/settings';
  response.writeHead(page ? 200 : 404, {'Content-Type':'text/html; charset=utf-8'});
  response.end(page ? pageHtml : '');
});
const pending = new Map(), errors = [];
let chromeErrors = '';
chrome.stderr.on('data', data => { chromeErrors = (chromeErrors + data).slice(-4000); });
chrome.on('exit', code => {
  for (const p of pending.values()) { clearTimeout(p.timer); p.reject(Error('Chrome exited ' + code + ': ' + chromeErrors)); }
  pending.clear();
});
chrome.stdio[4].on('data', bytes => {
  buffer += bytes.toString(); let end;
  while ((end = buffer.indexOf('\0')) >= 0) {
    const reply = JSON.parse(buffer.slice(0, end)); buffer = buffer.slice(end + 1);
    if (reply.method === 'Runtime.exceptionThrown') errors.push(reply.params.exceptionDetails.exception?.description || reply.params.exceptionDetails.text);
    const p = pending.get(reply.id);
    if (p) { pending.delete(reply.id); clearTimeout(p.timer); reply.error ? p.reject(Error(reply.error.message)) : p.resolve(reply.result); }
  }
});
function send(method, params = {}, sessionId) {
  return new Promise((resolve, reject) => {
    const id = ++serial, timer = setTimeout(() => { pending.delete(id); reject(Error('CDP timeout: ' + method + ': ' + chromeErrors)); }, 45000);
    pending.set(id, {resolve, reject, timer});
    chrome.stdio[3].write(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}) + '\0');
  });
}
const cdp = (method, params = {}) => send(method, params, session);
const evaluate = async expression => {
  const result = await cdp('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
  if (result.exceptionDetails) throw Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  return result.result.value;
};
const check = (ok, label) => { assert.ok(ok, label); checks++; };
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const markup = view => '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'
  + fs.readFileSync(path.join(root, 'assets/vendor/css/core.css'), 'utf8') + '</style></head><body><script>'
  + 'window.fixtureCalls=[];window.fetch=async(url,options={})=>{fixtureCalls.push({url,options});return {ok:true,json:async()=>({ok:true,status:"CONNECTED",running:false}),text:async()=>JSON.stringify({ok:true,status:"CONNECTED",running:false})};};'
  + '</script>' + view.replace(/<script\b[^>]*src="[^"]*"[^>]*><\/script>/g, '')
  + '<script>' + fs.readFileSync(path.join(root, 'assets/vendor/js/bootstrap.js'), 'utf8') + '</script></body></html>';
(async () => {
  try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const url = 'http://127.0.0.1:' + server.address().port + '/wa/settings';
    const {targetId} = await send('Target.createTarget', {url:'about:blank'});
    session = (await send('Target.attachToTarget', {targetId, flatten:true})).sessionId;
    await cdp('Page.enable'); await cdp('Runtime.enable');
    // Only local fixture HTTP is allowed; bot fetches are stubbed before page scripts run.
    await cdp('Network.enable');
    await cdp('Network.setBlockedURLs', {urls:['https://*', 'http://fixture.invalid/*']});
    await cdp('Emulation.setDeviceMetricsOverride', {width:1280,height:1000,deviceScaleFactor:1,mobile:false});
    pageHtml = markup(views.edit);
    await cdp('Page.navigate', {url}); await delay(450);
    check(await evaluate('document.querySelector(".tab-pane.active").id === "wa-notifications"'), 'notifications is default tab');
    check(await evaluate('document.getElementById("qr-panel").getClientRects().length === 0'), 'other tabs actually hidden');
    const key = 'notifications[SELF_ORDER][targets][]';
    check(await evaluate(`JSON.stringify(new FormData(document.querySelector('#module-notifications form')).getAll(${JSON.stringify(key)})) === '["group:1","group:2"]'`), 'saved multiple groups submit as array');
    await evaluate('document.getElementById("target-SELF_ORDER-group-2").click()');
    check(await evaluate(`JSON.stringify(new FormData(document.querySelector('#module-notifications form')).getAll(${JSON.stringify(key)})) === '["group:1"]'`), 'unchecking group excludes it from submission');
    check(await evaluate('document.getElementById("target-SELF_ORDER-group-3").disabled'), 'invalid group visible but disabled');
    for (const tab of ['connection','testing','technical','notifications']) {
      await evaluate(`document.getElementById('wa-tab-${tab}').click()`); await delay(80);
      check(await evaluate(`document.querySelector('.tab-pane.active').id === 'wa-${tab}' && document.querySelectorAll('.tab-pane.active').length === 1`), 'tab switches: ' + tab);
    }
    await evaluate('document.getElementById("wa-tab-technical").click()');
    await cdp('Page.reload', {ignoreCache:true}); await delay(450);
    check(await evaluate('document.querySelector(".tab-pane.active").id === "wa-technical"'), 'active tab restored on reload');
    await cdp('Page.navigate', {url}); await delay(450);
    check(await evaluate('document.querySelector(".tab-pane.active").id === "wa-technical"'), 'active tab restored after clean save redirect');
    await cdp('Emulation.setDeviceMetricsOverride', {width:390,height:844,deviceScaleFactor:1,mobile:true});
    for (const tab of ['notifications','connection','testing','technical']) {
      await evaluate(`document.getElementById('wa-tab-${tab}').click()`); await delay(80);
      check(await evaluate('document.documentElement.scrollWidth <= 391'), 'no horizontal overflow on phone: ' + tab);
    }
    await evaluate('document.getElementById("wa-tab-notifications").click()');
    const shot = await cdp('Page.captureScreenshot', {format:'png',captureBeyondViewport:true});
    fs.writeFileSync(path.join(evidence, 'wa-settings-mobile.png'), Buffer.from(shot.data, 'base64'));
    check(await evaluate('fixtureCalls.every(c => !c.options.method || c.options.method === "GET")'), 'tab navigation triggers no mutation or message send');
    pageHtml = markup(views.read);
    await cdp('Page.reload', {ignoreCache:true}); await delay(450);
    check(await evaluate('document.querySelectorAll("#module-notifications fieldset:disabled").length === 4'), 'read-only account cannot change recipient checklists');
    check(await evaluate('!document.getElementById("env-card") && !document.getElementById("btn-session-reset")'), 'technical mutations unavailable to read-only account');
    check(errors.length === 0, 'no script exceptions: ' + errors.join(';'));
    console.log(JSON.stringify({status:'PASS',checks,evidence,limits:'Synthetic HTML data and HTTP responses; real Chrome and local Bootstrap; no real bot messages.'}));
  } catch (error) { console.error(error); process.exitCode = 1; }
  finally { for (const p of pending.values()) clearTimeout(p.timer); chrome.kill('SIGTERM'); server.closeAllConnections(); server.close(); }
})();
