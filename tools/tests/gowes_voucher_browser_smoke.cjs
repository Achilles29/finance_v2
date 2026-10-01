'use strict';
// Public GET only. Every claim POST is intercepted with synthetic data.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const puppeteer = require(require.resolve('puppeteer-core', { paths: [root + '/../wa-bot', root + '/wa-engine'] }));
const destination = fs.mkdtempSync('/tmp/gowes-browser-');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_BIN || '/root/.cache/puppeteer/chrome/linux-144.0.7559.96/chrome-linux64/chrome', headless: true, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', entry => { if (entry.type() === 'error' && /Content Security Policy/.test(entry.text())) errors.push(entry.text()); });
    await page.setRequestInterception(true);
    let calls = 0;
    page.on('request', request => {
      if (request.method() === 'POST' && new URL(request.url()).pathname === '/gowes-vol9/claim') {
        calls++;
        const body = calls === 1 ? { ok: false, message: 'Email belum terdaftar sebagai peserta.' } : { ok: true, existing: calls > 2, voucher: {
          code: calls === 5 ? 'GW9-TEST-0000-0000-0001' : 'TEST8XYZ', percent: 15, status: calls === 4 ? 'REDEEMED' : 'OPEN',
          issued_at: new Date().toISOString(), expires_at: new Date(Date.now() + 604800000).toISOString(),
        } };
        return request.respond({ status: calls === 1 ? 422 : 200, contentType: 'application/json', body: JSON.stringify(body) });
      }
      request.continue();
    });
    await page.setViewport({ width: 1440, height: 1040, deviceScaleFactor: 1 });
    const response = await page.goto(process.env.GOWES_URL || 'https://namuacoffee.com/gowes-vol9', { waitUntil: 'networkidle0' });
    assert.equal(response.status(), 200);
    await page.evaluate(() => document.fonts.ready);
    assert(await page.$eval('#namua-logo', image => image.complete && image.naturalWidth > 0 && image.width >= 50), 'official Namua logo loads prominently');
    await page.screenshot({ path: destination + '/desktop.png', fullPage: true });
    for (const width of [768, 390, 320]) {
      await page.setViewport({ width, height: 844, deviceScaleFactor: 1 });
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no horizontal overflow at ' + width);
      await page.screenshot({ path: destination + '/mobile-' + width + '.png', fullPage: true });
    }
    await page.type('#email', 'synthetic@example.invalid');
    await page.click('#claim-button');
    await page.waitForSelector('#form-message:not([hidden])');
    assert.match(await page.$eval('#form-message', node => node.textContent), /belum terdaftar/);
    await page.click('#claim-button');
    await page.waitForSelector('#voucher-result:not([hidden])');
    assert.equal(await page.$eval('#voucher-code', node => node.textContent), 'TEST8XYZ');
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'voucher result fits mobile');
    assert(await page.$eval('#voucher-code', node => node.scrollWidth <= node.clientWidth), 'eight-character code is not clipped');
    assert(await page.$eval('.reward-brand img', image => image.complete && image.naturalWidth > 0), 'voucher preview includes the Namua logo');
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 1 });
    await page.screenshot({ path: destination + '/voucher-mobile.png', fullPage: true });
    const cdp = await page.createCDPSession();
    await cdp.send('Page.setDownloadBehavior', { behavior: 'allow', downloadPath: destination });
    await page.click('#download-button');
    let png;
    for (let i = 0; i < 60; i++) { png = fs.readdirSync(destination).find(name => name.startsWith('Voucher-') && name.endsWith('.png')); if (png) break; await sleep(100); }
    assert(png, 'download button creates PNG');
    const bytes = fs.readFileSync(path.join(destination, png));
    assert.equal(bytes.readUInt32BE(16), 1200); assert.equal(bytes.readUInt32BE(20), 760);
    const logoPresent = await page.evaluate(async png => {
      const image = new Image(); image.src = 'data:image/png;base64,' + png; await image.decode();
      const canvas = document.createElement('canvas'); canvas.width = 78; canvas.height = 78;
      const ctx = canvas.getContext('2d'); ctx.drawImage(image, 66, 62, 78, 78, 0, 0, 78, 78);
      const pixels = ctx.getImageData(0, 0, 78, 78).data;
      let maroonPixels = 0;
      for (let i = 0; i < pixels.length; i += 4) if (pixels[i] > 100 && pixels[i + 1] < 80 && pixels[i + 2] < 60) maroonPixels++;
      return maroonPixels > 100;
    }, bytes.toString('base64'));
    assert(logoPresent, 'downloaded PNG contains the official maroon Namua logo');
    await page.click('#another-email');
    await page.type('#email', 'synthetic@example.invalid'); await page.click('#claim-button');
    await page.waitForSelector('#voucher-result:not([hidden])');
    assert.match(await page.$eval('#result-message', node => node.textContent), /voucher yang sama/);
    await page.click('#another-email');
    await page.type('#email', 'synthetic@example.invalid'); await page.click('#claim-button');
    await page.waitForSelector('#voucher-result:not([hidden])');
    assert.match(await page.$eval('#voucher-status', node => node.textContent), /SUDAH DIGUNAKAN/);
    await page.click('#another-email');
    await page.type('#email', 'synthetic@example.invalid'); await page.click('#claim-button');
    await page.waitForSelector('#voucher-result:not([hidden])');
    await page.setViewport({ width: 320, height: 844, deviceScaleFactor: 1 });
    assert.equal(await page.$eval('#voucher-code', node => node.textContent), 'GW9-TEST-0000-0000-0001');
    assert(await page.$eval('#voucher-code', node => node.classList.contains('is-long-code') && node.scrollWidth <= node.clientWidth), 'previously issued long codes remain readable without truncation');
    await page.click('#download-button');
    const legacyName = 'Voucher-GOWES-VOL9-GW9-TEST-0000-0000-0001.png';
    for (let i = 0; i < 60 && !fs.existsSync(path.join(destination, legacyName)); i++) await sleep(100);
    assert(fs.existsSync(path.join(destination, legacyName)), 'legacy code can still be downloaded');
    assert.deepEqual(errors, []);
    console.log('PASS: desktop/tablet/mobile layouts, Namua logos in page/preview/PNG, short and legacy codes, invalid email, claim/reclaim, used state, downloads, no JS/CSP errors. No real vouchers issued.');
    console.log('Screenshots and PNG: ' + destination);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
