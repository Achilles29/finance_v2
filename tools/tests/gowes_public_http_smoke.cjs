'use strict';
// Live route/guard checks use unknown synthetic emails only; never claim a participant's voucher.
const assert = require('node:assert/strict');
(async () => {
  const base = process.env.GOWES_ORIGIN || 'https://namuacoffee.com';
  const adminBase = process.env.GOWES_ADMIN_ORIGIN || 'https://core.namuacoffee.com';
  const page = await fetch(base + '/gowes-vol9');
  assert.equal(page.status, 200);
  const html = await page.text();
  const csrf = html.match(/name="claim_csrf" value="([a-f0-9]{64})"/)[1];
  const cookie = page.headers.getSetCookie().map(value => value.split(';')[0]).join('; ');
  assert.match(page.headers.get('cache-control'), /no-store/);
  assert.equal((await fetch(base + '/gowes-vol9/claim')).status, 405);
  async function send(fields) {
    const body = new URLSearchParams(fields);
    const response = await fetch(base + '/gowes-vol9/claim', { method: 'POST', headers: { Cookie: cookie }, body });
    const data = await response.json();
    return { response, data };
  }
  const email = 'gowes-smoke-' + Date.now() + '@example.invalid';
  let reply = await send({ email, website: '' });
  assert.equal(reply.response.status, 403);
  reply = await send({ email, website: '', claim_csrf: csrf });
  assert.equal(reply.response.status, 422); assert.match(reply.data.message, /belum terdaftar/);
  reply = await send({ 'email[]': email, website: '', claim_csrf: csrf });
  assert.equal(reply.response.status, 422);
  reply = await send({ email, website: 'bot', claim_csrf: csrf });
  assert.equal(reply.response.status, 403);
  for (const route of ['/loyalty/gowes-participants', '/loyalty/gowes-participants/import']) {
    const response = await fetch(adminBase + route, { redirect: 'manual', method: route.endsWith('/import') ? 'POST' : 'GET' });
    assert([302, 303, 307].includes(response.status)); assert.match(response.headers.get('location'), /login/);
  }
  assert.equal((await fetch(base + '/docs/GOWESDAY.xlsx')).status, 404);
  assert.equal((await fetch(adminBase + '/gowes-vol9', { redirect: 'manual' })).status, 410);
  assert.equal((await fetch(adminBase + '/gowes-vol9/claim', { method: 'POST', redirect: 'manual' })).status, 410);
  console.log('PASS: public route, real JSON callback, CSRF, invalid email, honeypot, private admin/import, private participant XLSX. No participant claimed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
