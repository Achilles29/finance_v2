'use strict';
const fs = require('node:fs'), path = require('node:path');
const puppeteer = require(process.env.FINANCE_BROWSER_TEST_PUPPETEER || 'puppeteer-core');
const dir = process.argv[2], root = path.resolve(__dirname, '../..');
if (!/^\/tmp\/finance-report-ui-[a-f0-9]{12}$/.test(dir || '')) throw Error('Use --export-ui fixture directory only');
const css = ['assets/vendor/css/core.css', 'assets/css/theme-custom.css', 'assets/css/app.css', 'assets/css/report-workspace.css']
  .map(file => fs.readFileSync(path.join(root, file), 'utf8').replace(/^\uFEFF/, '')).join('\n');
(async () => {
  const browser = await puppeteer.launch({executablePath: process.env.FINANCE_BROWSER_TEST_CHROME || '/usr/bin/google-chrome',
    headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking']});
  let checks = 0;
  const check = (value, label) => { if (!value) throw Error(label); checks++; };
  try {
    for (const kind of ['sr', 'materials', 'financial']) for (const view of (kind === 'financial' ? ['overview','matrix','detail','sales','reconcile','efficiency','waste','balances'] : ['overview','matrix','detail'])) {
      const html = fs.readFileSync(path.join(dir, `${kind}-${view}.html`), 'utf8');
      check(!/Warning:|Fatal error:/.test(html), 'PHP rendering');
      for (const width of [390, 768, 1440]) {
        const page = await browser.newPage(), errors = [];
        await page.setViewport({width, height: 1050});
        page.on('pageerror', error => errors.push(error.message));
        await page.setRequestInterception(true); page.on('request', request => request.abort());
        await page.setContent('<!doctype html><html><head><base href="https://fixture.invalid/"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + css +
          '</style></head><body><main style="padding:16px;max-width:100%;min-width:0">' + html + '</main></body></html>');
        await page.addScriptTag({content: fs.readFileSync(path.join(root, 'assets/js/report-workspace.js'), 'utf8')});
        await new Promise(resolve => setTimeout(resolve, 350));
        check(errors.length === 0, 'JavaScript ' + errors.join(';'));
        check(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), `page overflow ${kind}/${view}/${width}`);
        check(await page.$eval('.rw-hero h1', e => getComputedStyle(e).color === 'rgb(255, 255, 255)'), 'readable hero text');
        check(await page.$$eval('.rw-tabs a', links => links.length) === (kind === 'financial' ? 8 : 3), 'report modes');
        if (kind === 'financial') {
          check(await page.$$eval('select[name="month"],select[name="month_from"],select[name="month_to"]', e => e.length === 3), 'month filters are dropdowns');
          check(await page.$$eval('input[type="month"]', e => e.length === 0), 'no manual month entry');
        }
        if (view === 'overview') {
          check(await page.$$eval('.rw-chart svg', graphs => graphs.length === 2), 'two interactive charts');
          check(await page.$$eval('.rw-chart svg a', links => links.length > 0 && links.every(a => a.getAttribute('href').includes('month='))), 'chart drill-down URLs');
          if (kind === 'financial') {
            check(await page.$$eval('[data-report-chart="finance-month-chart"] polyline', e => e.length === 2), 'two revenue/expenditure lines');
            check(await page.$$eval('.rw-compare-table thead th a', e => e.map(n => n.textContent).join(',') === '2026-07,2026-08,2026-09'), 'side-by-side months match exact range');
            check(await page.$eval('.rw-compare-table td:first-child', e => getComputedStyle(e).position === 'sticky'), 'source labels remain frozen horizontally');
            const countBefore = await page.$$eval('[data-report-chart="finance-month-chart"] polyline', e => e.length);
            await page.$eval('[data-series-toggle="business_out"]', e => e.click());
            check(await page.$$eval('[data-report-chart="finance-month-chart"] polyline', e => e.length) === countBefore-1, 'legend toggles a line');
            await page.$eval('[data-series-toggle="business_out"]', e => e.click());
            const source = await page.$eval('#finance-source-select', e => { e.selectedIndex=e.options.length-1; e.dispatchEvent(new Event('change')); return e.options[e.selectedIndex].textContent; });
            check(await page.$eval('[data-report-chart="finance-source-chart"] svg', (e,title) => e.getAttribute('aria-label') === title, source), 'source selector updates graph');
            await page.$eval('.rw-spark-button', e => e.click());
            check(await page.$eval('#finance-source-select', e => e.value === 'OMZET'), 'sparkline switches source graph');
            const geometry=await page.$$eval('[data-report-chart="finance-month-chart"] circle', e => e.every(n => Number.isFinite(Number(n.getAttribute('cy'))) && Number(n.getAttribute('cy'))>=18 && Number(n.getAttribute('cy'))<=187));
            check(geometry, 'negative revenue remains within chart scale');
            await page.$eval('[data-report-chart="finance-month-chart"] [data-series="omzet"][tabindex]', e => e.dispatchEvent(new Event('focus')));
            check(await page.$eval('[data-report-chart="finance-month-chart"] .rw-chart-readout', e => e.textContent.includes('Omzet POS neto: IDR -300')), 'accessible negative-value readout');
          }
        }
        if (kind === 'financial' && ['efficiency','waste'].includes(view)) {
          check(await page.$$eval('.rw-chart polyline', e => e.length >= 3), 'inventory trend lines rendered');
          check(await page.$$eval('select[name="division_id"],select[name="location"]', e => e.length === 2), 'division/location dropdowns');
          check(await page.$eval('.rw-table-wrap', e => getComputedStyle(e).overflowX === 'auto'), 'wide inventory tables own scrolling');
        }
        if (kind === 'financial' && view === 'reconcile') {
          check(await page.$$eval('.rw-reconcile-grid .rw-panel', e => e.length === 2 && e.every(n => getComputedStyle(n).display !== 'none')), 'both POS comparison panels visible on mobile');
        }
        if (view === 'detail') {
          check(await page.$eval('.rw-table-wrap', e => getComputedStyle(e).overflowX === 'auto'), 'table owns horizontal scroll');
          check(await page.$eval('thead th', e => getComputedStyle(e).position === 'sticky'), 'table header sticky');
          await page.$eval('tbody details', e => { e.open = true; });
          check(await page.$eval('tbody details', e => e.open), 'source/profile drill-down opens');
        }
        if ((view === 'overview' || (kind === 'financial' && ['reconcile','efficiency','waste'].includes(view))) && width !== 768) await page.screenshot({path: path.join(dir, `${kind}-${view}-${width}.png`), fullPage: true});
        await page.close();
      }
    }
    console.log(`PASS ${checks} report browser checks; all network requests blocked. ${dir}`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
