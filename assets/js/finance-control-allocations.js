(() => {
  'use strict';
  const config = document.getElementById('fc-operations-config');
  if (!config) return;
  const notice = (text, error = false) => {
    const box = document.getElementById('fc-message');
    box.textContent = text; box.className = 'alert fc-alert ' + (error ? 'alert-danger' : 'alert-success');
    box.scrollIntoView({block: 'nearest'});
  };
  const post = async (kind, payload) => {
    const response = await fetch(config.dataset.base + 'save/' + kind, {method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-Finance-Control-CSRF': config.dataset.csrf}, body: JSON.stringify(payload)});
    const data = await response.json();
    if (!response.ok || !data.ok) throw Error(data.message || 'Penyimpanan ditolak.');
    return data;
  };
  document.querySelectorAll('.fc-allocation-form').forEach(form => {
    const rows = form.querySelector('[data-role=parts]'), choices = form.querySelector('[data-role=choices]');
    let page = 1, sequence = 0;
    const add = (id, label, amount) => {
      if (rows.querySelector(`[data-id="${Number(id)}"]`)) { notice('Rekap sudah ditambahkan.', true); return; }
      if (rows.children.length >= 25) { notice('Maksimal 25 rekap.', true); return; }
      const row = document.createElement('div'); row.className = 'fc-filter'; row.dataset.id = Number(id);
      const title = document.createElement('label'); title.textContent = label;
      const input = document.createElement('input'); input.type = 'number'; input.min = '0.01'; input.step = '0.01'; input.max = '999999999999.99'; input.required = true; input.className = 'form-control'; input.value = amount;
      title.append(input); const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-outline-danger'; remove.textContent = 'Lepas rekap'; remove.onclick = () => row.remove();
      row.append(title, remove); rows.append(row);
    };
    JSON.parse(form.dataset.parts).forEach(p => add(p.settlement_id, `#${p.settlement_id} · ${p.revenue_date} · ${p.method_name}`, p.amount));
    const search = async () => {
      const serial = ++sequence;
      try {
        const response = await fetch(config.dataset.base + 'lookup/settlements?' + new URLSearchParams({q: form.querySelector('[data-role=allocation-search]').value, page}), {credentials: 'same-origin'});
        const data = await response.json(); if (serial !== sequence) return;
        if (!response.ok || !data.ok) throw Error(data.message || 'Pencarian gagal.');
        choices.replaceChildren(new Option('Pilih rekap', ''));
        data.rows.filter(r => Number(r.account_id) === Number(form.dataset.account)).forEach(r => choices.add(new Option(`#${r.id} · ${r.revenue_date} · ${r.method_name} · ${r.provider_reference}`, r.id)));
        form.querySelector('[data-role=next]').disabled = data.page >= data.pages;
      } catch (e) { notice(e.message, true); }
    };
    form.querySelector('[data-role=search]').onclick = () => {page = 1; search();};
    form.querySelector('[data-role=next]').onclick = () => {page++; search();};
    form.querySelector('[data-role=add]').onclick = () => {if (choices.value) add(choices.value, choices.selectedOptions[0].textContent, '');};
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (form.dataset.busy || !form.reportValidity()) return;
      if (!confirm('Simpan pembagian transfer? Total konfirmasi seluruh rekap terkait akan dihitung ulang. Saldo bank tetap.')) return;
      const payload = Object.fromEntries(new FormData(form));
      payload.allocations = JSON.stringify([...rows.children].map(row => ({settlement_id: Number(row.dataset.id), amount: row.querySelector('input').value})));
      form.dataset.busy = '1';
      try {const data = await post('receipt-distribute', payload); notice(data.message); location.reload();}
      catch (e) {delete form.dataset.busy; notice(e.message, true);}
    });
  });
  const form = document.getElementById('fc-bank-import'); if (!form) return;
  const confirmButton = form.querySelector('[data-role=confirm]'); let preview = null, busy = false;
  const clear = () => {preview = null; confirmButton.disabled = true; document.getElementById('fc-bank-preview').replaceChildren();};
  form.addEventListener('change', clear); form.addEventListener('input', clear);
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || !form.reportValidity()) return;
    const file = form.querySelector('[data-role=csv]').files[0];
    if (!file || file.size > 1048576) {notice('Pilih CSV maksimal 1 MB.', true); return;}
    clear(); busy = true;
    const fields = [...form.querySelectorAll('input, select, button')]; fields.forEach(field => field.disabled = true);
    try {
      // Read fields explicitly while disabled to keep the preview bound to the displayed mapping.
      const payload = {}; for (const field of form.querySelectorAll('[name]')) payload[field.name] = field.value;
      for (const key of ['date', 'reference', 'in', 'out']) payload['column_' + key] = Number(payload['column_' + key]) - 1;
      payload.csv = new TextDecoder('utf-8', {fatal: true}).decode(await file.arrayBuffer());
      const data = await post('statement-import', payload);
      preview = {...payload, confirm_hash: data.preview_hash};
      const box = document.getElementById('fc-bank-preview'), table = document.createElement('table'); table.className = 'fc-table';
      const caption = document.createElement('caption'); caption.textContent = `${data.rows.length} baris. Belum diimpor; saldo tidak berubah.`; table.append(caption);
      for (const r of data.rows) {const tr = document.createElement('tr'); for (const value of [r.statement_date, r.reference_no, r.direction, r.amount]) {const td = document.createElement('td'); td.textContent = value; tr.append(td);} table.append(tr);}
      box.append(table); notice(data.message);
    } catch (e) {clear(); notice(e.message, true);}
    finally {busy = false; fields.forEach(field => field.disabled = false); confirmButton.disabled = !preview;}
  });
  confirmButton.onclick = async () => {
    if (busy || !preview || !confirm('Impor baris rekening koran ini sebagai pembanding, tanpa membuat transaksi kas?')) return;
    busy = true; confirmButton.disabled = true;
    try {const data = await post('statement-import', preview); notice(data.message); location.reload();}
    catch (e) {notice(e.message, true); busy = false; confirmButton.disabled = !preview;}
  };
})();
