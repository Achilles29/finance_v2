import asyncio
import contextlib
import hashlib
import json
import sqlite3
from pathlib import Path
import tempfile
import threading
import time
import unittest
from unittest.mock import AsyncMock, patch

import bridge as b


def update(uid=123, text='/codex resume', ident=1, **changes):
    m = {'from': {'id': uid, 'is_bot': False}, 'chat': {'id': uid, 'type': 'private'}, 'text': text}
    m.update(changes)
    return {'update_id': ident, 'message': m}


class Fixture(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='finance-codex-test-')
        self.s = b.Store(Path(self.temp.name))

    def tearDown(self):
        self.s.db.close()
        self.temp.cleanup()

    def owner(self):
        code = self.s.pair()
        self.s.accept(update(text='/codex pair ' + code))
        return code

    def messages(self):
        return [r[0] for r in self.s.db.execute('SELECT text FROM outbox ORDER BY id')]

    def test_pair_owner_only(self):
        code = self.owner()
        self.assertEqual(self.s.get('owner'), '123')
        self.assertEqual(self.s.get('pair_hash'), '')
        self.s.accept(update(uid=456, text='/codex pair ' + code, ident=2))
        self.assertEqual(self.s.get('owner'), '123')
        self.assertNotIn(code, ''.join(self.messages()))
        with self.assertRaises(RuntimeError):
            self.s.pair()

    def test_unpaired_unknown_wrong_expired_ignored(self):
        code = self.s.pair()
        self.s.accept(update(text='/codex pair ' + 'x' * 32))
        self.s.accept(update())
        with self.s.db:
            self.s.put('pair_until', 0)
        self.s.accept(update(text='/codex pair ' + code))
        self.assertEqual(self.s.get('owner'), '')
        self.assertEqual(self.messages(), [])

    def test_group_bot_forwarded_anonymous_not_accepted(self):
        code = self.s.pair()
        for m in [
            {'chat': {'id': -123, 'type': 'group'}},
            {'from': {'id': 123, 'is_bot': True}},
            {'forward_origin': {}}, {'sender_chat': {}}, {'via_bot': {}},
            {'chat': {'id': 124, 'type': 'private'}},
        ]:
            self.s.accept(update(text='/codex pair ' + code, **m))
            self.assertEqual(self.s.get('owner'), '')

    def test_duplicate_prompt_and_other_user(self):
        self.owner()
        self.s.accept(update(text='Fix it', ident=2))
        self.s.accept(update(text='Fix it', ident=2))
        self.s.accept(update(uid=124, text='DROP everything', ident=3))
        self.assertEqual(self.s.db.execute("SELECT count(*) FROM inbox WHERE state='pending'").fetchone()[0], 1)

    def test_invalid_payload(self):
        for u in [{}, {'update_id': True}, {'update_id': -1}, {'update_id': 2, 'message': 'x'},
                  update(**{'from': []}), update(**{'chat': []})]:
            self.s.accept(u)
        self.assertEqual(self.s.get('owner'), '')

    def test_restart_never_replays_jobs(self):
        self.owner()
        with self.s.db:
            self.s.db.execute("INSERT INTO jobs(id,thread,state,created,user_id) VALUES ('j','t','running',1,'123')")
            self.s.db.execute("INSERT INTO inbox(id,text,state,user_id) VALUES (2,'prompt','processing','123')")
            self.s.db.execute("INSERT INTO outbox(text,state,user_id) VALUES ('sent maybe','sending','123')")
        self.s.recover()
        self.assertEqual(self.s.db.execute('SELECT state FROM jobs').fetchone()[0], 'interrupted')
        self.assertEqual(self.s.db.execute('SELECT state FROM inbox WHERE id=2').fetchone()[0], 'interrupted')
        self.assertEqual(self.s.db.execute("SELECT state FROM outbox WHERE text='sent maybe'").fetchone()[0], 'unknown')

    def test_secret_redaction_and_bounded_output(self):
        self.owner()
        self.s.say('TOKEN=abc ghp_' + 'A'*30 + ' 123456789:' + 'B'*35 + ' x'*4000, '123')
        msg = self.messages()[-1]
        self.assertNotIn('TOKEN=abc', msg)
        self.assertNotIn('ghp_', msg)
        self.assertNotIn('123456789:', msg)
        self.assertLessEqual(len(msg), 3500)
        self.assertNotIn('Q'*32, b.redact('/codex pair ' + 'Q'*32))

    def test_safe_exec_exact_thread_not_shell_or_last(self):
        ident = '01a05f1b-9e9a-7f22-9831-f36b1d969f9d'
        args = b.exec_args(ident)
        self.assertIn(ident, args)
        self.assertNotIn('--last', args)
        self.assertNotIn('--dangerously-bypass-approvals-and-sandbox', args)
        self.assertIn('workspace-write', args)
        self.assertIn('notify=[]', args)
        self.assertIn('approval_policy="never"', args)
        with self.assertRaises(RuntimeError):
            b.exec_args('bad; rm -rf /')

    def test_additional_pair_keeps_first_and_limits_two(self):
        self.owner()
        code = self.s.pair(additional=True)
        self.s.accept(update(uid=456, text='/codex pair ' + code, ident=2))
        self.assertEqual(self.s.get('owner'), '123')
        self.assertTrue(self.s.authorized('123'))
        self.assertTrue(self.s.authorized('456'))
        self.s.accept(update(uid=789, text='/codex pair ' + code, ident=3))
        self.assertFalse(self.s.authorized('789'))
        with self.assertRaises(RuntimeError):
            self.s.pair(additional=True)
        self.s.accept(update(uid=456, text='Second prompt', ident=4))
        self.s.accept(update(uid=123, text='First prompt', ident=5))
        rows = self.s.db.execute('SELECT user_id FROM inbox WHERE id IN (4,5) ORDER BY id').fetchall()
        self.assertEqual([r[0] for r in rows], ['456', '123'])
        stored = ' '.join(r[0] for r in self.s.db.execute('SELECT text FROM inbox UNION ALL SELECT text FROM outbox'))
        self.assertNotIn(code, stored)

    def test_first_account_cannot_consume_second_invitation(self):
        self.owner()
        code = self.s.pair(additional=True)
        digest = self.s.get('pair_hash')
        self.s.accept(update(text='/codex pair ' + code, ident=2))
        self.assertEqual(self.s.get('pair_hash'), digest)
        self.s.accept(update(uid=456, text='/codex pair ' + code, ident=3))
        self.s.accept(update(uid=456, text='/codex pair ' + code, ident=3))
        self.assertEqual(self.s.db.execute('SELECT count(*) FROM accounts').fetchone()[0], 2)
        self.assertEqual(self.s.db.execute('SELECT count(*) FROM inbox WHERE id=3').fetchone()[0], 1)

    def test_additional_pair_requires_primary_and_checks_expiry_rotation(self):
        with self.assertRaises(RuntimeError): self.s.pair(additional=True)
        self.owner()
        old = self.s.pair(additional=True)
        code = self.s.pair(additional=True)
        self.s.accept(update(uid=456, text='/codex pair ' + old, ident=2))
        self.assertFalse(self.s.authorized(456))
        with self.s.db: self.s.put('pair_until', 0)
        self.s.accept(update(uid=456, text='/codex pair ' + code, ident=3))
        self.assertFalse(self.s.authorized(456))

    def test_simultaneous_pairing_consumes_invitation_once(self):
        self.owner()
        code = self.s.pair(additional=True)
        barrier = threading.Barrier(2)
        errors = []
        def attempt(uid):
            store = None
            try:
                store = b.Store(Path(self.temp.name))
                barrier.wait(timeout=5)
                store.accept(update(uid=uid, text='/codex pair ' + code, ident=uid))
            except Exception as exc:
                errors.append(type(exc).__name__)
            finally:
                if store: store.db.close()
        tasks = [threading.Thread(target=attempt, args=(uid,)) for uid in (456, 789)]
        for task in tasks: task.start()
        for task in tasks: task.join(timeout=10)
        self.assertFalse(any(task.is_alive() for task in tasks))
        self.assertEqual(errors, [])
        self.assertEqual(self.s.db.execute('SELECT count(*) FROM accounts').fetchone()[0], 2)
        self.assertNotEqual(self.s.authorized(456), self.s.authorized(789))

    def test_reply_cannot_target_unknown_or_group(self):
        self.owner()
        for uid in ('456', '-123', ''):
            with self.assertRaises(ValueError): self.s.say('secret reply', uid)
            with self.assertRaises(ValueError): self.s.account(uid)


class FakeCodex:
    thread_id = '01a05f1b-9e9a-7f22-9831-f36b1d969f9d'

    async def listing(self, cursor=None):
        return {'data': [await self.thread(self.thread_id)], 'nextCursor': None}

    async def thread(self, ident):
        return {'id': ident, 'cwd': str(b.ROOT), 'name': 'Audit Finance', 'status': {'type': 'notLoaded'}}


class FakeProcess:
    pid = 99999999
    returncode = 0

    class Input:
        data = b''
        def write(self, data): self.data += data
        async def drain(self): pass
        def close(self): pass

    def __init__(self, ident, complete=True, eof=True):
        self.stdin = self.Input()
        self.stdout = asyncio.StreamReader()
        events = [{'type': 'thread.started', 'thread_id': ident}]
        if complete:
            events += [{'type': 'item.completed', 'item': {'type': 'agent_message', 'text': 'Sudah diuji.'}}, {'type': 'turn.completed'}]
        for event in events:
            self.stdout.feed_data((json.dumps(event) + '\n').encode())
        if eof: self.stdout.feed_eof()

    async def wait(self): return 0


class Workflow(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='finance-codex-workflow-')
        self.s = b.Store(Path(self.temp.name))
        self.s.accept(update(text='/codex pair ' + self.s.pair()))
        self.a = self.s.account('123')
        self.worker = b.Bridge(self.s, FakeCodex())

    async def asyncTearDown(self):
        self.s.db.close()
        self.temp.cleanup()

    def last(self):
        return self.s.db.execute('SELECT text FROM outbox ORDER BY id DESC LIMIT 1').fetchone()[0]

    async def test_resume_select_status_logout(self):
        await self.worker.handle('/codex resume', '123')
        self.assertIn('Audit Finance', self.last())
        await self.worker.handle('/codex pilih 1', '123')
        self.assertEqual(self.a.get('thread'), FakeCodex.thread_id)
        await self.worker.handle('/codex status', '123')
        self.assertIn(FakeCodex.thread_id, self.last())
        await self.worker.handle('/codex keluar', '123')
        self.assertEqual(self.a.get('thread'), '')

    async def test_unselected_prompt_not_executed(self):
        await self.worker.handle('Fix the app', '123')
        self.assertIn('Pilih thread', self.last())
        self.assertIsNone(self.worker.task)

    async def test_expired_list_invalid_choice(self):
        await self.worker.handle('/codex resume', '123')
        await self.worker.handle('/codex pilih 8', '123')
        self.assertEqual(self.a.get('thread'), '')
        with self.s.db:
            self.a.put('choices_until', 0)
        await self.worker.handle('/codex pilih 1', '123')
        self.assertEqual(self.a.get('thread'), '')

    async def test_external_ide_blocks_before_exec(self):
        with patch.object(b, 'other_clients', return_value=[42]), patch.object(b.asyncio, 'create_subprocess_exec') as spawn:
            await self.worker.run(FakeCodex.thread_id, 'Fix code', '123')
        spawn.assert_not_called()
        self.assertIn('belum menjalankan perubahan', self.last())
        self.assertEqual(self.s.db.execute('SELECT count(*) FROM jobs').fetchone()[0], 0)

    async def test_wrong_bot_commands_are_not_prompts(self):
        await self.worker.handle('/codex@another_bot resume', '123')
        self.assertIsNone(self.worker.task)
        self.assertEqual(self.a.get('choices'), '')

    async def test_no_task_stop_does_not_signal_ide(self):
        with patch.object(b.os, 'killpg') as kill:
            await self.worker.handle('/codex stop', '123')
        kill.assert_not_called()

    async def test_completed_job_reports_final_and_exact_resume(self):
        proc = FakeProcess(FakeCodex.thread_id)
        with patch.object(b, 'other_clients', return_value=[]), patch.object(b.asyncio, 'create_subprocess_exec', new_callable=AsyncMock, return_value=proc) as spawn:
            await self.worker.run(FakeCodex.thread_id, 'Perbaiki filter', '123')
        self.assertIn(FakeCodex.thread_id, spawn.call_args.args)
        self.assertIn(b'Perbaiki filter', proc.stdin.data)
        self.assertIn('Sudah diuji.', self.last())
        self.assertEqual(self.s.db.execute('SELECT state FROM jobs').fetchone()[0], 'completed')

    async def test_wrong_thread_from_cli_fails(self):
        proc = FakeProcess('wrong-thread')
        with patch.object(b, 'other_clients', return_value=[]), patch.object(b.asyncio, 'create_subprocess_exec', new_callable=AsyncMock, return_value=proc):
            await self.worker.run(FakeCodex.thread_id, 'test', '123')
        self.assertEqual(self.s.db.execute('SELECT state FROM jobs').fetchone()[0], 'failed')
        self.assertIn('ID thread berbeda', self.last())

    async def test_missing_turn_complete_is_not_success(self):
        proc = FakeProcess(FakeCodex.thread_id, complete=False)
        with patch.object(b, 'other_clients', return_value=[]), patch.object(b.asyncio, 'create_subprocess_exec', new_callable=AsyncMock, return_value=proc):
            await self.worker.run(FakeCodex.thread_id, 'test', '123')
        self.assertEqual(self.s.db.execute('SELECT state FROM jobs').fetchone()[0], 'failed')

    async def test_cancelled_task_has_no_automatic_undo(self):
        proc = FakeProcess(FakeCodex.thread_id, complete=False, eof=False)
        with patch.object(b, 'other_clients', return_value=[]), patch.object(b.asyncio, 'create_subprocess_exec', new_callable=AsyncMock, return_value=proc), patch.object(b, 'terminate', new_callable=AsyncMock) as stop:
            self.worker.task = asyncio.create_task(self.worker.run(FakeCodex.thread_id, 'test', '123'))
            for _ in range(20):
                if self.worker.proc: break
                await asyncio.sleep(.01)
            await self.worker.handle('/codex stop', '123')
            await self.worker.task
        stop.assert_awaited_once_with(proc)
        self.assertEqual(self.s.db.execute('SELECT state FROM jobs').fetchone()[0], 'cancelled')
        self.assertIn('tetap ada', self.last())

    async def test_socket_auth_and_ack(self):
        socket = str(Path(self.temp.name) / 'test.sock')
        server = await asyncio.start_unix_server(lambda r, w: self.worker.incoming(r, w, 'secret'), socket)
        async with server:
            for secret, expected in [('wrong', False), ('secret', True), ('secret', True)]:
                r, w = await asyncio.open_unix_connection(socket)
                w.write((json.dumps({'secret': secret, 'update': update(ident=55)}) + '\n').encode())
                await w.drain()
                self.assertEqual(json.loads(await r.readline())['ok'], expected)
                w.close()
                await w.wait_closed()
        self.assertEqual(self.s.db.execute("SELECT count(*) FROM inbox WHERE id=55 AND user_id='123'").fetchone()[0], 1)

    def second(self):
        self.s.accept(update(uid=456, text='/codex pair ' + self.s.pair(additional=True), ident=2))
        return self.s.account('456')

    async def test_sessions_and_results_are_separate(self):
        second = self.second()
        await self.worker.handle('/codex resume', '123')
        self.assertEqual(second.get('choices'), '')
        with self.s.db:
            second.put('choices', json.dumps(['SECOND_THREAD_1234']))
            second.put('choices_until', int(time.time()) + 300)
            second.put('cursor', 'second-page')
        await self.worker.handle('/codex pilih 1', '123')
        await self.worker.handle('/codex pilih 1', '456')
        self.assertEqual(self.a.get('thread'), FakeCodex.thread_id)
        self.assertEqual(second.get('thread'), 'SECOND_THREAD_1234')
        self.assertEqual(second.get('cursor'), 'second-page')
        with self.s.db:
            self.s.db.execute("INSERT INTO jobs VALUES ('j','t','completed',1,'PRIVATE_RESULT_123','123')")
        await self.worker.handle('/codex status', '456')
        self.assertNotIn('PRIVATE_RESULT_123', self.last())
        await self.worker.handle('/codex status', '123')
        self.assertIn('PRIVATE_RESULT_123', self.last())
        await self.worker.handle('/codex keluar', '456')
        self.assertEqual(self.a.get('thread'), FakeCodex.thread_id)

    async def test_global_one_job_and_other_account_cannot_stop(self):
        second = self.second()
        with self.s.db:
            self.a.put('thread', FakeCodex.thread_id)
            second.put('thread', 'SECOND_THREAD_1234')
        proc = FakeProcess(FakeCodex.thread_id, complete=False, eof=False)
        with patch.object(b, 'other_clients', return_value=[]), patch.object(b.asyncio, 'create_subprocess_exec', new_callable=AsyncMock, return_value=proc) as spawn, patch.object(b, 'terminate', new_callable=AsyncMock):
            await self.worker.handle('task first', '123')
            task = self.worker.task
            for _ in range(30):
                if self.worker.proc: break
                await asyncio.sleep(.01)
            await self.worker.handle('task second', '456')
            self.assertIs(self.worker.task, task)
            self.assertIn('Pesan ini tidak dijalankan', self.last())
            await self.worker.handle('/codex stop', '456')
            self.assertIn('Gunakan akun pengirim', self.last())
            self.assertFalse(task.cancelled())
            await self.worker.handle('/codex status', '456')
            self.assertIn('akun lain', self.last())
            await self.worker.handle('/codex stop', '123')
            await task
            self.assertEqual(spawn.await_count, 1)
        self.assertEqual(self.s.db.execute('SELECT user_id FROM jobs').fetchone()[0], '123')
        final = self.s.db.execute('SELECT user_id FROM outbox ORDER BY id DESC LIMIT 1').fetchone()[0]
        self.assertEqual(final, '123')

    async def test_outbox_sends_to_immutable_recipient(self):
        self.second()
        with self.s.db: self.s.db.execute('DELETE FROM outbox')
        self.s.say('FOR_FIRST', '123')
        self.s.say('FOR_SECOND', '456')
        received = []
        class Response:
            def __enter__(self): return self
            def __exit__(self, *args): pass
            def read(self, limit): return b'{"ok":true}'
        class Sender:
            def open(self, request, **kwargs):
                received.append(json.loads(request.data))
                return Response()
        with patch.object(b.urllib.request, 'build_opener', return_value=Sender()):
            task = asyncio.create_task(self.worker.outbox('fake-test-token'))
            try:
                for _ in range(150):
                    if len(received) == 2: break
                    await asyncio.sleep(.02)
            finally:
                task.cancel()
                with contextlib.suppress(asyncio.CancelledError): await task
        self.assertEqual([(m['chat_id'], m['text']) for m in received], [('123', 'FOR_FIRST'), ('456', 'FOR_SECOND')])

    async def test_inbox_preserves_sender_for_command_and_failure(self):
        self.second()
        self.s.accept(update(uid=456, text='/codex status', ident=3))
        with patch.object(self.worker, 'handle', new_callable=AsyncMock, side_effect=ValueError('test')) as handle:
            task = asyncio.create_task(self.worker.inbox())
            try:
                for _ in range(40):
                    if handle.await_count: break
                    await asyncio.sleep(.01)
            finally:
                task.cancel()
                with contextlib.suppress(asyncio.CancelledError): await task
        handle.assert_awaited_once_with('/codex status', '456')
        self.assertEqual(self.s.db.execute('SELECT user_id FROM outbox ORDER BY id DESC LIMIT 1').fetchone()[0], '456')


class Migration(unittest.TestCase):
    def test_legacy_primary_history_and_choices_preserved_once(self):
        with tempfile.TemporaryDirectory(prefix='finance-codex-migrate-') as directory:
            path = Path(directory)
            db = sqlite3.connect(str(path / 'bridge.sqlite'))
            db.executescript('''
                CREATE TABLE settings (key TEXT PRIMARY KEY,value TEXT NOT NULL);
                INSERT INTO settings VALUES ('owner','123'),('thread','LEGACY_THREAD'),('cursor','legacy-cursor');
                CREATE TABLE inbox (id INTEGER PRIMARY KEY,text TEXT NOT NULL,state TEXT NOT NULL DEFAULT 'pending');
                INSERT INTO inbox VALUES (1,'legacy prompt','done');
                CREATE TABLE jobs (id TEXT PRIMARY KEY,thread TEXT NOT NULL,state TEXT NOT NULL,created INTEGER NOT NULL,result TEXT NOT NULL DEFAULT '');
                INSERT INTO jobs VALUES ('j','LEGACY_THREAD','completed',1,'legacy result');
                CREATE TABLE outbox (id INTEGER PRIMARY KEY,text TEXT NOT NULL,state TEXT NOT NULL DEFAULT 'pending');
                INSERT INTO outbox VALUES (1,'legacy reply','pending');
            ''')
            db.close()
            store = b.Store(path)
            self.assertEqual(store.get('owner'), '123')
            self.assertTrue(store.authorized('123'))
            self.assertEqual(store.account('123').get('thread'), 'LEGACY_THREAD')
            self.assertEqual(store.account('123').get('choices_until', '0'), '0')
            for table in ('inbox', 'jobs', 'outbox'):
                self.assertEqual(store.db.execute('SELECT user_id FROM ' + table).fetchone()[0], '123')
            code = store.pair(additional=True)
            store.accept(update(uid=456, text='/codex pair ' + code, ident=2))
            with store.db: store.account('123').put('thread', 'NEW_PRIMARY_THREAD')
            store.db.close()
            store = b.Store(path)
            self.assertEqual(store.account('123').get('thread'), 'NEW_PRIMARY_THREAD')
            self.assertEqual(store.account('456').get('thread'), '')
            self.assertEqual(store.db.execute('SELECT user_id FROM jobs').fetchone()[0], '123')
            self.assertEqual(store.db.execute('PRAGMA integrity_check').fetchone()[0], 'ok')
            store.db.close()


if __name__ == '__main__':
    unittest.main()
