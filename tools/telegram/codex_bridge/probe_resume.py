"""Explicit optional live CLI probe: two text-only turns in a NEW test thread.

Uses current Codex login/model; consumes quota. Never resumes a user thread.
Archives only the test thread this process created, preserving its history.
"""
import asyncio
import json
import subprocess

from bridge import CODEX, ROOT, Codex, exec_args


def call(args, prompt):
    proc = subprocess.run(args, input=prompt, text=True, stdout=subprocess.PIPE,
                          stderr=subprocess.DEVNULL, timeout=120, cwd=ROOT)
    thread = None
    answer = ''
    complete = False
    for line in proc.stdout.splitlines():
        try:
            event = json.loads(line)
        except ValueError:
            continue
        if event.get('type') == 'thread.started':
            thread = event.get('thread_id')
        if event.get('type') == 'turn.completed':
            complete = True
        if event.get('type') == 'item.completed' and event.get('item', {}).get('type') == 'agent_message':
            answer = event['item'].get('text', '').strip()
    if proc.returncode or not thread or not complete:
        raise RuntimeError('CLI probe incomplete (diagnostic details withheld).')
    return thread, answer


if __name__ == '__main__':
    ident = None
    try:
        ident, answer = call([CODEX, 'exec', '-C', str(ROOT), '--sandbox', 'read-only',
            '-c', 'approval_policy="never"', '-c', 'notify=[]', '-c', 'model_reasoning_effort="low"', '--json', '-'],
            'Ini thread diagnostik integrasi, bukan tugas aplikasi. Jangan gunakan tool atau membaca/mengubah file. '
            'Ingat kode konteks BRIDGE_CONTEXT_7281. Jawab tepat READY.')
        print(json.dumps({'new_thread_created': True, 'first_reply_ok': answer == 'READY'}), flush=True)
        args = exec_args(ident)
        args[args.index('resume'):args.index('resume')] = ['-c', 'model_reasoning_effort="low"']
        resumed, answer = call(args,
            'Probe teks saja. Jangan gunakan tool atau membaca/mengubah file. '
            'Jawab hanya kode konteks BRIDGE_CONTEXT yang disebut pada pesan pertama.')
        ok = resumed == ident and answer == 'BRIDGE_CONTEXT_7281'
        print(json.dumps({'exact_thread_resumed': resumed == ident, 'prior_context_retained': ok}), flush=True)
        if not ok:
            raise RuntimeError('Resume context check failed.')
    finally:
        if ident:
            asyncio.run(Codex().rpc('thread/archive', {'threadId': ident}))
            print('Diagnostic thread archived; history retained.', flush=True)
