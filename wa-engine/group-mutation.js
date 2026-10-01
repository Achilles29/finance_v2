'use strict';

function isMutationCommand(command) {
  return /^(?:input\s+)?mutasi\s+(in|out|transfer)\b/i.test(
    String(command || '').trim().replace(/^[!\/.#]+/, '').trim()
  );
}

function normalizeParticipant(jid) {
  return String(jid || '').replace(/:\d+(?=@)/, '');
}

async function mutationContext(sock, message, upsertType, now = Date.now()) {
  const timestamp = Number(message.messageTimestamp);
  // Reconnecting/history sync must not execute old financial instructions.
  if (upsertType !== 'notify' || !Number.isFinite(timestamp)
    || timestamp < now / 1000 - 900 || timestamp > now / 1000 + 60) return null;
  const sender = normalizeParticipant(message.key?.participant);
  const alternate = normalizeParticipant(message.key?.participantAlt);
  if (!sender || !message.key?.id) return null;

  let timer;
  let metadata;
  try {
    metadata = await Promise.race([
      sock.groupMetadata(message.key.remoteJid),
      new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error('Verifikasi admin grup timeout.')), 10000);
      }),
    ]);
  } finally {
    clearTimeout(timer);
  }
  const participant = (metadata.participants || []).find(person =>
    [person.id, person.lid, person.phoneNumber].some(jid => {
      const id = normalizeParticipant(jid);
      return id && (id === sender || (alternate && id === alternate));
    })
  );
  return {
    sender_jid: sender,
    sender_is_admin: participant?.admin === 'admin' || participant?.admin === 'superadmin',
    message_id: String(message.key.id),
    message_timestamp: timestamp,
  };
}

module.exports = { isMutationCommand, mutationContext };
