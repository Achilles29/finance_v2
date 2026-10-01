'use strict';
// Render the real view with synthetic data; every browser request is intercepted.
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const puppeteer = require(require.resolve('puppeteer-core', { paths: [root + '/../wa-bot', root + '/wa-engine'] }));
const html = execFileSync('php', ['-r', `
function html_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$view = new class {
    public $load;
    public function render() {
        $this->load = new class { public function view($name, $data) {} };
        $promo_config = ['action_mode'=>'voucher_issue', 'fields'=>[],
            'columns'=>[['key'=>'voucher_code','label'=>'Kode']], 'primary_filter_key'=>'voucher_status',
            'primary_filter_options'=>[['value'=>'ALL','label'=>'Semua']],
            'data_url'=>'https://voucher-delete.test/data', 'delete_base_url'=>'https://voucher-delete.test/delete'];
        $filters = ['status'=>'ALL'];
        include 'application/views/loyalty/promo_index.php';
    }
};
$view->render();
`], { cwd: root, encoding: 'utf8' });

(async () => {
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_BIN || '/root/.cache/puppeteer/chrome/linux-144.0.7559.96/chrome-linux64/chrome', headless: true, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    const errors = [], confirms = [], alerts = [], calls = [];
    let allowConfirm = false, releaseDelete, failPlain = true;
    let rows = [
      { id: 1, voucher_code: 'TESTGW9A', voucher_status: 'OPEN', is_gowes_claim: 1 },
      { id: 2, voucher_code: 'TESTPLN2', voucher_status: 'OPEN', is_gowes_claim: 0 },
      { id: 3, voucher_code: 'TESTUSED', voucher_status: 'REDEEMED', is_gowes_claim: 1 },
    ];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', async dialog => {
      if (dialog.type() === 'confirm') { confirms.push(dialog.message()); await (allowConfirm ? dialog.accept() : dialog.dismiss()); }
      else { alerts.push(dialog.message()); await dialog.accept(); }
    });
    await page.setRequestInterception(true);
    page.on('request', async request => {
      const url = new URL(request.url());
      assert.equal(url.origin, 'https://voucher-delete.test', 'no real network/database requests');
      if (url.pathname === '/') return request.respond({ status: 200, contentType: 'text/html', body: html });
      if (url.pathname === '/data') return request.respond({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, rows, meta: { total: rows.length, page: 1, limit: 25, total_pages: 1 } }) });
      if (url.pathname.startsWith('/delete/')) {
        assert.equal(request.method(), 'POST');
        const id = Number(url.pathname.split('/').pop()); calls.push(id);
        if (id === 1) await new Promise(resolve => { releaseDelete = resolve; });
        if (id === 2 && failPlain) return request.respond({ status: 422, contentType: 'application/json', body: JSON.stringify({ ok: false, message: 'Voucher masih terkait data lain dan tidak bisa dihapus.' }) });
        rows = rows.filter(row => row.id !== id);
        return request.respond({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, id }) });
      }
      return request.respond({ status: 404, body: '' });
    });
    await page.goto('https://voucher-delete.test/', { waitUntil: 'networkidle0' });
    await page.waitForSelector('.btn-delete[data-id="1"]');
    assert(await page.$eval('.btn-delete[data-id="3"]', button => button.disabled), 'redeemed voucher cannot be deleted in UI');
    await page.click('.btn-delete[data-id="1"]');
    assert.equal(calls.length, 0, 'cancel confirmation does not submit deletion');
    assert.match(confirms[0], /peserta dapat klaim ulang/);
    allowConfirm = true;
    await page.click('.btn-delete[data-id="1"]');
    await page.waitForFunction(() => document.querySelector('.btn-delete[data-id="1"]').disabled);
    assert.equal(calls.length, 1);
    await page.$eval('.btn-delete[data-id="1"]', button => button.click());
    assert.equal(calls.length, 1, 'double click does not submit twice');
    releaseDelete();
    await page.waitForSelector('.btn-delete[data-id="1"]', { hidden: true });
    await page.click('.btn-delete[data-id="2"]');
    await page.waitForFunction(() => !document.querySelector('.btn-delete[data-id="2"]').disabled);
    assert.match(alerts[0], /terkait data lain/);
    assert(!confirms[confirms.length - 1].includes('GOWES'), 'ordinary voucher confirmation is unchanged');
    failPlain = false;
    await page.click('.btn-delete[data-id="2"]');
    await page.waitForSelector('.btn-delete[data-id="2"]', { hidden: true });
    assert.deepEqual(errors, []);
    console.log('PASS: real voucher view confirmation/cancel, used-voucher protection, POST, duplicate-click guard, error recovery and list refresh. No real voucher deleted.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
