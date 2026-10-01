'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '../../assets/js/app.js'), 'utf8');
const start = source.indexOf("    document.querySelectorAll('[data-product-spreadsheet]')");
const end = source.indexOf('    initGlobalSelfOrderNotifier();', start);
assert.ok(start > 0 && end > start);
const code = source.slice(start, end);
function fixture({ confirm = true, reject = false, invalidJson = false, failed = false } = {}) {
  const requests = [];
  const status = { textContent: '', className: '' };
  const button = { disabled: false, textContent: 'Perbarui Spreadsheet', addEventListener: (_, callback) => { button.click = callback; } };
  const panel = { dataset: { syncUrl: '/master/product/spreadsheet/sync', csrf: 'a'.repeat(64) }, querySelector: selector => selector.includes('status') ? status : button };
  const context = { document: { querySelectorAll: () => [panel] }, TypeError, Error,
    window: { FinanceUI: { confirm: async () => confirm } },
    fetch: async (url, options) => {
      requests.push({ url, options });
      if (reject) throw new TypeError('network');
      return { ok: !failed, json: async () => {
        if (invalidJson) throw new Error('HTML response');
        return { ok: !failed, message: failed ? 'Google rejected' : '350 produk diperbarui', data: { captured_at: '2026-09-25 13:00:00', active_count: 253, inactive_count: 97 } };
      } };
    },
  };
  vm.runInNewContext(code, context);
  return { button, status, requests };
}
test('confirm, POST with scoped token, show results, and prevent double submit', async () => {
  const f = fixture();
  const first = f.button.click();
  const second = f.button.click();
  await Promise.all([first, second]);
  assert.equal(f.requests.length, 1);
  assert.equal(f.requests[0].options.method, 'POST');
  assert.equal(f.requests[0].options.headers['X-Master-Mutation-Csrf'], 'a'.repeat(64));
  assert.match(f.status.textContent, /253.*97/);
  assert.equal(f.button.disabled, false);
});
test('cancel does not send anything', async () => {
  const f = fixture({ confirm: false });
  await f.button.click();
  assert.equal(f.requests.length, 0);
  assert.equal(f.button.disabled, false);
});
for (const options of [{ reject: true }, { invalidJson: true }, { failed: true }]) {
  test('failure is visible and releases the button: ' + JSON.stringify(options), async () => {
    const f = fixture(options);
    await f.button.click();
    assert.match(f.status.className, /text-danger/);
    assert.equal(f.button.disabled, false);
    if (options.reject) assert.match(f.status.textContent, /belum pasti/);
    if (options.failed) assert.match(f.status.textContent, /Google rejected/);
  });
}
