'use strict';

const assert = require('node:assert/strict');
const { isMutationCommand, mutationContext } = require('../../wa-engine/group-mutation');

async function main() {
  for (const command of ['mutasi in TUNAI 10 modal', '/MUTASI OUT bank 10 biaya', '!input mutasi transfer a b 10 setor']) {
    assert.equal(isMutationCommand(command), true);
  }
  for (const command of ['menu', 'mutasi', 'mutasi kemarin', 'input mutasi', 'mutasi bantuan']) {
    assert.equal(isMutationCommand(command), false);
  }
  const now = Date.now();
  const msg = {
    key: { remoteJid: 'synthetic@g.us', participant: '100:4@lid', participantAlt: '62001@s.whatsapp.net', id: 'MSG_1' },
    messageTimestamp: Math.floor(now / 1000),
  };
  let calls = 0;
  const sock = { groupMetadata: async () => {
    calls++;
    return { participants: [{ id: '100@lid', phoneNumber: '62001@s.whatsapp.net', admin: 'admin' }] };
  } };
  const admin = await mutationContext(sock, msg, 'notify', now);
  assert.equal(admin.sender_is_admin, true);
  assert.equal(admin.sender_jid, '100@lid');
  assert.equal(admin.message_id, 'MSG_1');
  assert.equal(await mutationContext(sock, msg, 'append', now), null);
  assert.equal(await mutationContext(sock, { ...msg, messageTimestamp: now / 1000 - 901 }, 'notify', now), null);
  assert.equal(await mutationContext(sock, { ...msg, messageTimestamp: now / 1000 + 61 }, 'notify', now), null);
  assert.equal(await mutationContext(sock, { ...msg, messageTimestamp: undefined }, 'notify', now), null);
  assert.equal(await mutationContext(sock, { ...msg, key: { id: 'NO_SENDER' } }, 'notify', now), null);
  assert.equal(calls, 1, 'old/history/invalid messages are rejected before metadata or finance calls');
  const pnOnly = { groupMetadata: async () => ({ participants: [{ id: '62001@s.whatsapp.net', admin: 'superadmin' }] }) };
  assert.equal((await mutationContext(pnOnly, msg, 'notify', now)).sender_is_admin, true);
  for (const participants of [[], [{ id: '100@lid', admin: null }], [{ id: 'other@lid', admin: 'admin' }]]) {
    assert.equal((await mutationContext({ groupMetadata: async () => ({ participants }) }, msg, 'notify', now)).sender_is_admin, false);
  }
  await assert.rejects(mutationContext({ groupMetadata: async () => { throw Error('offline'); } }, msg, 'notify', now));
  console.log('PASS: WA mutation sender/admin/LID/history context; no network or real messages.');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
