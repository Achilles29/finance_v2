(() => {
  'use strict';
  const root = document.querySelector('.gl-workspace');
  if (!root) return;
  const form = document.getElementById('gl-entry'), body = document.getElementById('gl-lines');
  const message = document.getElementById('gl-message');
  const cents = value => {
    const match = /^(\d{1,12})(?:\.(\d{1,2}))?$/.exec(value);
    if (!match) throw new Error('Gunakan angka tanpa pemisah ribuan, maksimal dua desimal.');
    return BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0'));
  };
  const format = value => `${value < 0n ? '-' : ''}${((value < 0n ? -value : value) / 100n).toLocaleString('id-ID')},${String((value < 0n ? -value : value) % 100n).padStart(2, '0')}`;
  const totals = () => {
    if (!body) return;
    try {
      let debit = 0n, credit = 0n;
      body.querySelectorAll('tr').forEach(row => {
        debit += cents(row.dataset.fixedDebit ?? row.querySelector('[data-debit]').value);
        credit += cents(row.dataset.fixedCredit ?? row.querySelector('[data-credit]').value);
      });
      document.getElementById('gl-debit').textContent = format(debit);
      document.getElementById('gl-credit').textContent = format(credit);
      document.getElementById('gl-difference').textContent = debit === credit ? 'Seimbang' : `Selisih ${format(debit - credit)}`;
    } catch (error) { document.getElementById('gl-difference').textContent = error.message; }
  };
  const add = () => {
    if (body.querySelectorAll('tr').length >= 100) return;
    const row = document.getElementById('gl-line-template').content.cloneNode(true);
    body.appendChild(row); totals();
  };
  let busy = false;
  const submit = async (payload, button) => {
    if (busy) return;
    if (!window.confirm('Posting jurnal ini? Pastikan akun, tanggal dan dokumen benar. Saldo kas tidak diubah oleh jurnal.')) return;
    busy = true; button.disabled = true; message.className = ''; message.textContent = 'Menyimpan jurnal…';
    try {
      const response = await fetch(root.dataset.glUrl, {method: 'POST', credentials: 'same-origin', headers: {
        'Content-Type': 'application/json', 'X-Finance-Accounting-CSRF': root.dataset.glCsrf,
        'X-Requested-With': 'XMLHttpRequest'
      }, body: JSON.stringify(payload)});
      let data;
      try { data = await response.json(); } catch (_) { throw new Error('Respons tidak dapat dibaca. Periksa sesi/koneksi lalu coba lagi dengan formulir yang sama.'); }
      if (!response.ok || !data.ok) throw new Error(data.message || 'Jurnal belum berhasil disimpan.');
      const target = new URL(root.dataset.glHome, window.location.href);
      target.searchParams.set('tab', 'journals'); target.searchParams.set('month', payload.date.slice(0, 7)); target.searchParams.set('id', String(data.id));
      window.location.assign(target.href);
    } catch (error) {
      message.className = 'alert alert-danger'; message.textContent = error.message || 'Koneksi gagal. Coba lagi dengan formulir yang sama agar tidak tercatat dua kali.';
      busy = false; button.disabled = false;
    }
  };
  if (form) {
    let assistant = {};
    const choice = document.getElementById('gl-assistant-choice'), confirmation = document.getElementById('gl-assistant-confirm');
    const state = document.getElementById('gl-assistant-state');
    const manual = () => {
      if (assistant.assistant_scenario && state) state.textContent = 'Isian diubah: sekarang mode manual. Periksa akun, nominal dan kelompok arus kas sebelum posting.';
      assistant = {};
    };
    document.getElementById('gl-add').addEventListener('click', () => { manual(); add(); });
    body.addEventListener('input', () => { manual(); totals(); });
    body.addEventListener('change', manual);
    body.addEventListener('click', event => { const button = event.target.closest('[data-remove]'); if (button) { manual(); button.closest('tr').remove(); totals(); } });
    form.elements.cashflow_class?.addEventListener('change', manual);
    if (form.dataset.autoCounter !== '1') add();
    const suggestion = document.getElementById('gl-suggestion');
    if (choice) {
      choice.addEventListener('change', () => {
        manual(); confirmation.checked = false;
        const selected = choice.selectedOptions[0];
        document.getElementById('gl-assistant-explanation').textContent = choice.value
          ? `${selected.dataset.help} ${selected.dataset.configured === '1' ? 'Menggunakan pemetaan tersimpan.' : 'Saran bawaan; belum dikonfirmasi pengelola.'}`
          : 'Mode manual: tentukan akun dari bukti transaksi, termasuk bila satu pembayaran perlu dipecah ke beberapa akun.';
      });
      confirmation.addEventListener('change', () => { if (!confirmation.checked) manual(); });
      document.getElementById('gl-assistant-apply').addEventListener('click', () => {
        if (!choice.value || !confirmation.checked) { state.textContent = 'Pilih jenis transaksi dan centang konfirmasi pemeriksaan bukti dahulu.'; return; }
        const selected = choice.selectedOptions[0];
        const entered = [...body.querySelectorAll('[data-edit-line]')].some(row => row.querySelector('[data-code]').value || row.querySelector('[data-debit]').value !== '0' || row.querySelector('[data-credit]').value !== '0');
        if (entered && !window.confirm('Ganti baris akun manual dengan satu baris saran? Baris kas dari sumber tetap dipertahankan.')) return;
        body.querySelectorAll('[data-edit-line]').forEach(row => row.remove()); add();
        const row = body.querySelector('[data-edit-line]');
        row.querySelector('[data-code]').value = selected.dataset.account;
        row.querySelector(selected.dataset.direction === 'IN' ? '[data-credit]' : '[data-debit]').value = suggestion.dataset.amount;
        form.elements.cashflow_class.value = selected.dataset.flow;
        assistant = {assistant_scenario: choice.value, assistant_hash: selected.dataset.hash, assistant_confirmed: true};
        state.textContent = 'Saran terisi. Periksa baris debit/kredit dan dampaknya di bawah; belum ada jurnal yang diposting.';
        totals();
      });
    }
    if (form.dataset.kind === 'ADJUSTMENT') add();
    totals();
    form.addEventListener('submit', event => {
      event.preventDefault();
      const lines = [...body.querySelectorAll('[data-edit-line]')].map(row => ({account_code: row.querySelector('[data-code]').value, debit: row.querySelector('[data-debit]').value, credit: row.querySelector('[data-credit]').value}));
      submit({kind: form.dataset.kind, date: form.elements.date.value, reference: form.elements.reference.value,
        memo: form.elements.memo.value, source_id: Number(form.dataset.source), source_hash: form.dataset.hash,
        cashflow_class: form.elements.cashflow_class?.value || '', request_key: form.dataset.key, lines, ...assistant}, form.querySelector('[type="submit"]'));
    });
  }
  const reverse = document.getElementById('gl-reverse');
  if (reverse) reverse.addEventListener('submit', event => {
    event.preventDefault();
    submit({kind: 'REVERSAL', journal_id: Number(reverse.dataset.id), date: reverse.elements.date.value,
      reference: `Pembalik GL-${reverse.dataset.id}`, memo: reverse.elements.memo.value, request_key: reverse.dataset.key, lines: []}, reverse.querySelector('button'));
  });
  document.querySelectorAll('.gl-setup-form').forEach(settings => settings.addEventListener('submit', async event => {
    event.preventDefault();
    const button = settings.querySelector('button');
    if (busy || !button || button.disabled) return;
    if (!window.confirm('Simpan pengaturan jurnal? Ini tidak memposting transaksi atau menghitung ulang jurnal lama.')) return;
    busy = true; button.disabled = true;
    let feedback = settings.querySelector('[data-gl-feedback]');
    if (!feedback) { feedback = document.createElement('p'); feedback.dataset.glFeedback = ''; feedback.setAttribute('role', 'status'); settings.appendChild(feedback); }
    feedback.className = 'gl-muted'; feedback.textContent = 'Menyimpan pengaturan…';
    try {
      const response = await fetch(root.dataset.glSetup.replace(/\/$/, '') + '/' + settings.dataset.kind, {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json',
          'X-Finance-Accounting-CSRF': root.dataset.glCsrf, 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify(Object.fromEntries(new FormData(settings)))
      });
      let result;
      try { result = await response.json(); } catch (_) { throw new Error('Respons tidak terbaca. Periksa koneksi/sesi, lalu coba simpan kembali.'); }
      if (!response.ok || !result.ok) throw new Error(result.message || 'Pengaturan belum tersimpan.');
      window.location.reload();
    } catch (error) {
      feedback.className = 'text-danger'; feedback.textContent = error.message || 'Koneksi gagal. Coba simpan kembali.';
      busy = false; button.disabled = false;
    }
  }));
  document.querySelectorAll('[data-gl-search]').forEach(search => search.addEventListener('input', () => {
    const term = search.value.trim().toLocaleLowerCase('id-ID');
    document.querySelectorAll('[data-gl-filter]').forEach(row => {
      if (row.dataset.glFilter === search.dataset.glSearch) row.hidden = !row.textContent.toLocaleLowerCase('id-ID').includes(term);
    });
  }));
})();
