'use strict';

// Optional browser companion. Use an existing puppeteer-core installation;
// no packages are installed and no application/API/database is contacted.
// FINANCE_BROWSER_TEST_PUPPETEER=/path/to/puppeteer-core node tools/tests/sr_roastery_mobile_layout_browser.cjs
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFileSync } = require('node:child_process');
const puppeteer = require(process.env.FINANCE_BROWSER_TEST_PUPPETEER || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const css = ['assets/vendor/css/core.css', 'assets/css/theme-custom.css', 'assets/css/app.css']
  .map(file => fs.readFileSync(path.join(root, file), 'utf8').replace(/^\uFEFF/, '')).join('\n');

(async () => {
  const browser = await puppeteer.launch({
    executablePath: process.env.FINANCE_BROWSER_TEST_CHROME || '/usr/bin/google-chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking'],
  });
  let checks = 0;
  const screenshots = process.argv.includes('--screenshots')
    ? fs.mkdtempSync(path.join(os.tmpdir(), 'finance-sr-mobile-layout-')) : '';
  try {
    const page = await browser.newPage();
    await page.setRequestInterception(true);
    page.on('request', request => request.abort());
    for (const view of ['tx', 'nota', 'rincian', 'paid']) {
      const html = execFileSync(process.env.FINANCE_BROWSER_TEST_PHP || 'php', [
        path.join(__dirname, 'sr_roastery_mobile_layout_smoke.php'), '--render=' + view,
      ], { maxBuffer: 4 * 1024 * 1024 }).toString();
      for (const width of [360, 390, 600, 768, 1280]) {
        await page.setViewport({ width, height: 900, deviceScaleFactor: 1, isMobile: width < 768, hasTouch: width < 768 });
        await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
          + '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'">'
          + '<style>' + css + '</style></head><body><div class="layout-wrapper layout-content-navbar"><div class="layout-container">'
          + '<div class="layout-page"><div class="content-wrapper"><main class="container-xxl flex-grow-1 container-p-y">'
          + html + '</main></div></div></div></div></body></html>');
        await page.evaluate(() => {
          document.getAnimations().forEach(animation => {
            if (Number.isFinite(animation.effect.getComputedTiming().endTime)) animation.finish();
          });
        });
        const result = await page.evaluate(({ view, width }) => {
          const failures = [];
          const check = (ok, message) => { if (!ok) failures.push(message); };
          const viewport = document.documentElement.clientWidth;
          if (document.documentElement.scrollWidth > viewport + 1) {
            const overflow = [...document.querySelectorAll('main *')].filter(el => {
              const rect = el.getBoundingClientRect();
              return rect.right > viewport + 1 && !el.closest('.table-responsive, .po-table-wrap');
            }).slice(0, 5).map(el => el.tagName + '.' + el.className);
            check(false, 'page overflows horizontally: ' + overflow.join(', '));
          }
          const inside = element => {
            const rect = element.getBoundingClientRect();
            return rect.width > 0 && rect.left >= -1 && rect.right <= viewport + 1;
          };
          if (view === 'tx') {
            const actions = [...document.querySelectorAll('.pos-tx-header-actions a')];
            check(actions.length === 4 && actions.every(inside), 'invoice/navigation action clipped');
            check(actions.every(a => a.getBoundingClientRect().height >= 43), 'action touch target too small');
            for (let i = 1; i < actions.length; i++) {
              const a = actions[i - 1].getBoundingClientRect(), b = actions[i].getBoundingClientRect();
              check(b.top >= a.bottom - 1 || b.left >= a.right - 1, 'transaction actions overlap');
            }
          } else {
            const pane = document.querySelector('#po-tab-' + view);
            const table = pane.querySelector('.po-table');
            if (width < 768) {
              check(getComputedStyle(table.querySelector('tbody tr')).display === 'grid', 'mobile rows are not cards');
              for (const cell of table.querySelectorAll('tbody td')) {
                check(inside(cell), 'PO cell clipped: ' + cell.dataset.label);
                check(cell.scrollWidth <= cell.clientWidth + 1, 'PO field overflows: ' + cell.dataset.label);
                check(getComputedStyle(cell, '::before').content !== 'none', 'mobile field label hidden');
              }
              for (const control of table.querySelectorAll('a, button, select')) {
                check(inside(control), 'PO action clipped');
                check(control.getBoundingClientRect().height >= 43, 'PO touch target too small');
              }
            } else {
              check(getComputedStyle(table).display === 'table', 'desktop no longer a table');
              const wrap = pane.querySelector('.po-table-wrap');
              wrap.scrollLeft = wrap.scrollWidth;
              const lastCell = table.querySelector('tbody tr').lastElementChild;
              check(lastCell.getBoundingClientRect().right <= wrap.getBoundingClientRect().right + 1, 'last column unreachable by scrolling');
            }
          }
          return failures;
        }, { view, width });
        if (result.length) throw new Error(`${view} ${width}px: ${result.join('; ')}`);
        checks++;
        if (screenshots && width === 390) {
          const target = await page.$(view === 'tx' ? '.pos-report-hero' : '#po-tab-' + view);
          await target.screenshot({ path: path.join(screenshots, view + '-390.png') });
        }
      }
    }
    console.log(`PASS: ${checks} browser layouts, 360–1280px; synthetic views, no network or database.`);
    if (screenshots) console.log('Screenshots: ' + screenshots);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
