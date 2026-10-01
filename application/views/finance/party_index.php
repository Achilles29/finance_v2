<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$pg = is_array($pg ?? null) ? $pg : ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$editRow = is_array($edit_row ?? null) ? $edit_row : null;
$isEdit = !empty($editRow);
$baseUrl = site_url('finance/relasi');
$saveUrl = site_url('finance/relasi/save');
$buildUrl = static function (array $overrides = []) use ($filters, $pg, $baseUrl) {
    $query = [
        'q' => (string)($filters['q'] ?? ''),
        'status' => (string)($filters['status'] ?? 'ACTIVE'),
        'party_type' => (string)($filters['party_type'] ?? ''),
        'per_page' => (int)($pg['per_page'] ?? 25),
        'page' => (int)($pg['page'] ?? 1),
    ];
    return $baseUrl . '?' . http_build_query(array_merge($query, $overrides));
};
?>

<style>
  .fin-party-shell,
  .fin-party-filter,
  .fin-party-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    box-shadow: 0 16px 34px rgba(85, 55, 35, .06);
  }
  .fin-party-shell {
    background:
      radial-gradient(circle at top left, rgba(214, 139, 88, .10), transparent 32%),
      linear-gradient(180deg, #fffdfb, #fff);
  }
  .fin-party-pill {
    border-radius: 999px;
    border: 1px solid #e9d6c8;
    background: #fff7f1;
    color: #845b50;
    font-weight: 700;
    padding: .35rem .8rem;
    font-size: .78rem;
  }
  .fin-party-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    text-transform: uppercase;
    font-size: .78rem;
    letter-spacing: .04em;
  }
  .fin-party-name {
    font-weight: 700;
    color: #4f1f1f;
  }
  .fin-party-sub {
    color: #8a7a6c;
    font-size: .86rem;
  }
  .fin-search-results {
    position: absolute;
    inset: calc(100% + 4px) 0 auto 0;
    z-index: 1056;
    background: #fff;
    border: 1px solid rgba(143, 53, 58, .16);
    border-radius: 18px;
    box-shadow: 0 16px 40px rgba(80, 47, 26, .14);
    max-height: 240px;
    overflow: auto;
    display: none;
  }
  .fin-search-results.show {
    display: block;
  }
  .fin-search-option {
    padding: .75rem .9rem;
    border-bottom: 1px solid rgba(143, 53, 58, .08);
    cursor: pointer;
  }
  .fin-search-option:last-child {
    border-bottom: 0;
  }
  .fin-search-option:hover {
    background: #fff6ef;
  }
  .fin-selected-box {
    border: 1px dashed rgba(143, 53, 58, .22);
    border-radius: 18px;
    padding: .8rem .9rem;
    background: #fffaf6;
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Pihak Luar untuk Utang & Piutang</h4>
      <p class="fin-page-subtitle mb-0">Simpan daftar orang, vendor, atau relasi luar yang bisa menjadi pemberi utang, penerima piutang, atau ditautkan ke member.</p>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'party']); ?>

  <div class="card fin-party-shell mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <h5 class="mb-1">Master Pihak Luar</h5>
        <div class="text-muted small">Halaman ini jadi sumber pilihan cepat untuk transaksi utang dan piutang.</div>
      </div>
      <button type="button" class="btn btn-primary" id="btn-new-party" data-bs-toggle="modal" data-bs-target="#partyModal">Tambah Pihak</button>
    </div>
  </div>

  <div class="card fin-party-filter mb-3">
    <div class="card-body">
      <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label mb-1">Cari</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama pihak / kode / no HP / nama member">
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Tipe</label>
          <select name="party_type" class="form-select">
            <option value="">Semua tipe</option>
            <?php foreach (['PERSON' => 'Orang', 'BUSINESS' => 'Usaha / Vendor', 'MEMBER' => 'Member', 'OTHER' => 'Lainnya'] as $value => $label): ?>
              <option value="<?php echo $value; ?>" <?php echo (($filters['party_type'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Status</label>
          <select name="status" class="form-select">
            <?php foreach (['ACTIVE' => 'Aktif', 'INACTIVE' => 'Nonaktif', 'ALL' => 'Semua'] as $value => $label): ?>
              <option value="<?php echo $value; ?>" <?php echo (($filters['status'] ?? 'ACTIVE') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label mb-1">Baris</label>
          <select name="per_page" class="form-select">
            <?php foreach ([10, 25, 50, 100] as $opt): ?>
              <option value="<?php echo $opt; ?>" <?php echo ((int)($pg['per_page'] ?? 25) === $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Filter</button>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card fin-party-table">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Pihak</th>
            <th>Tipe</th>
            <th>Kontak</th>
            <th>Relasi Member</th>
            <th class="text-center">Dipakai</th>
            <th class="text-center">Status</th>
            <th class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data pihak luar.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td>
                  <div class="fin-party-name"><?php echo html_escape((string)($row['party_name'] ?? '-')); ?></div>
                  <div class="fin-party-sub"><?php echo html_escape((string)($row['party_code'] ?? '')); ?></div>
                </td>
                <td><span class="fin-party-pill"><?php echo html_escape((string)($row['party_type'] ?? '-')); ?></span></td>
                <td>
                  <div><?php echo html_escape((string)($row['mobile_phone'] ?? '-')); ?></div>
                  <div class="fin-party-sub"><?php echo html_escape((string)($row['email'] ?? '-')); ?></div>
                </td>
                <td>
                  <?php if (!empty($row['linked_member_id'])): ?>
                    <div class="fin-party-name"><?php echo html_escape((string)($row['member_name'] ?? '-')); ?></div>
                    <div class="fin-party-sub">ID member <?php echo (int)$row['linked_member_id']; ?></div>
                  <?php else: ?>
                    <span class="text-muted">Belum ditautkan</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <div class="small">Utang: <?php echo (int)($row['payable_count'] ?? 0); ?></div>
                  <div class="small">Piutang: <?php echo (int)($row['receivable_count'] ?? 0); ?></div>
                </td>
                <td class="text-center">
                  <?php if (!empty($row['is_active'])): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <a href="<?php echo $buildUrl(['edit_id' => (int)$row['id']]); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <form method="post" action="<?php echo site_url('finance/relasi/toggle/' . (int)$row['id']); ?>" class="d-inline">
                    <button type="submit" class="btn btn-sm btn-outline-warning"><?php echo !empty($row['is_active']) ? 'Nonaktifkan' : 'Aktifkan'; ?></button>
                  </form>
                  <form method="post" action="<?php echo site_url('finance/relasi/delete/' . (int)$row['id']); ?>" class="d-inline" onsubmit="return confirm('Hapus pihak ini?');">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pg['total_pages'] ?? 1) > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?>. Total <?php echo (int)$pg['total']; ?> data.</small>
        <div class="btn-group btn-group-sm">
          <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
          <a class="btn btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : $buildUrl(['page' => $prev]); ?>">Prev</a>
          <a class="btn btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : $buildUrl(['page' => $next]); ?>">Next</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="partyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><?php echo $isEdit ? 'Edit Pihak Luar' : 'Tambah Pihak Luar'; ?></h5>
          <div class="small text-muted">Bisa dipakai untuk utang, piutang, dan boleh ditautkan ke member bila orangnya memang pelanggan juga.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="partyForm" class="row g-3">
          <input type="hidden" name="id" value="<?php echo (int)($editRow['id'] ?? 0); ?>">
          <input type="hidden" name="linked_member_id" id="linked_member_id" value="<?php echo (int)($editRow['linked_member_id'] ?? 0); ?>">
          <div class="col-md-4">
            <label class="form-label mb-1">Tipe</label>
            <select class="form-select" name="party_type">
              <?php foreach (['PERSON' => 'Orang', 'BUSINESS' => 'Usaha / Vendor', 'MEMBER' => 'Member', 'OTHER' => 'Lainnya'] as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo (($editRow['party_type'] ?? 'BUSINESS') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1">Nama Pihak</label>
            <input type="text" class="form-control" name="party_name" value="<?php echo html_escape((string)($editRow['party_name'] ?? '')); ?>" required>
          </div>
          <div class="col-12 position-relative">
            <label class="form-label mb-1">Tautkan ke Member (opsional)</label>
            <input type="text" class="form-control" id="member_search_input" placeholder="Cari nama member atau no HP" value="<?php echo html_escape((string)($editRow['member_name'] ?? '')); ?>">
            <div class="fin-search-results" id="member_search_results"></div>
            <div class="fin-selected-box mt-2" id="member_selected_box">
              <?php if (!empty($editRow['linked_member_id'])): ?>
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <div class="fin-party-name"><?php echo html_escape((string)($editRow['member_name'] ?? '-')); ?></div>
                    <div class="fin-party-sub"><?php echo html_escape((string)($editRow['member_mobile_phone'] ?? '-')); ?></div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-member">Lepas</button>
                </div>
              <?php else: ?>
                <span class="text-muted small">Belum ada member yang ditautkan.</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">No HP</label>
            <input type="text" class="form-control" name="mobile_phone" value="<?php echo html_escape((string)($editRow['mobile_phone'] ?? '')); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo html_escape((string)($editRow['email'] ?? '')); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">PIC / Contact Person</label>
            <input type="text" class="form-control" name="contact_person" value="<?php echo html_escape((string)($editRow['contact_person'] ?? '')); ?>">
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Alamat</label>
            <textarea class="form-control" name="address" rows="2"><?php echo html_escape((string)($editRow['address'] ?? '')); ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea class="form-control" name="notes" rows="2"><?php echo html_escape((string)($editRow['notes'] ?? '')); ?></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" id="party_is_active" value="1" <?php echo !isset($editRow['is_active']) || !empty($editRow['is_active']) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="party_is_active">Aktif dan bisa dipilih di transaksi</label>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-party">Simpan Pihak</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('partyModal');
  const modal = (window.bootstrap && modalEl) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('partyForm');
  const saveButton = document.getElementById('btn-save-party');
  const memberInput = document.getElementById('member_search_input');
  const memberResults = document.getElementById('member_search_results');
  const memberIdInput = document.getElementById('linked_member_id');
  const memberSelectedBox = document.getElementById('member_selected_box');
  const memberSearchUrl = <?php echo json_encode(site_url('finance/member-search')); ?>;
  const saveUrl = <?php echo json_encode($saveUrl); ?>;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }

  function renderMemberSelected(row) {
    if (!row || !row.id) {
      memberSelectedBox.innerHTML = '<span class="text-muted small">Belum ada member yang ditautkan.</span>';
      memberIdInput.value = '';
      return;
    }
    memberIdInput.value = String(row.id || '');
    memberSelectedBox.innerHTML = `
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
          <div class="fin-party-name">${escapeHtml(row.member_name || '-')}</div>
          <div class="fin-party-sub">${escapeHtml(row.mobile_phone || '-')}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear-member">Lepas</button>
      </div>
    `;
    const clearBtn = document.getElementById('btn-clear-member');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        renderMemberSelected(null);
        memberInput.value = '';
      });
    }
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, options || {});
    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Request gagal.');
    }
    return json;
  }

  let memberTimer = null;
  memberInput.addEventListener('input', function () {
    const q = memberInput.value.trim();
    clearTimeout(memberTimer);
    if (q.length < 2) {
      memberResults.classList.remove('show');
      memberResults.innerHTML = '';
      return;
    }
    memberTimer = setTimeout(async function () {
      try {
        const json = await fetchJson(memberSearchUrl + '?q=' + encodeURIComponent(q), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const rows = Array.isArray(json.rows) ? json.rows : [];
        if (!rows.length) {
          memberResults.innerHTML = '<div class="fin-search-option text-muted">Member tidak ditemukan.</div>';
        } else {
          memberResults.innerHTML = rows.map((row) => `
            <div class="fin-search-option" data-id="${Number(row.id || 0)}" data-name="${escapeHtml(row.member_name || '')}" data-phone="${escapeHtml(row.mobile_phone || '')}">
              <div class="fin-party-name">${escapeHtml(row.member_name || '-')}</div>
              <div class="fin-party-sub">${escapeHtml(row.mobile_phone || '-')}</div>
            </div>
          `).join('');
        }
        memberResults.classList.add('show');
      } catch (err) {
        memberResults.innerHTML = '<div class="fin-search-option text-danger">Gagal mencari member.</div>';
        memberResults.classList.add('show');
      }
    }, 250);
  });

  memberResults.addEventListener('click', function (event) {
    const option = event.target.closest('.fin-search-option[data-id]');
    if (!option) return;
    renderMemberSelected({
      id: option.dataset.id,
      member_name: option.dataset.name,
      mobile_phone: option.dataset.phone
    });
    if (!form.elements.party_name.value.trim()) {
      form.elements.party_name.value = option.dataset.name || '';
    }
    if (!form.elements.mobile_phone.value.trim()) {
      form.elements.mobile_phone.value = option.dataset.phone || '';
    }
    memberResults.classList.remove('show');
    memberResults.innerHTML = '';
    memberInput.value = option.dataset.name || '';
  });

  document.addEventListener('click', function (event) {
    if (!memberResults.contains(event.target) && event.target !== memberInput) {
      memberResults.classList.remove('show');
    }
  });

  saveButton.addEventListener('click', async function () {
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.is_active = document.getElementById('party_is_active').checked ? 1 : 0;
    saveButton.disabled = true;
    try {
      const json = await fetchJson(saveUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify(payload)
      });
      alert(json.message || 'Data pihak berhasil disimpan.');
      window.location.href = <?php echo json_encode($baseUrl); ?>;
    } catch (err) {
      alert(err.message || 'Gagal menyimpan data pihak.');
    } finally {
      saveButton.disabled = false;
    }
  });

  <?php if ($isEdit): ?>
    if (modal) {
      modal.show();
    }
    renderMemberSelected({
      id: <?php echo (int)($editRow['linked_member_id'] ?? 0); ?>,
      member_name: <?php echo json_encode((string)($editRow['member_name'] ?? '')); ?>,
      mobile_phone: <?php echo json_encode((string)($editRow['member_mobile_phone'] ?? '')); ?>
    });
  <?php endif; ?>
});
</script>
