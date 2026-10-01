<?php
$profileFilters = is_array($profile_filters ?? null) ? $profile_filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$printers = is_array($filterOptions['printers'] ?? null) ? $filterOptions['printers'] : [];
?>
<style>
  .printer-page-card{border:0;border-radius:22px;box-shadow:0 14px 34px rgba(57,39,32,.07);background:linear-gradient(180deg,#fffdfb 0%,#fff 100%)}
  .printer-page-kicker{display:inline-flex;align-items:center;gap:.45rem;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#8a786c}
  .printer-page-empty{border:1px dashed rgba(179,161,147,.55);border-radius:16px;padding:1rem 1.1rem;color:#8b7c71;background:#fffaf6}
</style>
<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Pengaturan Output Printer POS</h4>
      <p class="fin-page-subtitle mb-0">Halaman ini khusus untuk kualitas hasil cetak: kertas, copy, cut mode, logo, footer, dan harga. Jadi tidak bercampur dengan template atau device.</p>
    </div>
  </div>
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'printer']); ?>
  <?php $this->load->view('pos/_printer_tabs', ['printer_tab_active' => 'profiles']); ?>

  <div class="card printer-page-card">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
          <div class="printer-page-kicker"><i class="ri-layout-4-line"></i> Output Printer</div>
          <h5 class="mt-2 mb-1">Kertas, Copy, Footer, Harga</h5>
          <p class="mb-0 text-muted">Satu printer satu profile output. Ini membantu tim kasir membedakan masalah layout cetak dengan masalah device fisik.</p>
        </div>
        <button type="button" class="btn btn-warning" id="btn-new-profile"><i class="ri-equalizer-line me-1"></i>Tambah Profile</button>
      </div>
      <form class="row g-2 mb-3">
        <div class="col-md-9"><input id="profile_q" class="form-control" placeholder="Cari nama printer / role / outlet"></div>
        <div class="col-md-3"><button type="button" id="btn-clear-profile" class="btn btn-outline-danger w-100">Clear</button></div>
      </form>
      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-sm btn-outline-primary profile-status-tab" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary profile-status-tab" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary profile-status-tab" data-status="ALL">Semua</button>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle table-hover">
          <thead><tr><th>Printer</th><th>Role / Scope</th><th class="text-center">Paper</th><th class="text-center">Copy</th><th class="text-center">Output</th><th class="text-center">Status</th><th class="text-center" style="width:220px;">Aksi</th></tr></thead>
          <tbody id="profile-table-body"></tbody>
        </table>
      </div>
      <div id="profile-empty-state" class="printer-page-empty d-none">Belum ada profile printer yang bisa ditampilkan.</div>
      <div class="d-flex justify-content-between align-items-center mt-3"><small id="profile-pagination-info" class="text-muted"></small><div class="d-flex gap-1" id="profile-pagination"></div></div>
    </div>
  </div>
</div>
<div class="modal fade finance-ui-modal" id="printerProfileModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title" id="printerProfileModalLabel">Tambah Profile Printer</h5><div class="small text-muted">Profile ini menyimpan pengaturan output printer yang dipakai runtime POS.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="profile-form" class="row g-3"><input type="hidden" name="id" value=""><div class="col-md-6"><label class="form-label mb-1 small text-muted">Printer</label><select class="form-select" name="printer_id" required><option value="">Pilih Printer</option><?php foreach ($printers as $printer): ?><option value="<?php echo (int)$printer['id']; ?>"><?php echo html_escape((string)$printer['printer_name']); ?> | <?php echo html_escape((string)($printer['printer_role'] ?? 'CUSTOM')); ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label mb-1 small text-muted">Paper (mm)</label><select class="form-select" name="paper_width_mm"><option value="80">80</option><option value="58">58</option></select></div><div class="col-md-3"><label class="form-label mb-1 small text-muted">Copy</label><input type="number" class="form-control" name="copy_count" min="1" max="10" value="1"></div><div class="col-md-4"><label class="form-label mb-1 small text-muted">Cut Mode</label><select class="form-select" name="cut_mode"><option value="PARTIAL">PARTIAL</option><option value="FULL">FULL</option><option value="NONE">NONE</option></select></div><div class="col-md-4"><label class="form-label mb-1 small text-muted">Show Logo</label><select class="form-select" name="show_logo"><option value="1">Ya</option><option value="0">Tidak</option></select></div><div class="col-md-4"><label class="form-label mb-1 small text-muted">Show Price</label><select class="form-select" name="show_price"><option value="1">Ya</option><option value="0">Tidak</option></select></div><div class="col-md-4"><label class="form-label mb-1 small text-muted">Show Footer</label><select class="form-select" name="show_footer"><option value="1">Ya</option><option value="0">Tidak</option></select></div><div class="col-md-4"><label class="form-label mb-1 small text-muted">Open Drawer</label><select class="form-select" name="open_drawer"><option value="0">Tidak</option><option value="1">Ya</option></select></div><div class="col-md-4"><label class="form-label mb-1 small text-muted">Status</label><select class="form-select" name="is_active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div></form></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="btn-save-profile">Simpan Profile</button></div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialProfileFilters = <?php echo json_encode($profileFilters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const profileState = { q: initialProfileFilters.q || '', status: initialProfileFilters.status || 'ACTIVE', page: parseInt(initialProfileFilters.page || 1, 10), limit: parseInt(initialProfileFilters.limit || 10, 10) || 10 };
  const profileModal = new bootstrap.Modal(document.getElementById('printerProfileModal')); const profileForm = document.getElementById('profile-form');
  function escapeHtml(v) { return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m])); }
  function statusBadge(flag) { return Number(flag || 0) === 1 ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>' : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>'; }
  async function getJson(url) { const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch (e) { const snippet = String(t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220); throw new Error(snippet ? `Response backend tidak valid: ${snippet}` : 'Response backend tidak valid dan bukan JSON.'); } if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data'); return j; }
  async function postJson(url, payload) { const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) }); const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch (e) { const snippet = String(t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220); throw new Error(snippet ? `Response save backend tidak valid: ${snippet}` : 'Response save backend tidak valid dan bukan JSON.'); } if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data'); return j; }
  function rowPayload(row) { return encodeURIComponent(JSON.stringify(row || {})); }
  function parseRow(payload) { return JSON.parse(decodeURIComponent(payload)); }
  function pager(meta) { const total=Number(meta.total||0), page=Number(meta.page||1), totalPages=Number(meta.total_pages||1), limit=Number(meta.limit||profileState.limit||10); const start=total===0?0:((page-1)*limit)+1; const end=Math.min(total,page*limit); document.getElementById('profile-pagination-info').textContent = total ? `Menampilkan ${start}-${end} dari ${total} profile` : 'Belum ada profile'; document.getElementById('profile-pagination').innerHTML = Array.from({ length: totalPages }, (_, idx) => { const p = idx + 1; return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'}" data-page="${p}">${p}</button>`; }).join(''); document.querySelectorAll('#profile-pagination button').forEach((btn)=>btn.addEventListener('click',()=>{ profileState.page=Number(btn.dataset.page||1); loadProfiles(); })); }
  function syncControls(){ document.getElementById('profile_q').value=profileState.q; document.querySelectorAll('.profile-status-tab').forEach((btn)=>btn.classList.toggle('active', btn.dataset.status===profileState.status)); }
  function profileQs(){ const p=new URLSearchParams(); p.set('profile_q', profileState.q); p.set('profile_status', profileState.status); p.set('profile_page', profileState.page); p.set('profile_limit', profileState.limit); return p.toString(); }
  function openProfile(row = null) { profileForm.reset(); profileForm.elements.id.value = row?.id || ''; profileForm.elements.printer_id.value = row?.id || row?.printer_id || ''; profileForm.elements.paper_width_mm.value = String(row?.paper_width_mm || 80); profileForm.elements.copy_count.value = row?.copy_count || 1; profileForm.elements.cut_mode.value = row?.cut_mode || 'PARTIAL'; profileForm.elements.open_drawer.value = Number(row?.open_drawer || 0); profileForm.elements.show_logo.value = Number(row?.show_logo ?? 1); profileForm.elements.show_price.value = (String(row?.price_visibility || 'always').toLowerCase() === 'never') ? 0 : 1; profileForm.elements.show_footer.value = Number(row?.show_footer ?? 1); profileForm.elements.is_active.value = Number(row?.is_active ?? 1); document.getElementById('printerProfileModalLabel').textContent = row ? `Edit Profile: ${row.profile_name}` : 'Tambah Profile Printer'; profileModal.show(); }
  async function loadProfiles(){ syncControls(); const json = await getJson('<?= site_url('pos/printers/profiles/data'); ?>?' + profileQs()); const rows = json.rows || []; const body = document.getElementById('profile-table-body'); const empty=document.getElementById('profile-empty-state'); if(!rows.length){ body.innerHTML=''; empty.classList.remove('d-none'); } else { empty.classList.add('d-none'); body.innerHTML = rows.map((r)=>`<tr><td><div class="fw-semibold">${escapeHtml(r.profile_name || '-')}</div><div class="small text-muted">${escapeHtml(r.profile_code || '-')} | ${escapeHtml(r.outlet_name || 'Global')}</div></td><td><div>${escapeHtml(r.printer_role || 'CUSTOM')}</div><div class="small text-muted">${escapeHtml(r.print_scope || 'DIVISION')}</div></td><td class="text-center">${escapeHtml(r.paper_width_mm || 80)}mm</td><td class="text-center">${escapeHtml(r.copy_count || 1)}x</td><td class="text-center"><div class="small text-muted">Logo ${Number(r.show_logo||0)?'On':'Off'} | Footer ${Number(r.show_footer||0)?'On':'Off'}</div><div class="small text-muted">Harga ${String(r.price_visibility || 'always').toLowerCase()==='never'?'Hide':'Show'}</div></td><td class="text-center">${statusBadge(r.is_active)}</td><td class="text-center"><div class="d-inline-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary btn-profile-edit" data-row="${rowPayload(r)}">Edit</button><button type="button" class="btn btn-sm ${Number(r.is_active||0)===1?'btn-outline-danger':'btn-outline-success'} btn-profile-toggle" data-id="${Number(r.id||0)}">${Number(r.is_active||0)===1?'Nonaktifkan':'Aktifkan'}</button></div></td></tr>`).join(''); }
    pager(json.meta || {}); body.querySelectorAll('.btn-profile-edit').forEach((btn)=>btn.addEventListener('click',()=>openProfile(parseRow(btn.dataset.row)))); body.querySelectorAll('.btn-profile-toggle').forEach((btn)=>btn.addEventListener('click', async()=>{ await postJson('<?= site_url('pos/printers/profiles/toggle'); ?>/' + btn.dataset.id, {}); await loadProfiles(); })); }
  document.getElementById('btn-new-profile').addEventListener('click',()=>openProfile());
  document.querySelectorAll('.profile-status-tab').forEach((btn)=>btn.addEventListener('click',()=>{ profileState.status=btn.dataset.status; profileState.page=1; loadProfiles(); }));
  document.getElementById('profile_q').addEventListener('input',(e)=>{ profileState.q=e.target.value; profileState.page=1; loadProfiles(); });
  document.getElementById('btn-clear-profile').addEventListener('click',()=>{ profileState.q=''; profileState.status='ACTIVE'; profileState.page=1; loadProfiles(); });
  document.getElementById('btn-save-profile').addEventListener('click', async()=>{ const payload = Object.fromEntries(new FormData(profileForm).entries()); payload.paper_width_mm = Number(payload.paper_width_mm || 80); payload.copy_count = Number(payload.copy_count || 1); payload.open_drawer = Number(payload.open_drawer || 0); payload.show_logo = Number(payload.show_logo || 0); payload.show_price = Number(payload.show_price || 0); payload.show_footer = Number(payload.show_footer || 0); payload.is_active = Number(payload.is_active || 0); await postJson('<?= site_url('pos/printers/profiles/save'); ?>', payload); profileModal.hide(); await loadProfiles(); });
  loadProfiles().catch((e)=>alert(e.message));
});
</script>
