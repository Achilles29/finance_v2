'use strict';

/**
 * Batch 52 wa-engine internal service-auth smoke.
 *
 * Only pure functions extracted from source are executed with synthetic
 * request/config objects. The engine is not required, no module bootstrap or
 * database/network access occurs, and no credential value is printed.
 */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const enginePath = path.join(root, 'wa-engine', 'index.js');
const templatePath = path.join(root, 'wa-engine', '.env.example');
const runbookPath = path.join(root, 'docs', 'wa_engine_internal_service_auth_runbook.md');
const groupRunbookPath = path.join(root, 'docs', 'wa_group_command_service_auth_runbook.md');
const source = fs.readFileSync(enginePath, 'utf8');
const template = fs.readFileSync(templatePath, 'utf8');
const runbook = fs.readFileSync(runbookPath, 'utf8');
const groupRunbook = fs.readFileSync(groupRunbookPath, 'utf8');
const normalizedRunbook = runbook.replace(/\s+/g, ' ');
const normalizedGroupRunbook = groupRunbook.replace(/\s+/g, ' ');

let checks = 0;
const failures = [];

function check(condition, message) {
  checks += 1;
  if (!condition) failures.push(message);
}

function functionSource(name) {
  const marker = `function ${name}(`;
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

const authorizeSource = functionSource('authorizeInternalRequest');
const loaderSource = functionSource('loadEnvironmentFile');
check(authorizeSource !== '', 'internal authorization function is present');
check(loaderSource !== '', '.env loader function is present');

const authorize = new Function(`${authorizeSource}; return authorizeInternalRequest;`)();
const expected = 'synthetic-batch52-service-token';
const request = (headers = {}) => ({ headers });
const internalUrl = (suffix = '') => new URL(`http://127.0.0.1/internal/status${suffix}`);

check(
  authorize(request({ 'x-finance-wa-engine-token': expected }), internalUrl(), expected) === true,
  'valid dedicated header authorizes an internal request'
);
check(
  authorize(request({ 'x-finance-wa-engine-token': expected }), internalUrl(), '') === false,
  'empty process credential fails closed'
);
check(authorize(request(), internalUrl(), expected) === false, 'missing header is rejected');
check(
  authorize(request({ 'x-finance-wa-engine-token': '' }), internalUrl(), expected) === false,
  'empty header is rejected'
);
check(
  authorize(request({ 'x-finance-wa-engine-token': 'synthetic-wrong-token' }), internalUrl(), expected) === false,
  'wrong dedicated header is rejected'
);
check(
  authorize(request({ 'x-sync-token': expected }), internalUrl(), expected) === false,
  'legacy X-Sync-Token header cannot authenticate'
);
check(
  authorize(
    request({ 'x-finance-wa-engine-token': expected, 'x-sync-token': 'legacy' }),
    internalUrl(),
    expected
  ) === false,
  'legacy header presence is rejected even beside a valid new header'
);
check(
  authorize(request({ 'x-finance-wa-engine-token': expected }), internalUrl('?token=legacy'), expected) === false,
  'legacy token query is rejected even beside a valid new header'
);
check(
  authorize(request(), internalUrl(`?token=${encodeURIComponent(expected)}`), expected) === false,
  'legacy query cannot authenticate with the correct credential value'
);

const syntheticProcess = {
  env: {
    FINANCE_WA_ENGINE_API_TOKEN: 'process-api-token',
    FINANCE_WA_ENGINE_COMMAND_TOKEN: 'process-callback-token',
    DB_HOST: 'process-db-host',
  },
};
const syntheticFs = {
  existsSync: () => true,
  readFileSync: () => [
    'FINANCE_WA_ENGINE_API_TOKEN=file-api-token',
    'FINANCE_WA_ENGINE_COMMAND_TOKEN=file-callback-token',
    'WA_PORT=3999',
    'DB_HOST=file-db-host',
    'DB_NAME=synthetic_db',
  ].join('\n'),
};
const loadEnvironmentFile = new Function(
  'fs',
  'path',
  'process',
  '__dirname',
  `${loaderSource}; return loadEnvironmentFile;`
)(syntheticFs, path, syntheticProcess, '/synthetic/wa-engine');
loadEnvironmentFile();
check(
  syntheticProcess.env.FINANCE_WA_ENGINE_API_TOKEN === 'process-api-token'
    && syntheticProcess.env.FINANCE_WA_ENGINE_COMMAND_TOKEN === 'process-callback-token',
  'web-root .env cannot populate or replace either process-only credential'
);
check(
  syntheticProcess.env.WA_PORT === '3999'
    && syntheticProcess.env.DB_HOST === 'process-db-host'
    && syntheticProcess.env.DB_NAME === 'synthetic_db',
  'unrelated port and DB .env loading contract remains intact'
);

const serverSource = functionSource('startServer');
check(
  serverSource.indexOf("url.pathname.startsWith('/internal/')") >= 0
    && serverSource.indexOf('authorizeInternalRequest(req, url)') >= 0
    && serverSource.indexOf("url.pathname === '/internal/status'")
      > serverSource.indexOf('authorizeInternalRequest(req, url)'),
  'all /internal/* routing passes through the authorization gate first'
);
check(
  /const FINANCE_WA_ENGINE_API_TOKEN = String\(process\.env\.FINANCE_WA_ENGINE_API_TOKEN \|\| ''\)\.trim\(\);/.test(source)
    && !source.includes('process.env.WA_TOKEN')
    && !source.includes('SYNC_TOKEN')
    && !source.includes('local-dev-token'),
  'engine credential has an empty default and no WA_TOKEN/development fallback'
);
check(
  !serverSource.includes("searchParams.get('token')")
    && !serverSource.includes("req.headers['x-sync-token']"),
  'server gate does not select credentials from legacy transports'
);
check(
  template.includes('FINANCE_WA_ENGINE_API_TOKEN=')
    && /^FINANCE_WA_ENGINE_API_TOKEN=\s*$/m.test(template)
    && !template.includes('WA_TOKEN=')
    && !template.includes('local-dev-token'),
  '.env example has only an empty process-token name and no legacy default'
);
check(
  source.includes("const FINANCE_WA_ENGINE_COMMAND_TOKEN = String(process.env.FINANCE_WA_ENGINE_COMMAND_TOKEN || '').trim();")
    && source.includes("'X-Finance-Group-Command-Token': FINANCE_WA_ENGINE_COMMAND_TOKEN")
    && source.includes("if (key === 'FINANCE_WA_ENGINE_COMMAND_TOKEN'")
    && source.includes("|| key === 'FINANCE_WA_ENGINE_API_TOKEN'"),
  'Batch 50 callback credential/header remains separate and process-only'
);
check(
  normalizedRunbook.includes('PHP/FPM')
    && normalizedRunbook.includes('HTTP 403')
    && normalizedRunbook.includes('DB_HOST')
    && normalizedRunbook.includes('tidak menjalankan restart atau cutover')
    && normalizedRunbook.includes('FINANCE_WA_ENGINE_COMMAND_TOKEN'),
  'runbook records fail-closed, DB, deployment, and callback boundaries'
);
check(
  !normalizedGroupRunbook.includes(
    '`WA_TOKEN` tetap khusus API internal Finance ke `wa-engine`; jangan memakai ulang nilainya sebagai credential callback.'
  )
    && groupRunbook.includes('FINANCE_WA_ENGINE_API_TOKEN')
    && groupRunbook.includes('X-Finance-Wa-Engine-Token')
    && groupRunbook.includes('FINANCE_WA_ENGINE_COMMAND_TOKEN')
    && groupRunbook.includes('tetap hanya untuk arah callback'),
  'group-command runbook has the Batch 52 direction split and no stale WA_TOKEN contract'
);
check(
  normalizedRunbook.includes('php index.php whatsapp api_schedule_run')
    && normalizedRunbook.includes('Cron tidak otomatis mewarisi environment PHP-FPM')
    && normalizedRunbook.includes('process manager eksternal')
    && normalizedRunbook.includes('di luar web root')
    && normalizedRunbook.includes('PHP/FPM web')
    && normalizedRunbook.includes('scheduler/cron CLI Finance')
    && normalizedRunbook.includes('Proses `wa-engine`')
    && normalizedRunbook.includes('Preflight sebelum cutover')
    && normalizedRunbook.includes('Cutover terkoordinasi')
    && normalizedRunbook.includes('Untuk rollback:')
    && normalizedRunbook.includes('tidak menjalankan restart atau cutover apa pun')
    && normalizedRunbook.includes('command line/log'),
  'runbook provisions and checks all three execution contexts without secret exposure or staging cutover'
);

if (failures.length > 0) {
  process.stderr.write('WA engine internal service-auth smoke FAILED\n');
  failures.forEach((failure) => process.stderr.write(`- ${failure}\n`));
  process.exit(1);
}

process.stdout.write(`WA engine internal service-auth smoke passed (${checks} checks).\n`);
