<?php
$templateFilters = is_array($template_filters ?? null) ? $template_filters : [];
?>
<style>
  .printer-page-card{border:0;border-radius:22px;box-shadow:0 14px 34px rgba(57,39,32,.07);background:linear-gradient(180deg,#fffdfb 0%,#fff 100%)}
  .printer-page-kicker{display:inline-flex;align-items:center;gap:.45rem;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#8a786c}
  .printer-page-empty{border:1px dashed rgba(179,161,147,.55);border-radius:16px;padding:1rem 1.1rem;color:#8b7c71;background:#fffaf6}
  .printer-page-note{font-size:.82rem;color:#8a786c}
  .printer-page-code{font-weight:800;color:#3b2d31}
</style>
<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Template Printer POS</h4>
      <p class="fin-page-subtitle mb-0">Fokus ke format dokumen dulu. Atur receipt, kitchen ticket, refund, dan void per template agar tim operasional tidak bingung.</p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>
  <?php $this->load->view('pos/_printer_tabs', ['printer_tab_active' => 'templates']); ?>

  <div class="card printer-page-card">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
          <div class="printer-page-kicker"><i class="ri-file-list-3-line"></i> Template Dokumen</div>
          <h5 class="mt-2 mb-1">Receipt, KOT, Refund, Void</h5>
          <p class="mb-0 text-muted">Pilih satu template per kebutuhan cetak. Detail visual dan preview live tetap dikelola di halaman editor template.</p>
        </div>
        <a href="<?= site_url('pos/printers/templates/create') ?>" class="btn btn-primary"><i class="ri-add-line me-1"></i>Tambah Template</a>
      </div>

      <form class="row g-2 mb-3">
        <div class="col-lg-6">
          <input id="template_q" class="form-control" placeholder="Cari kode / nama template / jenis dokumen">
        </div>
        <div class="col-lg-2">
          <select id="document_type" class="form-select">
            <option value="ALL">Semua Dokumen</option>
            <?php foreach (['RECEIPT','KITCHEN_TICKET','REFUND_SLIP','VOID_SLIP','DEPOSIT_RECEIPT'] as $doc): ?>
              <option value="<?= $doc ?>"><?= $doc ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <select id="template_limit" class="form-select">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="20">20</option>
          </select>
        </div>
        <div class="col-lg-2 d-grid">
          <button type="button" id="btn-clear-template" class="btn btn-outline-danger">Clear</button>
        </div>
      </form>

      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-sm btn-outline-primary template-status-tab" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary template-status-tab" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary template-status-tab" data-status="ALL">Semua</button>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle table-hover">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Template</th>
              <th>Dokumen</th>
              <th class="text-center">Default</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width:220px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="template-table-body"></tbody>
        </table>
      </div>
      <div id="template-empty-state" class="printer-page-empty d-none">Belum ada template printer yang cocok dengan filter ini.</div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <small id="template-pagination-info" class="text-muted"></small>
        <div class="d-flex gap-1" id="template-pagination"></div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialTemplateFilters = <?php echo json_encode($templateFilters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const templateState = { q: initialTemplateFilters.q || '', status: initialTemplateFilters.status || 'ACTIVE', document_type: initialTemplateFilters.document_type || 'ALL', page: parseInt(initialTemplateFilters.page || 1, 10), limit: parseInt(initialTemplateFilters.limit || 10, 10) || 10 };

  function escapeHtml(v) { return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m])); }
  function yesNoBadge(flag, textYes='Ya', textNo='Tidak') { return Number(flag || 0) === 1 ? `<span class="badge bg-success-subtle text-success-emphasis">${textYes}</span>` : `<span class="badge bg-secondary-subtle text-secondary-emphasis">${textNo}</span>`; }
  function statusBadge(flag) { return Number(flag || 0) === 1 ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>'; }
  async function getJson(url) { const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch (e) { const snippet = String(t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220); throw new Error(snippet ? `Response backend tidak valid: ${snippet}` : 'Response backend tidak valid dan bukan JSON.'); } if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data'); return j; }
  async function postJson(url, payload) { const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }); const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch (e) { const snippet = String(t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220); throw new Error(snippet ? `Response save backend tidak valid: ${snippet}` : 'Response save backend tidak valid dan bukan JSON.'); } if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data'); return j; }
  function pager(meta) {
    const total = Number(meta.total || 0), page = Number(meta.page || 1), totalPages = Number(meta.total_pages || 1), limit = Number(meta.limit || templateState.limit || 10);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    document.getElementById('template-pagination-info').textContent = total ? `Menampilkan ${start}-${end} dari ${total} template` : 'Belum ada template';
    document.getElementById('template-pagination').innerHTML = Array.from({ length: totalPages }, (_, idx) => { const p = idx + 1; return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`; }).join('');
    document.querySelectorAll('#template-pagination button').forEach((btn) => btn.addEventListener('click', () => { templateState.page = Number(btn.dataset.page || 1); loadTemplates(); }));
  }
  function syncControls(){ document.getElementById('template_q').value = templateState.q; document.getElementById('document_type').value = templateState.document_type; document.getElementById('template_limit').value = String(templateState.limit); document.querySelectorAll('.template-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === templateState.status)); }
  function templateQs(){ const p = new URLSearchParams(); p.set('template_q', templateState.q); p.set('template_status', templateState.status); p.set('document_type', templateState.document_type); p.set('template_page', templateState.page); p.set('template_limit', templateState.limit); return p.toString(); }
  async function loadTemplates() {
    syncControls();
    const json = await getJson('<?= site_url('pos/printers/templates/data'); ?>?' + templateQs());
    const rows = json.rows || [];
    const body = document.getElementById('template-table-body');
    const empty = document.getElementById('template-empty-state');
    if (!rows.length) { body.innerHTML = ''; empty.classList.remove('d-none'); }
    else {
      empty.classList.add('d-none');
      body.innerHTML = rows.map((r) => `
        <tr>
          <td><div class="printer-page-code">${escapeHtml(r.template_code || '-')}</div></td>
          <td><div class="fw-semibold">${escapeHtml(r.template_name || '-')}</div><div class="printer-page-note">Editor visual & preview live</div></td>
          <td><span class="badge text-bg-light border">${escapeHtml(r.document_type || 'OTHER')}</span></td>
          <td class="text-center">${yesNoBadge(r.is_default)}</td>
          <td class="text-center">${statusBadge(r.is_active)}</td>
          <td class="text-center"><div class="d-inline-flex gap-1 flex-wrap justify-content-center"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('pos/printers/templates/edit'); ?>/${Number(r.id||0)}">Edit Live</a><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('pos/printers/templates/preview'); ?>/${Number(r.id||0)}">Preview</a><button type="button" class="btn btn-sm ${Number(r.is_active||0)===1?'btn-outline-danger':'btn-outline-success'} btn-template-toggle" data-id="${Number(r.id||0)}">${Number(r.is_active||0)===1?'Nonaktifkan':'Aktifkan'}</button></div></td>
        </tr>`).join('');
    }
    pager(json.meta || {});
    body.querySelectorAll('.btn-template-toggle').forEach((btn) => btn.addEventListener('click', async () => { await postJson('<?= site_url('pos/printers/templates/toggle'); ?>/' + btn.dataset.id, {}); await loadTemplates(); }));
  }
  document.querySelectorAll('.template-status-tab').forEach((btn) => btn.addEventListener('click', () => { templateState.status = btn.dataset.status; templateState.page = 1; loadTemplates(); }));
  document.getElementById('template_q').addEventListener('input', (e) => { templateState.q = e.target.value; templateState.page = 1; loadTemplates(); });
  document.getElementById('document_type').addEventListener('change', (e) => { templateState.document_type = e.target.value; templateState.page = 1; loadTemplates(); });
  document.getElementById('template_limit').addEventListener('change', (e) => { templateState.limit = Number(e.target.value || 10); templateState.page = 1; loadTemplates(); });
  document.getElementById('btn-clear-template').addEventListener('click', () => { templateState.q = ''; templateState.status = 'ACTIVE'; templateState.document_type = 'ALL'; templateState.page = 1; templateState.limit = 10; loadTemplates(); });
  loadTemplates().catch((e) => alert(e.message));
});
</script>
