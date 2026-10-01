'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const views = path.join(__dirname, '../../application/views/purchase');

function section(source, first, last) {
  const start = source.indexOf(first);
  const end = source.indexOf(last, start + first.length);
  assert.ok(start >= 0 && end > start, `source block ${first}`);
  return source.slice(start, end);
}

function fixture(scope, options = {}) {
  const material = scope === 'material';
  const prefix = material ? 'pmd' : 'pwd';
  const source = fs.readFileSync(path.join(views, `inventory_${scope}_daily_index.php`), 'utf8');
  const script = section(source, '  function buildAdjustPayload(){', `  var ${prefix}ActionEl`);
  const sent = [], redirects = [], messages = [];
  const elements = {};
  const el = name => elements[prefix + name] ||= { value: '', disabled: false, textContent: '' };
  el('AdjustAction').value = 'PLUS';
  el('QtyInput').value = '2';
  el('AutoCostDisplay').value = '20';
  const context = {
    URL, document: { getElementById: id => elements[id] ||= { value: '' } },
    window: { location: { href: 'https://fixture.invalid/matrix', assign: url => redirects.push(url) } },
    adjustContext: {
      date: '2026-09-25', factor: 1500, defaultUnitCostInput: material ? 20 : 30000,
      row: { item_id: 196, material_id: 108, division_id: 3, destination_type: 'KITCHEN_EVENT',
        buy_uom_id: 6, content_uom_id: 9, profile_key: 'fixture-profile', profile_content_per_buy: 1500 },
      group: { division_id: 3, destination_type: 'EVENT' },
    },
    adjustActionMeta: { PLUS: {}, MINUS: {}, WASTE: {}, SPOIL: {}, PROCESS_LOSS: {} },
    stockAdjustmentCsrfToken: options.noToken ? '' : 'a'.repeat(64),
    adjustmentStoreUrl: '/adjustment/store', adjustmentIndexUrl: '/adjustment/' + scope,
    showAdjustAlert: (ok, message) => messages.push({ ok, message }), showToast: () => {},
    setSubmitButtonLoading: (button, text) => { button.disabled = true; button.textContent = text; },
    clearSubmitButtonLoading: button => { button.disabled = false; },
    requestJson: async (url, request) => {
      sent.push({ url, request, payload: JSON.parse(request.body) });
      if (options.reject) throw new Error('Synthetic request rejection');
      return { ok: true, id: 123, adjustment_no: 'ADJ-FIXTURE' };
    },
  };
  vm.runInNewContext(script, context);
  return { context, sent, redirects, messages, button: el('AdjustSubmit') };
}

for (const scope of ['material', 'warehouse']) {
  test(`${scope}: correct quantity/cost units and exact profile`, () => {
    const { context } = fixture(scope);
    const payload = context.buildAdjustPayload();
    assert.equal(payload.auto_post, undefined);
    assert.equal(payload.lines[0].qty_adjustment_plus_content, scope === 'material' ? 2 : 3000);
    assert.equal(payload.lines[0].unit_cost, 20);
    assert.equal(payload.lines[0].profile_key, 'fixture-profile');
    assert.equal(payload.lines[0].content_uom_id, 9);
    if (scope === 'material') assert.equal(payload.destination_type, 'KITCHEN_EVENT');
    else assert.equal(payload.stock_scope, 'WAREHOUSE');
  });

  test(`${scope}: save once with CSRF, then open existing password-protected posting page`, async () => {
    const f = fixture(scope);
    f.context.submitAdjust();
    f.context.submitAdjust();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(f.sent.length, 1, 'double click does not create another draft or bypass Post');
    assert.equal(f.sent[0].url, '/adjustment/store');
    assert.equal(f.sent[0].request.headers['X-Stock-Adjustment-Csrf'], 'a'.repeat(64));
    assert.equal(f.redirects.length, 1);
    const next = new URL(f.redirects[0]);
    assert.equal(next.pathname, '/adjustment/' + scope);
    assert.equal(next.searchParams.get('q'), 'ADJ-FIXTURE');
    assert.equal(next.searchParams.get('month'), '2026-09');
    assert.ok(f.messages.some(m => /Stok belum berubah/.test(m.message)));
    assert.ok(!f.messages.some(m => /berhasil diposting/.test(m.message)));
    assert.equal(f.button.disabled, true, 'saved draft cannot be recreated while navigating');
  });

  test(`${scope}: missing CSRF fails closed before saving`, async () => {
    const f = fixture(scope, { noToken: true });
    f.context.submitAdjust();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(f.sent.length, 0);
    assert.equal(f.redirects.length, 0);
    assert.ok(f.messages.some(m => /Muat ulang/.test(m.message)));
  });

  test(`${scope}: server rejection stays visible and allows retry`, async () => {
    const f = fixture(scope, { reject: true });
    f.context.submitAdjust();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(f.redirects.length, 0);
    assert.equal(f.button.disabled, false);
    assert.ok(f.messages.some(m => /Synthetic request rejection/.test(m.message)));
  });
}

test('material matrix rejects ambiguous destinations instead of guessing a stock bucket', () => {
  for (const destination of ['', 'EVENT', 'REGULER', 'ALL']) {
    const f = fixture('material');
    f.context.adjustContext.row.destination_type = destination;
    assert.throws(() => f.context.buildAdjustPayload(), /Tujuan stok profil/);
  }
});

test('adjustment draft locks division/destination until every line is removed', () => {
  const source = fs.readFileSync(path.join(views, 'stock_adjustment_index.php'), 'utf8');
  const script = section(source, '  const renderDraftLines = () => {', '  const fetchJson');
  const c = { isDivisionScope: true, formDivisionEl: {}, formDestinationEl: {}, lines: [{}],
    document: { getElementById: () => null }, draftTableBody: null, fmtMoney: String };
  vm.runInNewContext(script + '\nthis.render = renderDraftLines;', c);
  c.render();
  assert.equal(c.formDivisionEl.disabled, true);
  assert.equal(c.formDestinationEl.disabled, true);
  c.lines.length = 0;
  c.render();
  assert.equal(c.formDivisionEl.disabled, false);
  assert.equal(c.formDestinationEl.disabled, false);
});

test('changing adjustment context clears selections and discards in-flight search results', async () => {
  const source = fs.readFileSync(path.join(views, 'stock_adjustment_index.php'), 'utf8');
  const script = section(source, '  function resetStockAdjustmentSelection()', "  selectedCard?.addEventListener").replace(/<\?php[\s\S]*?\?>/g, '/synthetic/search');
  let finish;
  const c = { URLSearchParams, clearTimeout: () => {}, searchTimer: null,
    profileLookupToken: 0, searchLookupToken: 0, selectedItem: { id: 1 }, selectedProfileOptions: [{}],
    profilePickerBaseItem: { id: 1 }, profilePickerOptions: [{}],
    currentSearchItems: [{}], searchInput: { value: 'kecap' }, searchResults: { innerHTML: 'old' },
    closeProfilePicker: () => {}, renderSelectedItem: () => {}, clearLineInputs: () => {},
    isDivisionScope: true, stockScope: 'DIVISION', document: { getElementById: () => ({ value: '3' }) },
    fetchJson: () => new Promise(resolve => { finish = resolve; }),
  };
  vm.runInNewContext(script + '\nthis.search = performSearch;', c);
  const pending = c.search();
  c.resetStockAdjustmentSelection();
  finish({ ok: true, items: [{ id: 196 }] });
  await pending;
  assert.equal(c.selectedItem, null);
  assert.equal(c.selectedProfileOptions.length, 0);
  assert.equal(c.profilePickerBaseItem, null);
  assert.equal(c.profilePickerOptions.length, 0);
  assert.equal(c.currentSearchItems.length, 0);
  assert.equal(c.searchResults.innerHTML, '');
  assert.equal(c.profileLookupToken, 1);
  assert.ok(source.includes("formDestinationEl?.addEventListener('change', resetStockAdjustmentSelection)"));
});
