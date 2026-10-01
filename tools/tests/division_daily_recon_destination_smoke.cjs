'use strict';

// Exercise the production click handler without a browser, database, or network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const view = fs.readFileSync(path.join(__dirname, '../../application/views/inventory/stock_opname_division_index.php'), 'utf8');
const start = view.indexOf('window.opnPostAdj = function (iid) {');
const end = view.indexOf('\n};', start);
assert.ok(start >= 0 && end > start, 'production posting handler exists');
const handler = view.slice(start, end + 3);

async function post(filter, destination) {
  const sent = [];
  const alerts = [];
  const profile = {
    division_id: 3, destination_type: destination, item_id: 196, material_id: 108,
    buy_uom_id: 6, content_uom_id: 9, profile_key: 'fixture-profile',
    identity_key: 'fixture-profile', system_qty_content: 1500,
  };
  const button = { innerHTML: 'Posting', disabled: false };
  const elements = {
    'row-fixture': { querySelector: () => ({ value: '1400' }) },
    'adjtype-fixture': { value: 'ADJUSTMENT_MINUS' },
    'adjbtn-fixture': button,
    opnForm: { querySelector: selector => ({ value: selector.includes('opname_date') ? '2026-09-25' : filter }) },
  };
  const context = {
    window: {}, profileMap: { fixture: profile }, el: id => elements[id] || null,
    round4: value => Math.round(value * 10000) / 10000,
    showAlert: (type, message) => alerts.push({ type, message }),
    ADJ_URL: '/synthetic/quick-adjust',
    fetch: async (url, options) => {
      sent.push({ url, options, payload: JSON.parse(options.body) });
      // Reject after capture: no posting, success-side UI, or stock mutation.
      return { status: 409, text: async () => JSON.stringify({ ok: false, message: 'Synthetic capture only' }) };
    },
  };
  vm.runInNewContext(handler, context);
  context.window.opnPostAdj('fixture');
  await new Promise(resolve => setImmediate(resolve));
  return { sent, alerts, profile, button };
}

for (const [filter, destination] of [
  ['EVENT', 'KITCHEN_EVENT'], ['EVENT', 'BAR_EVENT'], ['EVENT', 'ROASTERY_EVENT'],
  ['REGULER', 'KITCHEN'], ['REGULER', 'BAR'], ['REGULER', 'ROASTERY'],
  ['ALL', 'KITCHEN_EVENT'], ['ALL', 'KITCHEN'],
  ['KITCHEN_EVENT', 'KITCHEN_EVENT'], ['OTHER', 'OTHER'],
  // A filter changed without reloading must not replace a rendered row identity.
  ['KITCHEN', 'KITCHEN_EVENT'], ['EVENT', 'KITCHEN'],
]) {
  test(`${filter} filter posts exact ${destination} row identity`, async () => {
    const result = await post(filter, destination);
    assert.equal(result.sent.length, 1);
    const payload = result.sent[0].payload;
    assert.equal(payload.destination_type, destination);
    for (const field of ['division_id', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'identity_key', 'profile_key']) {
      assert.equal(payload[field], result.profile[field], `${field} remains row-owned`);
    }
    assert.equal(payload.physical_qty_content, 1400);
    assert.equal(payload.selisih, -100);
    assert.equal(result.button.disabled, false, 'failed request releases posting button');
  });
}

for (const destination of [undefined, '', 'ALL', 'REGULER', 'EVENT']) {
  test(`missing/aggregate destination ${String(destination)} never posts`, async () => {
    const result = await post('EVENT', destination);
    assert.equal(result.sent.length, 0);
    assert.match(result.alerts[0].message, /tujuan.*stok|muat ulang/i);
    assert.equal(result.button.disabled, false);
  });
}
