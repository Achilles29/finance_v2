#!/usr/bin/env python3
"""Private Telegram transport for exact-id Codex resume. Python stdlib only.

No polling, no public listener, no database connection to Finance. Never run
inside a customer installation. Owner pairing must be initiated locally.
"""
import argparse
import asyncio
import contextlib
import fcntl
import grp
import hashlib
import hmac
import json
import logging
import os
from pathlib import Path
import re
import secrets
import signal
import sqlite3
import time
import urllib.error
import urllib.request

ROOT = Path('/www/wwwroot/finance')
STATE = Path('/var/lib/finance-codex-bridge')
SOCKET = Path('/var/lib/finance-config/codex-bridge.sock')
CODEX = '/root/.local/bin/codex'
SECRET_FILE = Path('/var/lib/finance-telegram/runtime.env')
MAX_ACCOUNTS = 2
HELP = ('Codex pribadi — Finance\n\n/codex resume — daftar thread\n'
        '/codex pilih NOMOR — pilih thread dari daftar\n'
        '/codex berikut — halaman berikutnya\n'
        '/codex status — status/hasil terakhir\n/codex stop — hentikan tugas bot\n'
        '/codex keluar — lepas thread pilihan\n\n'
        'Setelah memilih thread, kirim instruksi sebagai pesan teks biasa. '
        'Tutup sesi Codex Finance di terminal/IDE terlebih dahulu. '
        'Jangan jalankan IDE dan bot bersamaan. Stop tidak membatalkan perubahan yang sudah dibuat. '
        'Akses di luar workspace atau perubahan server yang ditolak sandbox harus dilanjutkan di IDE.')


def redact(text):
    text = re.sub(r'/codex(?:@[A-Za-z0-9_]+)?\s+pair\s+[A-Za-z0-9_-]{20,}', '/codex pair [kode pribadi disamarkan]', str(text), flags=re.I)
    text = re.sub(r'-----BEGIN[^\n]*PRIVATE KEY-----.*?-----END[^\n]*PRIVATE KEY-----', '[kunci disamarkan]', str(text), flags=re.S)
    text = re.sub(r'\b(?:\d{8,12}:[A-Za-z0-9_-]{25,}|(?:ghp_|github_pat_|sk-)[A-Za-z0-9_-]{12,})', '[secret disamarkan]', text)
    text = re.sub(r'(?im)((?:[A-Z0-9_]*(?:TOKEN|PASSWORD|SECRET|API_KEY))\s*[=:]\s*)[^\s,;]+', r'\1[disamarkan]', text)
    return re.sub(r'[\x00-\x08\x0b-\x1f\x7f]', '', text)


def private_message(update):
    if type(update.get('update_id')) is not int or update['update_id'] < 0:
        return None
    m = update.get('message')
    if not isinstance(m, dict):
        return None
    c, u = m.get('chat', {}), m.get('from', {})
    if not isinstance(c, dict) or not isinstance(u, dict):
        return None
    uid = u.get('id')
    if (c.get('type') != 'private' or type(uid) is not int or uid <= 0
            or c.get('id') != uid or u.get('is_bot') is not False
            or any(k in m for k in ('sender_chat', 'forward_origin', 'forward_from', 'via_bot'))):
        return None
    return m


class Store:
    def __init__(self, directory=STATE):
        directory.mkdir(mode=0o700, parents=True, exist_ok=True)
        self.db = sqlite3.connect(str(directory / 'bridge.sqlite'), timeout=2)
        self.db.row_factory = sqlite3.Row
        self.db.executescript('''
            PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL;
            CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS inbox (
                id INTEGER PRIMARY KEY, text TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'pending');
            CREATE TABLE IF NOT EXISTS jobs (
                id TEXT PRIMARY KEY, thread TEXT NOT NULL, state TEXT NOT NULL,
                created INTEGER NOT NULL, result TEXT NOT NULL DEFAULT '');
            CREATE TABLE IF NOT EXISTS outbox (
                id INTEGER PRIMARY KEY, text TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'pending');
        ''')
        self.migrate_accounts()

    def migrate_accounts(self):
        # Additive, transactional upgrade of the INTERNAL SQLite only. Never
        # reassign unattributed rows on subsequent startups to a new account.
        with self.db:
            self.db.execute('BEGIN IMMEDIATE')
            version = int(self.get('schema_version', '1'))
            if version > 2:
                raise RuntimeError('Versi state bridge lebih baru dari worker ini.')
            if version == 2:
                return
            self.db.execute('CREATE TABLE IF NOT EXISTS accounts (user_id TEXT PRIMARY KEY, created INTEGER NOT NULL)')
            self.db.execute('CREATE TABLE IF NOT EXISTS account_settings (user_id TEXT NOT NULL, key TEXT NOT NULL, value TEXT NOT NULL, PRIMARY KEY(user_id,key))')
            for table in ('inbox', 'jobs', 'outbox'):
                columns = [r['name'] for r in self.db.execute('PRAGMA table_info(' + table + ')')]
                if 'user_id' not in columns:
                    self.db.execute("ALTER TABLE " + table + " ADD COLUMN user_id TEXT NOT NULL DEFAULT ''")
            owner = self.get('owner')
            if re.fullmatch(r'[1-9][0-9]{0,19}', owner):
                self.db.execute('INSERT OR IGNORE INTO accounts VALUES (?,?)', (owner, int(time.time())))
                for key in ('thread', 'choices', 'choices_until', 'cursor'):
                    self.db.execute('INSERT OR IGNORE INTO account_settings SELECT ?,key,value FROM settings WHERE key=?', (owner, key))
                for table in ('inbox', 'jobs', 'outbox'):
                    self.db.execute("UPDATE " + table + " SET user_id=? WHERE user_id=''", (owner,))
            self.put('schema_version', 2)

    def get(self, key, default=''):
        r = self.db.execute('SELECT value FROM settings WHERE key=?', (key,)).fetchone()
        return r[0] if r else default

    def put(self, key, value):
        self.db.execute('INSERT INTO settings VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', (key, str(value)))

    def authorized(self, uid):
        return bool(self.db.execute('SELECT 1 FROM accounts WHERE user_id=?', (str(uid),)).fetchone())

    def account(self, uid):
        if not self.authorized(uid):
            raise ValueError('Akun tidak diizinkan.')
        return AccountStore(self, str(uid))

    def queue_reply(self, text, uid):
        if not self.authorized(uid):
            raise ValueError('Penerima tidak diizinkan.')
        self.db.execute('INSERT INTO outbox(text,user_id) VALUES (?,?)', (redact(text)[:3500], str(uid)))

    def say(self, text, uid):
        # Recipient is fixed when enqueued, never derived from mutable owner or
        # selected-thread state when the asynchronous sender eventually runs.
        with self.db:
            self.queue_reply(text, uid)

    def pair(self, additional=False):
        code = secrets.token_urlsafe(24)
        with self.db:
            self.db.execute('BEGIN IMMEDIATE')
            count = self.db.execute('SELECT count(*) FROM accounts').fetchone()[0]
            if additional:
                if not self.authorized(self.get('owner')) or count >= MAX_ACCOUNTS:
                    raise RuntimeError('Akun utama belum terpasang atau batas dua akun sudah tercapai.')
            elif self.get('owner') or count:
                raise RuntimeError('Akun utama sudah terpasang; gunakan pair-add untuk akun kedua.')
            self.put('pair_kind', 'additional' if additional else 'primary')
            self.put('pair_hash', hashlib.sha256(code.encode()).hexdigest())
            self.put('pair_until', int(time.time()) + 86400)
        return code

    def accept(self, update):
        m = private_message(update)
        if m is None:
            return
        uid = str(m['from']['id'])
        text = m.get('text', '')
        if not isinstance(text, str) or len(text) > 16000:
            return
        with self.db:
            self.db.execute('BEGIN IMMEDIATE')
            if self.db.execute('SELECT 1 FROM inbox WHERE id=?', (update['update_id'],)).fetchone():
                return
            owner = self.get('owner')
            allowed = self.authorized(uid)
            match = re.fullmatch(r'/codex(?:@cacacia_bot)?\s+pair\s+([A-Za-z0-9_-]{32})', text.strip(), re.I)
            if match:
                if allowed:
                    # Sending the invitation from the first account must not
                    # consume the invitation intended for the second account.
                    self.db.execute('INSERT INTO inbox(id,text,state,user_id) VALUES (?,?,?,?)', (update['update_id'], '', 'paired', uid))
                    self.queue_reply('Akun ini sudah terhubung. Untuk memasangkan akun kedua, kirim kode pairing dari akun Telegram kedua.', uid)
                    return
                count = self.db.execute('SELECT count(*) FROM accounts').fetchone()[0]
                kind = self.get('pair_kind', 'primary')
                eligible = (not owner and not count and kind == 'primary') or (self.authorized(owner) and count < MAX_ACCOUNTS and kind == 'additional')
                if (eligible and int(self.get('pair_until', '0')) >= time.time()
                        and hmac.compare_digest(self.get('pair_hash'), hashlib.sha256(match[1].encode()).hexdigest())):
                    self.db.execute('INSERT INTO accounts VALUES (?,?)', (uid, int(time.time())))
                    if not owner:
                        self.put('owner', uid)
                    self.put('pair_hash', '')
                    self.put('pair_until', 0)
                    self.put('pair_kind', '')
                    self.db.execute('INSERT INTO inbox(id,text,state,user_id) VALUES (?,?,?,?)', (update['update_id'], '', 'paired', uid))
                    self.queue_reply('✅ Akun Telegram Anda sudah terpasang. Pilihan thread dan balasan terpisah per akun; pengerjaan tetap satu tugas sekaligus.\n\n' + HELP, uid)
                    if owner:
                        self.queue_reply('✅ Akun Telegram kedua berhasil ditambahkan. Akses dan pilihan thread akun pertama tetap berlaku.', owner)
                return
            if not allowed:
                return
            if not text:
                text = '/codex unsupported'
            self.db.execute('INSERT INTO inbox(id,text,user_id) VALUES (?,?,?)', (update['update_id'], text, uid))

    def recover(self):
        with self.db:
            recipients = [r[0] for r in self.db.execute("SELECT DISTINCT user_id FROM jobs WHERE state='running'")]
            self.db.execute("UPDATE jobs SET state='interrupted', result='Worker restart; periksa diff sebelum melanjutkan.' WHERE state='running'")
            self.db.execute("UPDATE inbox SET state='interrupted' WHERE state='processing'")
            self.db.execute("UPDATE outbox SET state='unknown' WHERE state='sending'")
            for uid in recipients:
                if self.authorized(uid):
                    self.queue_reply('⚠️ Worker dimulai ulang. Tugas sebelumnya dihentikan, tidak dijalankan ulang otomatis. Periksa perubahan melalui /codex status atau IDE.', uid)


class AccountStore:
    """An immutable recipient/session binding, safe across awaited operations."""
    def __init__(self, store, uid):
        self.store, self.uid, self.db = store, uid, store.db

    def get(self, key, default=''):
        r = self.db.execute('SELECT value FROM account_settings WHERE user_id=? AND key=?', (self.uid, key)).fetchone()
        return r[0] if r else default

    def put(self, key, value):
        self.db.execute('INSERT INTO account_settings VALUES (?,?,?) ON CONFLICT(user_id,key) DO UPDATE SET value=excluded.value', (self.uid, key, str(value)))

    def say(self, text):
        self.store.say(text, self.uid)


def runtime_secrets():
    # Existing deployment file is PHP INI, not a shell script. Never source it.
    values = {}
    for line in SECRET_FILE.read_text().splitlines():
        key, sep, value = line.strip().partition('=')
        if sep and key.strip() in ('FINANCE_TELEGRAM_BOT_TOKEN', 'FINANCE_TELEGRAM_WEBHOOK_SECRET'):
            values[key.strip()] = value.strip().strip('"\'')
    if not re.fullmatch(r'\d+:[A-Za-z0-9_-]+', values.get('FINANCE_TELEGRAM_BOT_TOKEN', '')):
        raise RuntimeError('Token bot belum tersedia.')
    if not re.fullmatch(r'[A-Za-z0-9_-]{1,256}', values.get('FINANCE_TELEGRAM_WEBHOOK_SECRET', '')):
        raise RuntimeError('Secret webhook belum tersedia.')
    return values


async def terminate(proc):
    if not proc or proc.returncode is not None:
        return
    for sig in (signal.SIGINT, signal.SIGTERM, signal.SIGKILL):
        with contextlib.suppress(ProcessLookupError):
            os.killpg(proc.pid, sig)
        try:
            await asyncio.wait_for(proc.wait(), 5)
            return
        except asyncio.TimeoutError:
            pass


class Codex:
    async def rpc(self, method, params):
        proc = await asyncio.create_subprocess_exec(
            CODEX, 'app-server', '--listen', 'stdio://', '-c', 'notify=[]',
            cwd=ROOT, stdin=asyncio.subprocess.PIPE, stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL, start_new_session=True, limit=2**22)

        async def request(ident, name, args):
            proc.stdin.write((json.dumps({'id': ident, 'method': name, 'params': args}) + '\n').encode())
            await proc.stdin.drain()
            while True:
                line = await proc.stdout.readline()
                if not line:
                    raise RuntimeError('Codex berhenti sebelum memberikan respons.')
                reply = json.loads(line)
                if reply.get('id') == ident:
                    if 'error' in reply:
                        raise RuntimeError('Codex tidak dapat membaca thread. Periksa autentikasi/versi CLI di server.')
                    return reply['result']
        try:
            await asyncio.wait_for(request(1, 'initialize', {
                'clientInfo': {'name': 'finance_telegram_bridge', 'version': '1.0'},
                'capabilities': {'experimentalApi': True}}), 20)
            proc.stdin.write(b'{"method":"initialized"}\n')
            return await asyncio.wait_for(request(2, method, params), 20)
        finally:
            await terminate(proc)

    async def listing(self, cursor=None):
        return await self.rpc('thread/list', {'cwd': str(ROOT), 'limit': 8,
            'sortKey': 'updated_at', 'sourceKinds': ['cli', 'vscode', 'exec', 'appServer'],
            'useStateDbOnly': True, 'cursor': cursor})

    async def thread(self, ident):
        thread = (await self.rpc('thread/read', {'threadId': ident, 'includeTurns': False}))['thread']
        if Path(thread.get('cwd', '')).resolve() != ROOT or thread.get('id') != ident:
            raise RuntimeError('Thread bukan milik workspace Finance yang diizinkan.')
        return thread


def other_clients(own_pid=None, thread_id=None):
    """Conservative handoff guard: loaded/idle CLI also requires user to exit.

    This is not an IDE-wide lock. A newly opened external client causes the bot
    worker to stop, but users must still avoid starting two writers themselves.
    """
    found = []
    for entry in Path('/proc').iterdir():
        if not entry.name.isdigit() or int(entry.name) == own_pid:
            continue
        try:
            if (entry / 'comm').read_text().strip() != 'codex':
                continue
            args = (entry / 'cmdline').read_bytes().split(b'\0')
            # Read-only metadata helper is owned by this service, not an IDE.
            parent = int((entry / 'stat').read_text().rsplit(')', 1)[1].split()[1])
            if parent == os.getpid() and b'app-server' in args:
                continue
            cwd = (entry / 'cwd').resolve()
            if cwd == ROOT or ROOT in cwd.parents:
                found.append(int(entry.name))
                continue
            # IDE app servers may be launched from a parent/unrelated cwd.
            # An open rollout for this exact thread is also a handoff conflict.
            if thread_id:
                for fd in (entry / 'fd').iterdir():
                    with contextlib.suppress(OSError):
                        if os.readlink(fd).endswith('-' + thread_id + '.jsonl'):
                            found.append(int(entry.name))
                            break
        except (OSError, PermissionError):
            continue
    return found


def exec_args(thread_id):
    if not re.fullmatch(r'[A-Za-z0-9_-]{12,100}', thread_id):
        raise RuntimeError('ID thread tidak valid.')
    return [CODEX, 'exec', '-C', str(ROOT), '--sandbox', 'workspace-write', '--ignore-rules',
            '-c', 'approval_policy="never"', '-c', 'notify=[]',
            '-c', 'sandbox_workspace_write.network_access=false',
            '-c', 'sandbox_workspace_write.writable_roots=[]',
            'resume', thread_id, '-', '--json']


class Bridge:
    def __init__(self, store, codex=None):
        self.s = store
        self.codex = codex or Codex()
        self.task = None
        self.proc = None
        self.cancel_requested = False
        self.active_user = None

    async def handle(self, text, uid):
        s = self.s.account(uid)
        text = text.strip()
        command = re.fullmatch(r'/codex(?:@cacacia_bot)?(?:\s+(.*))?', text, re.S | re.I)
        if text.startswith('/') and not command:
            s.say(HELP)
            return
        args = (command.group(1) or 'help').strip() if command else None
        if args in ('resume', 'berikut'):
            cursor = s.get('cursor') if args == 'berikut' else None
            if args == 'berikut' and not cursor:
                s.say('Tidak ada halaman berikutnya. /codex resume untuk kembali ke awal.')
                return
            page = await self.codex.listing(cursor)
            threads = [t for t in page['data'] if Path(t.get('cwd', '')).resolve() == ROOT]
            with s.db:
                s.put('choices', json.dumps([t['id'] for t in threads]))
                s.put('choices_until', int(time.time()) + 900)
                s.put('cursor', page.get('nextCursor') or '')
            names = [f"{i}. {redact(t.get('name') or t.get('preview') or t['id'])[:150]}" for i, t in enumerate(threads, 1)]
            s.say(('Thread Finance (terbaru dahulu):\n\n' + '\n'.join(names)
                + '\n\nPilih: /codex pilih 1\nDaftar berlaku 15 menit.'
                + ('\n/codex berikut untuk halaman selanjutnya.' if page.get('nextCursor') else ''))
                if threads else 'Tidak ada thread Finance yang tersimpan pada akun Codex server ini.')
        elif args and re.fullmatch(r'pilih\s+[1-8]', args):
            if self.task and not self.task.done() and self.active_user == s.uid:
                s.say('Tugas masih berjalan. Tunggu selesai atau /codex stop sebelum pindah thread.')
                return
            choices = json.loads(s.get('choices', '[]'))
            n = int(args.split()[1]) - 1
            if int(s.get('choices_until', '0')) < time.time() or n >= len(choices):
                s.say('Pilihan kedaluwarsa/tidak valid. Kirim /codex resume lagi.')
                return
            t = await self.codex.thread(choices[n])
            with s.db:
                s.put('thread', t['id'])
            s.say('Thread dipilih: ' + redact(t.get('name') or t.get('preview') or t['id'])[:180]
                + '\nID: ' + t['id'] + '\n\nKirim instruksi Anda. Tutup sesi Codex Finance di terminal/IDE dulu, tanpa menghapus thread-nya.')
        elif args == 'status':
            job = s.db.execute('SELECT * FROM jobs WHERE user_id=? ORDER BY created DESC, rowid DESC LIMIT 1', (s.uid,)).fetchone()
            busy = '\nAda tugas dari akun lain yang sedang berjalan; tunggu sebelum mengirim tugas baru.' if self.task and not self.task.done() and self.active_user != s.uid else ''
            s.say('Thread: ' + (s.get('thread') or 'belum dipilih') + '\n'
                + (f"Tugas {job['id']}: {job['state']}\n{job['result']}" if job else 'Belum ada tugas dari akun ini.') + busy)
        elif args == 'stop':
            if self.task and not self.task.done() and self.active_user != s.uid:
                s.say('Tugas aktif dikirim dari akun lain. Gunakan akun pengirim untuk /codex stop.')
                return
            self.cancel_requested = True
            if self.task and not self.task.done():
                self.task.cancel()
                s.say('Penghentian diminta. Perubahan file yang sudah dibuat tidak di-undo otomatis.')
            else:
                s.say('Tidak ada tugas bot yang sedang berjalan. Sesi IDE tidak disentuh.')
        elif args == 'keluar':
            if self.task and not self.task.done() and self.active_user == s.uid:
                s.say('Hentikan tugas dengan /codex stop terlebih dahulu.')
            else:
                with s.db:
                    s.put('thread', '')
                s.say('Thread dilepas, riwayat tetap tersimpan. /codex resume untuk memilih kembali.')
        elif args is not None:
            s.say(HELP if args != 'unsupported' else 'Saat ini instruksi coding harus berupa teks. Foto/file belum diteruskan ke Codex.')
        else:
            if not s.get('thread'):
                s.say('Pilih thread terlebih dahulu dengan /codex resume.')
                return
            if self.task and not self.task.done():
                s.say('Masih ada satu tugas berjalan. Pesan ini tidak dijalankan; kirim ulang setelah selesai. /codex stop hanya dari akun pengirim tugas.')
                return
            self.cancel_requested = False
            self.active_user = s.uid
            self.task = asyncio.create_task(self.run(s.get('thread'), text, s.uid))

    async def run(self, thread_id, text, uid):
        s = self.s.account(uid)
        self.active_user = s.uid
        job_id = secrets.token_hex(5)
        state, result = 'failed', 'Tugas tidak selesai; periksa status Codex di server.'
        started = False
        try:
            thread = await self.codex.thread(thread_id)
            if thread.get('status', {}).get('type') == 'active' or other_clients(thread_id=thread_id):
                s.say('⏸️ Codex Finance masih terbuka di terminal/IDE. Tutup sesi tersebut terlebih dahulu (riwayat tidak hilang), lalu kirim ulang instruksi. Bot belum menjalankan perubahan apa pun.')
                return
            with self.s.db:
                self.s.db.execute('INSERT INTO jobs(id,thread,state,created,user_id) VALUES (?,?,?,?,?)',
                    (job_id, thread_id, 'running', int(time.time()), s.uid))
            started = True
            s.say(f'▶️ Tugas {job_id} dimulai pada thread {thread_id}.\n/codex status atau /codex stop tersedia selama proses.')
            spawning = asyncio.create_task(asyncio.create_subprocess_exec(*exec_args(thread_id), cwd=ROOT,
                stdin=asyncio.subprocess.PIPE, stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.DEVNULL, start_new_session=True, limit=2**23))
            try:
                self.proc = await asyncio.shield(spawning)
            except asyncio.CancelledError:
                # Always acquire the child handle before cancellation cleanup.
                self.proc = await spawning
                raise
            prompt = ('[Transport Telegram pribadi, workspace Finance. Ikuti tugas pengguna di bawah. '
                'Bridge sudah menangani balasan; jangan mengirim notifikasi Telegram sendiri atau mengubah bridge. '
                'Jangan push, deploy, mengubah credential/konfigurasi server/database, atau melakukan tindakan '
                'destruktif. Jika perlu, jelaskan dan minta dilanjutkan di IDE. '
                'Jangan tampilkan secret/token. Akhiri dengan ringkasan hasil dan validasi maksimal 2500 karakter.]\n\n' + text)
            self.proc.stdin.write(prompt.encode())
            await self.proc.stdin.drain()
            self.proc.stdin.close()
            final = ''
            complete = False
            deadline, update_at = time.monotonic() + 2700, time.monotonic() + 90
            read = asyncio.create_task(self.proc.stdout.readline())
            try:
                while time.monotonic() < deadline:
                    done, _ = await asyncio.wait({read}, timeout=2)
                    if other_clients(self.proc.pid, thread_id=thread_id):
                        raise RuntimeError('Sesi Codex lain dibuka. Tugas bot dihentikan untuk mengurangi risiko perubahan bersamaan; periksa diff.')
                    if not done:
                        if time.monotonic() > update_at:
                            s.say(f'⏳ Tugas {job_id} masih berjalan. /codex status atau /codex stop.')
                            update_at = time.monotonic() + 120
                        continue
                    line = read.result()
                    if not line:
                        break
                    read = asyncio.create_task(self.proc.stdout.readline())
                    try:
                        event = json.loads(line)
                    except ValueError:
                        continue
                    if event.get('type') == 'thread.started' and event.get('thread_id') != thread_id:
                        raise RuntimeError('Codex mengembalikan ID thread berbeda; tugas dihentikan.')
                    item = event.get('item', {})
                    if event.get('type') == 'item.completed' and item.get('type') == 'agent_message':
                        final = redact(item.get('text', ''))[:2800]
                    if event.get('type') == 'turn.completed':
                        complete = True
                if time.monotonic() >= deadline:
                    raise RuntimeError('Batas waktu 45 menit tercapai. Periksa perubahan sebelum melanjutkan.')
                rc = await asyncio.wait_for(self.proc.wait(), 10)
                if rc == 0 and complete and final:
                    state, result = 'completed', final
                else:
                    result = f'Codex belum berhasil menyelesaikan tugas (exit {rc}). Periksa login, kuota, atau sandbox di IDE. Perubahan parsial mungkin sudah ada.'
            finally:
                read.cancel()
                with contextlib.suppress(asyncio.CancelledError):
                    await read
        except asyncio.CancelledError:
            state, result = 'cancelled', 'Tugas dihentikan. Perubahan yang sudah dibuat tetap ada; periksa diff sebelum melanjutkan.'
        except RuntimeError as exc:
            result = redact(str(exc))[:1200]
        except Exception:
            result = 'Gangguan internal worker. Periksa log layanan dan diff di IDE; tugas tidak diulang otomatis.'
            logging.error('Job failed: internal error (details withheld)')
        finally:
            await terminate(self.proc)
            self.proc = None
            self.active_user = None
            if started:
                with self.s.db:
                    self.s.db.execute('UPDATE jobs SET state=?,result=? WHERE id=?', (state, result, job_id))
                s.say(('✅' if state == 'completed' else '⚠️') + f' Tugas {job_id}: {state}\n\n' + result)

    async def incoming(self, reader, writer, expected):
        try:
            raw = await asyncio.wait_for(reader.readline(), 2)
            if len(raw) > 524288:
                raise ValueError('oversize')
            packet = json.loads(raw)
            if not isinstance(packet, dict) or not isinstance(packet.get('secret'), str) or not hmac.compare_digest(packet['secret'], expected):
                raise ValueError('unauthorized')
            if not isinstance(packet.get('update'), dict):
                raise ValueError('update')
            self.s.accept(packet['update'])
            writer.write(b'{"ok":true}\n')
        except Exception:
            writer.write(b'{"ok":false}\n')
        finally:
            with contextlib.suppress(Exception):
                await writer.drain()
            writer.close()
            with contextlib.suppress(Exception):
                await writer.wait_closed()

    async def inbox(self):
        while True:
            row = self.s.db.execute("SELECT * FROM inbox WHERE state='pending' ORDER BY id LIMIT 1").fetchone()
            if row:
                with self.s.db:
                    self.s.db.execute("UPDATE inbox SET state='processing' WHERE id=?", (row['id'],))
                try:
                    await self.handle(row['text'], row['user_id'])
                    status = 'done'
                except Exception:
                    if self.s.authorized(row['user_id']):
                        self.s.say('Perintah belum berhasil. Coba /codex status; periksa layanan Codex bila daftar thread tidak tersedia.', row['user_id'])
                    status = 'failed'
                with self.s.db:
                    self.s.db.execute('UPDATE inbox SET state=? WHERE id=?', (status, row['id']))
            await asyncio.sleep(.2)

    async def outbox(self, token):
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args, **kwargs):
                return None
        opener = urllib.request.build_opener(NoRedirect)

        def send(text, owner):
            req = urllib.request.Request('https://api.telegram.org/bot' + token + '/sendMessage',
                data=json.dumps({'chat_id': owner, 'text': text, 'disable_web_page_preview': True}).encode(),
                headers={'Content-Type': 'application/json'}, method='POST')
            with opener.open(req, timeout=10) as response:
                return json.loads(response.read(65536)).get('ok') is True
        while True:
            row = self.s.db.execute("SELECT * FROM outbox WHERE state='pending' ORDER BY id LIMIT 1").fetchone()
            if row:
                recipient = row['user_id']
                if not self.s.authorized(recipient):
                    with self.s.db:
                        self.s.db.execute("UPDATE outbox SET state='rejected' WHERE id=?", (row['id'],))
                    continue
                with self.s.db:
                    self.s.db.execute("UPDATE outbox SET state='sending' WHERE id=?", (row['id'],))
                status = 'unknown'
                try:
                    status = 'sent' if await asyncio.to_thread(send, row['text'], recipient) else 'failed'
                except Exception:
                    logging.warning('Telegram delivery unconfirmed; use /codex status (no automatic replay)')
                with self.s.db:
                    self.s.db.execute('UPDATE outbox SET state=? WHERE id=?', (status, row['id']))
            await asyncio.sleep(1)


async def serve():
    s = Store()
    # This file lock serializes bridge workers, not independent IDE processes.
    lock = open(STATE / 'worker.lock', 'a')
    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    credentials = runtime_secrets()
    s.recover()
    bridge = Bridge(s)
    if SOCKET.is_socket():
        SOCKET.unlink()  # Only this exact stale service socket; no logs/data.
    elif SOCKET.exists():
        raise RuntimeError('Socket path occupied; refusing to overwrite.')
    server = await asyncio.start_unix_server(
        lambda r, w: bridge.incoming(r, w, credentials['FINANCE_TELEGRAM_WEBHOOK_SECRET']),
        str(SOCKET), limit=524289)
    os.chown(SOCKET, 0, grp.getgrnam('www').gr_gid)
    os.chmod(SOCKET, 0o660)
    logging.info('Owner-only bridge ready; paired=%s', bool(s.get('owner')))
    stop = asyncio.Event()
    for sig in (signal.SIGTERM, signal.SIGINT):
        asyncio.get_running_loop().add_signal_handler(sig, stop.set)
    workers = [asyncio.create_task(bridge.inbox()), asyncio.create_task(bridge.outbox(credentials['FINANCE_TELEGRAM_BOT_TOKEN']))]
    stopping = asyncio.create_task(stop.wait())
    async with server:
        done, _ = await asyncio.wait([stopping, *workers], return_when=asyncio.FIRST_COMPLETED)
    failed = stopping not in done
    stopping.cancel()
    for task in workers:
        task.cancel()
    if bridge.task and not bridge.task.done():
        bridge.task.cancel()
        await bridge.task
    await asyncio.gather(*workers, return_exceptions=True)
    lock.close()
    if failed:
        raise RuntimeError('Queue worker stopped unexpectedly; service restart required.')


def main():
    os.umask(0o077)
    logging.basicConfig(level=logging.INFO, format='%(levelname)s %(message)s')
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['serve', 'pair', 'pair-add', 'check'])
    args = parser.parse_args()
    if args.command == 'serve':
        asyncio.run(serve())
    elif args.command in ('pair', 'pair-add'):
        print('/codex pair ' + Store().pair(additional=args.command == 'pair-add'))
        print('Kirim hanya melalui chat PRIBADI @cacacia_bot. Sekali pakai, berlaku 24 jam. Jangan kirim ke grup.')
    else:
        page = asyncio.run(Codex().listing())
        s = Store()
        print(json.dumps({'thread_count_first_page': len(page['data']), 'paired': bool(s.get('owner')),
                          'account_count': s.db.execute('SELECT count(*) FROM accounts').fetchone()[0],
                          'external_codex_clients': len(other_clients())}))


if __name__ == '__main__':
    main()
