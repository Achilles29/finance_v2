<?php
$mode      = (string)($mode ?? 'create');
$broadcast = (array)($broadcast ?? []);
$templates = (array)($templates ?? []);
$groups    = (array)($groups ?? []);
$mutationCsrf = (string)($wa_broadcast_mutation_csrf ?? '');
$selectedMembers = (array)($selected_members ?? []);
$delayPattern = (array)($broadcast['delay_pattern'] ?? [1=>2,2=>2,3=>2,4=>2,5=>2,6=>2,7=>2,8=>2,9=>2,10=>2]);
$formAction = $mode === 'edit' && !empty($broadcast['id'])
  ? site_url('wa/broadcast/edit/' . (int)$broadcast['id'])
  : site_url('wa/broadcast/create');
?>

<style>
.wa-broadcast-member-results {
  position: absolute;
  z-index: 50;
  left: 0;
  right: 0;
  max-height: 280px;
  overflow-y: auto;
  border: 1px solid #ead6d0;
  border-radius: .75rem;
  background: #fff;
  box-shadow: 0 .75rem 2rem rgba(80, 40, 40, .12);
}
.wa-broadcast-result-item {
  cursor: pointer;
  padding: .7rem .9rem;
  border-bottom: 1px solid #f3e8e4;
}
.wa-broadcast-result-item:hover { background: #fff5f2; }
.wa-target-chip {
  display: inline-flex;
  gap: .35rem;
  align-items: center;
  border: 1px solid #f0d2ca;
  border-radius: 999px;
  padding: .35rem .65rem;
  background: #fff7f4;
  color: #7f2722;
  font-size: .78rem;
  margin: .2rem;
}
.wa-target-chip button {
  border: 0;
  background: transparent;
  color: #b42318;
  padding: 0;
  line-height: 1;
}
.wa-target-panel {
  border: 1px dashed #ead6d0;
  border-radius: 1rem;
  background: linear-gradient(135deg, #fffaf7, #fff);
  padding: 1rem;
}
</style>

<div class="container-xxl py-3">
  <div class="mb-3">
    <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri ri-arrow-left-line me-1"></i>Kembali
    </a>
  </div>

  <div class="d-flex align-items-center gap-2 mb-3">
    <h4 class="mb-0 fw-bold"><i class="ri ri-broadcast-line me-1"></i>
      <?= $mode === 'create' ? 'Buat Broadcast Baru' : 'Edit Broadcast' ?>
    </h4>
  </div>

  <?php if ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="<?= $formAction ?>" id="waBroadcastForm">
    <input type="hidden" name="wa_broadcast_mutation_csrf" value="<?= html_escape($mutationCsrf) ?>">
    <input type="hidden" name="selected_member_ids" id="selectedMemberIds" value="">
    <div class="row g-3">
      <div class="col-md-8">
        <!-- Identitas -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header"><h5 class="mb-0">Informasi Broadcast</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Nama Broadcast <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required
                value="<?= html_escape($broadcast['name'] ?? '') ?>"
                placeholder="Contoh: Promo Ramadan Juli 2026">
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Tipe Target</label>
                <select name="target_type" id="target_type" class="form-select">
                  <option value="MANUAL" <?= ($broadcast['target_type'] ?? 'MANUAL') === 'MANUAL' ? 'selected' : '' ?>>Manual (input nomor)</option>
                  <option value="SELECTED_MEMBERS" <?= ($broadcast['target_type'] ?? '') === 'SELECTED_MEMBERS' ? 'selected' : '' ?>>Pilih Member</option>
                  <option value="ALL_MEMBERS" <?= ($broadcast['target_type'] ?? '') === 'ALL_MEMBERS' ? 'selected' : '' ?>>Semua Member</option>
                  <option value="MEMBER_ACTIVE" <?= ($broadcast['target_type'] ?? '') === 'MEMBER_ACTIVE' ? 'selected' : '' ?>>Member Aktif</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Jadwal Kirim (opsional)</label>
                <input type="datetime-local" name="scheduled_at" class="form-control"
                  value="<?= html_escape($broadcast['scheduled_at'] ?? '') ?>">
                <div class="form-text">Kosongkan untuk kirim segera setelah mulai.</div>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label fw-semibold">Pola Jeda Pesan Personal</label>
              <div class="form-text mb-2">Jeda setelah penerima ke-1 sampai ke-10. Pola diulang dari urutan pertama hingga antrean selesai.</div>
              <div class="row g-2">
                <?php for ($slot = 1; $slot <= 10; $slot++): ?>
                <div class="col-6 col-md-4 col-xl-2">
                  <label class="form-label small mb-1">Pesan <?= $slot ?></label>
                  <div class="input-group input-group-sm">
                    <input type="number" name="delay_pattern[<?= $slot ?>]" class="form-control" min="1" max="60" required value="<?= (int)($delayPattern[$slot] ?? 2) ?>">
                    <span class="input-group-text">dtk</span>
                  </div>
                </div>
                <?php endfor; ?>
              </div>
            </div>
            <div class="mt-3" id="manual-lines-wrapper">
              <label class="form-label fw-semibold">Daftar Nomor (manual)</label>
              <textarea name="manual_lines" class="form-control font-monospace" rows="8"
                placeholder="Satu nomor per baris. Format: 081234567890 atau 081234567890|Nama Pelanggan"><?= html_escape($broadcast['manual_lines'] ?? '') ?></textarea>
              <div class="form-text">Format: <code>08xxx</code> atau <code>08xxx|Nama</code>. Nomor 08 akan dikonversi ke 62.</div>
              <?php if ($mode === 'edit' && ($broadcast['target_type'] ?? '') !== 'MANUAL'): ?>
              <div class="form-text text-warning">Daftar ini hanya dipakai jika tipe target diubah ke Manual.</div>
              <?php endif; ?>
            </div>
            <div class="mt-3 d-none" id="selected-members-wrapper">
              <div class="wa-target-panel">
                <div class="mb-2 position-relative">
                  <label class="form-label fw-semibold">Cari Member</label>
                  <input type="text" id="memberSearch" class="form-control" autocomplete="off" placeholder="Cari no member / nama / nomor HP...">
                  <div id="memberResults" class="wa-broadcast-member-results d-none"></div>
                  <div class="form-text">Pilih beberapa member. Hanya member aktif dengan nomor HP yang ditampilkan.</div>
                </div>
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold mb-0">Member Dipilih</label>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger" id="clearMembers">Bersihkan</button>
                  </div>
                  <div id="selectedMembers" class="border rounded p-2 min-vh-25 text-muted small bg-white">Belum ada member dipilih.</div>
                </div>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label fw-semibold">Catatan Internal</label>
              <textarea name="notes" class="form-control" rows="2"><?= html_escape($broadcast['notes'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <!-- Pesan -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header"><h5 class="mb-0">Pesan</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Template Pesan</label>
              <select name="template_id" id="template_select" class="form-select">
                <option value="">— Tanpa template —</option>
                <?php foreach ($templates as $tpl): ?>
                <option value="<?= (int)$tpl['id'] ?>"
                  data-body="<?= html_escape($tpl['body']) ?>"
                  <?= (int)($broadcast['template_id'] ?? 0) === (int)$tpl['id'] ? 'selected' : '' ?>>
                  <?= html_escape($tpl['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label fw-semibold">Pesan Custom</label>
              <textarea name="custom_message" id="custom_message" class="form-control font-monospace" rows="10"
                placeholder="Kosongkan jika menggunakan template. Isi jika ingin override."><?= html_escape($broadcast['custom_message'] ?? '') ?></textarea>
              <div class="form-text">Gunakan <code>&#123;&#123;nama&#125;&#125;</code> untuk variabel dinamis.</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Gambar (opsional)</label>
              <input type="file" name="media_image" id="media_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB. Pesan akan menjadi caption gambar. Upload baru akan mengganti gambar lama.</div>
              <div id="media-preview" class="mt-2 <?= empty($broadcast['media_url']) ? 'd-none' : '' ?>">
                <img src="<?= html_escape($broadcast['media_url'] ?? '') ?>" alt="Preview gambar" class="img-fluid rounded border" style="max-height:180px;">
                <?php if (!empty($broadcast['media_name'])): ?>
                <div class="small text-muted mt-1"><?= html_escape($broadcast['media_name']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-preview">
              <i class="ri ri-eye-line me-1"></i>Preview Pesan
            </button>
            <div id="preview-box" class="mt-2 p-2 bg-light rounded small font-monospace d-none" style="white-space:pre-wrap;"></div>
          </div>
        </div>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary" id="btnBroadcastSubmit">
            <i class="ri ri-save-line me-1"></i><?= $mode === 'edit' ? 'Update Broadcast' : 'Simpan Broadcast' ?>
          </button>
          <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
// Auto-fill template ke custom_message
document.getElementById('template_select')?.addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  const body = opt.getAttribute('data-body') || '';
  document.getElementById('custom_message').value = body;
});

(() => {
  const targetType = document.getElementById('target_type');
  const manualWrapper = document.getElementById('manual-lines-wrapper');
  const selectedWrapper = document.getElementById('selected-members-wrapper');
  const memberSearch = document.getElementById('memberSearch');
  const memberResults = document.getElementById('memberResults');
  const selectedMembersEl = document.getElementById('selectedMembers');
  const selectedMemberIds = document.getElementById('selectedMemberIds');
  const clearMembers = document.getElementById('clearMembers');
  const form = document.getElementById('waBroadcastForm');
  const submitBtn = document.getElementById('btnBroadcastSubmit');
  const selected = new Map();
  const initialMembers = <?= json_encode($selectedMembers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  let timer = null;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
  }

  function syncTargetPanel() {
    const type = targetType?.value || 'MANUAL';
    if (manualWrapper) manualWrapper.style.display = type === 'MANUAL' ? '' : 'none';
    if (selectedWrapper) selectedWrapper.classList.toggle('d-none', type !== 'SELECTED_MEMBERS');
  }

  function renderSelected() {
    if (!selectedMemberIds || !selectedMembersEl) return;
    selectedMemberIds.value = Array.from(selected.keys()).join(',');
    if (selected.size === 0) {
      selectedMembersEl.className = 'border rounded p-2 min-vh-25 text-muted small bg-white';
      selectedMembersEl.textContent = 'Belum ada member dipilih.';
      return;
    }
    selectedMembersEl.className = 'border rounded p-2 bg-white';
    selectedMembersEl.innerHTML = Array.from(selected.values()).map(row => `
      <span class="wa-target-chip" data-id="${Number(row.id)}">
        <span>${escapeHtml(row.member_name || '-')} · <span class="font-monospace">${escapeHtml(row.mobile_phone || '')}</span></span>
        <button type="button" title="Hapus" data-remove-member="${Number(row.id)}">×</button>
      </span>
    `).join('');
  }

  function hideResults() {
    memberResults?.classList.add('d-none');
    if (memberResults) memberResults.innerHTML = '';
  }

  function searchMembers(q) {
    if (!memberResults) return;
    if (q.length < 2) {
      hideResults();
      return;
    }
    memberResults.classList.remove('d-none');
    memberResults.innerHTML = '<div class="p-3 text-muted small">Mencari member...</div>';
    fetch(`<?= site_url('wa/api/member-search') ?>?q=${encodeURIComponent(q)}&limit=12`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        const rows = Array.isArray(data.rows) ? data.rows : [];
        if (!rows.length) {
          memberResults.innerHTML = '<div class="p-3 text-muted small">Member tidak ditemukan.</div>';
          return;
        }
        memberResults.innerHTML = rows.map(row => `
          <div class="wa-broadcast-result-item" data-member-id="${Number(row.id)}"
               data-member='${escapeHtml(JSON.stringify(row))}'>
            <div class="fw-semibold">${escapeHtml(row.member_name || '-')}</div>
            <div class="small text-muted">
              ${escapeHtml(row.member_no || '-')} · <span class="font-monospace">${escapeHtml(row.mobile_phone || '-')}</span>
            </div>
          </div>
        `).join('');
      })
      .catch(() => {
        memberResults.innerHTML = '<div class="p-3 text-danger small">Gagal mencari member.</div>';
      });
  }

  targetType?.addEventListener('change', syncTargetPanel);
  if (Array.isArray(initialMembers)) {
    initialMembers.forEach(row => {
      if (row && row.id) selected.set(String(row.id), row);
    });
    renderSelected();
  }
  targetType?.dispatchEvent(new Event('change'));

  memberSearch?.addEventListener('input', function() {
    clearTimeout(timer);
    timer = setTimeout(() => searchMembers(this.value.trim()), 250);
  });

  memberResults?.addEventListener('click', function(e) {
    const item = e.target.closest('[data-member-id]');
    if (!item) return;
    try {
      const row = JSON.parse(item.dataset.member || '{}');
      if (row.id) {
        selected.set(String(row.id), row);
        renderSelected();
        memberSearch.value = '';
        hideResults();
      }
    } catch (err) {
      hideResults();
    }
  });

  selectedMembersEl?.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-remove-member]');
    if (!btn) return;
    selected.delete(String(btn.dataset.removeMember));
    renderSelected();
  });

  clearMembers?.addEventListener('click', function() {
    selected.clear();
    renderSelected();
  });

  document.addEventListener('click', function(e) {
    if (!e.target.closest('#memberSearch') && !e.target.closest('#memberResults')) {
      hideResults();
    }
  });

  form?.addEventListener('submit', function(e) {
    if ((targetType?.value || '') === 'SELECTED_MEMBERS' && selected.size === 0) {
      e.preventDefault();
      alert('Pilih minimal satu member untuk target broadcast.');
      return;
    }
    if (!submitBtn) return;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Menyimpan...';
  });
})();

// Preview
document.getElementById('btn-preview')?.addEventListener('click', function () {
  const msg = document.getElementById('custom_message').value;
  const box = document.getElementById('preview-box');
  if (!msg) { box.classList.add('d-none'); return; }
  fetch('<?= site_url('wa/api/template-preview') ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ body: msg, variables: { nama: 'Pelanggan', judul_promo: '[Judul]', tanggal_promo: '[Tanggal]', deskripsi: '[Deskripsi]' } })
  }).then(r => r.json()).then(d => {
    box.textContent = d.preview || '';
    box.classList.remove('d-none');
  });
});

document.getElementById('media_image')?.addEventListener('change', function () {
  const wrap = document.getElementById('media-preview');
  const img = wrap?.querySelector('img');
  const file = this.files && this.files[0] ? this.files[0] : null;
  if (!wrap || !img || !file) {
    wrap?.classList.add('d-none');
    return;
  }
  img.src = URL.createObjectURL(file);
  wrap.classList.remove('d-none');
});
</script>
