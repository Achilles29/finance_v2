'use strict';

/**
 * Network/DB/bootstrap/secret-free static/config caller smoke for Batch 50.
 */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const enginePath = path.join(root, 'wa-engine', 'index.js');
const templatePath = path.join(root, 'wa-engine', '.env.example');
const runbookPath = path.join(root, 'docs', 'wa_group_command_service_auth_runbook.md');
const source = fs.readFileSync(enginePath, 'utf8');
const template = fs.readFileSync(templatePath, 'utf8');
const runbook = fs.readFileSync(runbookPath, 'utf8');

let checks = 0;
const failures = [];

function check(condition, message) {
  checks += 1;
  if (!condition) failures.push(message);
}

function functionSource(name) {
  const marker = `async function ${name}(`;
  const start = source.indexOf(marker);
  if (start < 0) return '';
  const brace = source.indexOf('{', start);
  if (brace < 0) return '';
  let depth = 0;
  for (let i = brace; i < source.length; i += 1) {
    if (source[i] === '{') depth += 1;
    if (source[i] === '}') {
      depth -= 1;
      if (depth === 0) return source.slice(start, i + 1);
    }
  }
  return '';
}

async function main() {
  const callerSource = functionSource('buildGroupCommandReply');
  check(callerSource !== '', 'group command caller function is present');
  check(
    /const FINANCE_WA_ENGINE_COMMAND_TOKEN = String\(process\.env\.FINANCE_WA_ENGINE_COMMAND_TOKEN \|\| ''\)\.trim\(\);/.test(source),
    'credential is read from its dedicated environment variable with an empty fail-closed default'
  );
  check(
    source.includes("if (key === 'FINANCE_WA_ENGINE_COMMAND_TOKEN'")
      && source.includes("|| key === 'FINANCE_WA_ENGINE_API_TOKEN')"),
    'web-root .env loader cannot populate the process-only callback credential'
  );
  check(
    /const FINANCE_COMMAND_URL = String\(process\.env\.FINANCE_COMMAND_URL \|\| [^)]+\)\.trim\(\);/.test(source),
    'callback URL is read as a trimmed configuration value'
  );
  check(
    callerSource.indexOf('if (!FINANCE_WA_ENGINE_COMMAND_TOKEN)') < callerSource.indexOf('fetch('),
    'missing credential is rejected before fetch'
  );
  check(
    callerSource.includes('fetch(FINANCE_COMMAND_URL, {')
      && !callerSource.includes('?token=')
      && !callerSource.includes('encodeURIComponent'),
    'caller posts the exact configured URL without a token query string'
  );
  check(
    callerSource.includes("redirect: 'error'"),
    'caller rejects redirects instead of forwarding callback credentials'
  );
  check(
    callerSource.includes("'X-Finance-Group-Command-Token': FINANCE_WA_ENGINE_COMMAND_TOKEN")
      && !callerSource.includes('X-Sync-Token')
      && !callerSource.includes('SYNC_TOKEN'),
    'caller uses only the new dedicated callback header/token'
  );

  let missingFetchCalls = 0;
  const missingCaller = new Function(
    'fetch',
    'FINANCE_COMMAND_URL',
    'FINANCE_WA_ENGINE_COMMAND_TOKEN',
    `${callerSource}; return buildGroupCommandReply;`
  )(
    async () => {
      missingFetchCalls += 1;
      return { ok: true, status: 200, json: async () => ({ ok: true, message: 'unexpected' }) };
    },
    'https://finance.example.invalid/wa/api/group-command',
    ''
  );
  let missingRejected = false;
  try {
    await missingCaller('synthetic@g.us', 'menu');
  } catch (err) {
    missingRejected = true;
  }
  check(missingRejected && missingFetchCalls === 0, 'empty caller credential fails closed without network work');

  const calls = [];
  const validCaller = new Function(
    'fetch',
    'FINANCE_COMMAND_URL',
    'FINANCE_WA_ENGINE_COMMAND_TOKEN',
    `${callerSource}; return buildGroupCommandReply;`
  )(
    async (url, options) => {
      calls.push({ url, options });
      return { ok: true, status: 200, json: async () => ({ ok: true, message: 'synthetic menu' }) };
    },
    'https://finance.example.invalid/wa/api/group-command',
    'synthetic-service-auth-token'
  );
  const reply = await validCaller('synthetic-active-group@g.us', 'menu');
  const call = calls[0] || {};
  const options = call.options || {};
  check(
    calls.length === 1
      && call.url === 'https://finance.example.invalid/wa/api/group-command'
      && options.method === 'POST',
    'valid caller performs one POST to the exact configured URL'
  );
  check(
    JSON.stringify(options.headers) === JSON.stringify({
      'Content-Type': 'application/json',
      'X-Finance-Group-Command-Token': 'synthetic-service-auth-token',
    }),
    'valid caller sends JSON plus only the dedicated service-auth header'
  );
  check(
    options.body === JSON.stringify({
      group_jid: 'synthetic-active-group@g.us',
      command: 'menu',
    }) && options.redirect === 'error' && reply === 'synthetic menu',
    'valid caller preserves the group command payload and reply contract'
  );

  const redirectCalls = [];
  const redirectedUrl = 'https://credential-capture.example.invalid/callback';
  const redirectCaller = new Function(
    'fetch',
    'FINANCE_COMMAND_URL',
    'FINANCE_WA_ENGINE_COMMAND_TOKEN',
    `${callerSource}; return buildGroupCommandReply;`
  )(
    async function redirectingFetch(url, redirectOptions) {
      redirectCalls.push({ url, options: redirectOptions });
      if (url !== redirectedUrl) {
        if (redirectOptions.redirect === 'error') {
          throw new TypeError('redirect mode is set to error');
        }
        return redirectingFetch(redirectedUrl, redirectOptions);
      }
      return { ok: true, status: 200, json: async () => ({ ok: true, message: 'credential leaked' }) };
    },
    'https://finance.example.invalid/wa/api/group-command',
    'synthetic-redirect-secret'
  );
  let redirectRejected = false;
  try {
    await redirectCaller('synthetic-active-group@g.us', 'menu');
  } catch (err) {
    redirectRejected = err instanceof TypeError;
  }
  const crossOriginCalls = redirectCalls.filter((redirectCall) => redirectCall.url === redirectedUrl);
  const crossOriginCredentialLeaks = crossOriginCalls.filter(
    (redirectCall) => redirectCall.options?.headers?.['X-Finance-Group-Command-Token']
  );
  check(
    redirectRejected
      && redirectCalls.length === 1
      && crossOriginCalls.length === 0
      && crossOriginCredentialLeaks.length === 0,
    'redirected callback is rejected without following or leaking the credential cross-origin'
  );

  for (const status of [404, 403, 500]) {
    const httpCaller = new Function('fetch', 'FINANCE_COMMAND_URL', 'FINANCE_WA_ENGINE_COMMAND_TOKEN',
      `${callerSource}; return buildGroupCommandReply;`)(
      async () => ({ ok: false, status, json: async () => ({ message: 'private response body' }) }),
      'https://finance.example.invalid/wa/api/group-command', 'synthetic-service-auth-token'
    );
    let error = '';
    try { await httpCaller('synthetic@g.us', 'menu'); } catch (err) { error = err.message; }
    check(error.includes(`HTTP ${status}`) && !error.includes('private response body')
      && !error.includes('synthetic-service-auth-token'), `HTTP ${status} is observable without leaking response bodies or credentials`);
  }

  for (const body of [null, {}, { ok: true }, { ok: true, message: 123 }]) {
    const invalidCaller = new Function('fetch', 'FINANCE_COMMAND_URL', 'FINANCE_WA_ENGINE_COMMAND_TOKEN',
      `${callerSource}; return buildGroupCommandReply;`)(
      async () => ({ ok: true, status: 200, json: async () => body }),
      'https://finance.example.invalid/wa/api/group-command', 'synthetic-service-auth-token'
    );
    let rejected = false;
    try { await invalidCaller('synthetic@g.us', 'menu'); } catch { rejected = true; }
    check(rejected, 'invalid successful callback response is not silently discarded');
  }

  let abortTimer;
  let timerCleared = false;
  let receivedSignal;
  const timeoutCaller = new Function('fetch', 'FINANCE_COMMAND_URL', 'FINANCE_WA_ENGINE_COMMAND_TOKEN',
    'setTimeout', 'clearTimeout', `${callerSource}; return buildGroupCommandReply;`)(
    async (_url, opts) => {
      receivedSignal = opts.signal;
      abortTimer();
      throw new Error('transport aborted');
    },
    'https://finance.example.invalid/wa/api/group-command', 'synthetic-service-auth-token',
    (callback, ms) => { check(ms === 30000, 'callback has a bounded 30 second timeout'); abortTimer = callback; return 91; },
    (id) => { timerCleared = id === 91; }
  );
  let timedOut = false;
  try { await timeoutCaller('synthetic@g.us', 'menu'); } catch (err) { timedOut = err.message.includes('timeout'); }
  check(timedOut && receivedSignal.aborted && timerCleared, 'hung callback is aborted and its timer is always cleared');

  check(
    source.includes("const FINANCE_WA_ENGINE_API_TOKEN = String(process.env.FINANCE_WA_ENGINE_API_TOKEN || '').trim();")
      && source.includes("headers['x-finance-wa-engine-token']")
      && !source.includes('process.env.WA_TOKEN'),
    'separate Finance-to-engine credential does not alter the callback credential contract'
  );
  check(
    template.includes('FINANCE_WA_ENGINE_COMMAND_TOKEN=')
      && /^FINANCE_WA_ENGINE_COMMAND_TOKEN=\s*$/m.test(template)
      && template.includes('FINANCE_COMMAND_URL='),
    'non-secret engine template documents the callback variable names with an empty credential'
  );
  check(
    runbook.includes('PHP/FPM')
      && runbook.includes('wa-engine')
      && runbook.includes('di luar web root')
      && runbook.includes('restart')
      && runbook.includes('FINANCE_WA_ENGINE_COMMAND_TOKEN'),
    'runbook documents external provisioning and coordinated restart/cutover'
  );

  if (failures.length > 0) {
    process.stderr.write('WA engine group command service-auth smoke FAILED\n');
    failures.forEach((failure) => process.stderr.write(`- ${failure}\n`));
    process.exit(1);
  }
  process.stdout.write(`WA engine group command service-auth smoke passed (${checks} checks).\n`);
}

main().catch((err) => {
  process.stderr.write(`WA engine group command service-auth smoke FAILED: ${err.message}\n`);
  process.exit(1);
});
