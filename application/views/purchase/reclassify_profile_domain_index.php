<?php
$runUrl = site_url('purchase/reclassify-profile-domain/run');
$maintenanceCsrfToken = (string)($purchase_maintenance_csrf_token ?? '');
?>

<div class="mb-2">
  <h4 class="mb-0 fw-bold"><i class="ri-shuffle-line page-title-icon me-1"></i><?php echo html_escape($title ?? 'Reclassify ITEM/MATERIAL by Profile Key'); ?></h4>
  <small class="text-muted">Tool utilitas untuk membersihkan data snapshot lama yang dobel domain `ITEM/MATERIAL` berdasarkan `mst_purchase_catalog.profile_key`.</small>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'reclassify-profile']); ?>

<div id="alert-area"></div>

<div class="card mb-3">
  <div class="card-body">
    <form id="reclassify-form" class="row g-3" autocomplete="off">
      <div class="col-md-4">
        <label class="form-label">Cari</label>
        <input type="text" class="form-control" id="q" placeholder="profile key / nama katalog / brand">
      </div>
      <div class="col-md-3">
        <label class="form-label">Profile Key (Exact, opsional)</label>
        <input type="text" class="form-control" id="profile_key" placeholder="Isi bila ingin 1 profile_key saja">
      </div>
      <div class="col-md-2">
        <label class="form-label">Line Kind</label>
        <select class="form-select" id="line_kind">
          <option value="ALL">ALL</option>
          <option value="MATERIAL">MATERIAL</option>
          <option value="ITEM">ITEM</option>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label">Limit</label>
        <input type="number" class="form-control" id="limit" min="1" max="2000" value="200">
      </div>
      <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="button" class="btn btn-outline-primary btn-run" data-dry-run="1">Dry Run</button>
        <button type="button" class="btn btn-warning btn-run" data-dry-run="0">Apply</button>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3" id="summary-card" style="display:none;">
  <div class="card-body">
    <h6 class="mb-3">Ringkasan</h6>
    <div class="row g-2" id="summary-grid"></div>
  </div>
</div>

<div class="card mb-3" id="table-summary-card" style="display:none;">
  <div class="card-body">
    <h6 class="mb-3">Dampak Per Tabel</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Tabel</th>
            <th class="text-end">Profile</th>
            <th class="text-end">Rows Before</th>
            <th class="text-end">Rows After</th>
            <th class="text-end">Merged</th>
            <th class="text-end">Changed</th>
          </tr>
        </thead>
        <tbody id="table-summary-body"></tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" id="profile-card" style="display:none;">
  <div class="card-body">
    <h6 class="mb-3">Profile Terdampak</h6>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Profile Key</th>
            <th>Line Kind</th>
            <th>Target</th>
            <th>Katalog</th>
            <th>Tabel Terdampak</th>
          </tr>
        </thead>
        <tbody id="profile-body"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  var runUrl = <?php echo json_encode($runUrl); ?>;
  var maintenanceCsrfToken = <?php echo json_encode($maintenanceCsrfToken); ?>;
  var alertArea = document.getElementById('alert-area');
  var summaryCard = document.getElementById('summary-card');
  var summaryGrid = document.getElementById('summary-grid');
  var tableSummaryCard = document.getElementById('table-summary-card');
  var tableSummaryBody = document.getElementById('table-summary-body');
  var profileCard = document.getElementById('profile-card');
  var profileBody = document.getElementById('profile-body');

  function esc(v) {
    var d = document.createElement('div');
    d.textContent = v == null ? '' : String(v);
    return d.innerHTML;
  }
  function fmt(n) {
    var x = Number(n || 0);
    return x.toLocaleString('id-ID');
  }
  function setAlert(type, msg) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + msg
      + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
      + '</div>';
  }

  function payload(dryRun) {
    return {
      q: (document.getElementById('q').value || '').trim(),
      profile_key: (document.getElementById('profile_key').value || '').trim(),
      line_kind: String(document.getElementById('line_kind').value || 'ALL').toUpperCase(),
      limit: Number(document.getElementById('limit').value || 200),
      dry_run: !!dryRun
    };
  }

  function renderSummary(data) {
    var s = data && data.summary ? data.summary : {};
    var cards = [
      ['profiles_scanned', 'Profile Scanned'],
      ['profiles_affected', 'Profile Affected'],
      ['tables_affected', 'Tabel Affected'],
      ['rows_before', 'Rows Before'],
      ['rows_after', 'Rows After'],
      ['rows_merged', 'Rows Merged'],
      ['rows_changed', 'Rows Changed']
    ];
    summaryGrid.innerHTML = cards.map(function (it) {
      return '<div class="col-md-3 col-6"><div class="border rounded p-2 h-100">'
        + '<small class="text-muted d-block">' + esc(it[1]) + '</small>'
        + '<div class="fw-semibold fs-5">' + esc(fmt(s[it[0]] || 0)) + '</div>'
        + '</div></div>';
    }).join('');
    summaryCard.style.display = '';
  }

  function renderTableSummary(data) {
    var rows = Array.isArray(data && data.table_summary ? data.table_summary : []) ? data.table_summary : [];
    if (!rows.length) {
      tableSummaryBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada dampak tabel.</td></tr>';
      tableSummaryCard.style.display = '';
      return;
    }
    tableSummaryBody.innerHTML = rows.map(function (r) {
      return '<tr>'
        + '<td><code>' + esc(r.table || '-') + '</code></td>'
        + '<td class="text-end">' + esc(fmt(r.profiles || 0)) + '</td>'
        + '<td class="text-end">' + esc(fmt(r.rows_before || 0)) + '</td>'
        + '<td class="text-end">' + esc(fmt(r.rows_after || 0)) + '</td>'
        + '<td class="text-end">' + esc(fmt(r.rows_merged || 0)) + '</td>'
        + '<td class="text-end">' + esc(fmt(r.rows_changed || 0)) + '</td>'
        + '</tr>';
    }).join('');
    tableSummaryCard.style.display = '';
  }

  function renderProfiles(data) {
    var rows = Array.isArray(data && data.profiles ? data.profiles : []) ? data.profiles : [];
    if (!rows.length) {
      profileBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada profile terdampak.</td></tr>';
      profileCard.style.display = '';
      return;
    }
    profileBody.innerHTML = rows.map(function (r) {
      var tables = Array.isArray(r.tables) ? r.tables : [];
      var tableText = tables.map(function (t) {
        return String(t.table || '') + ' (' + fmt(t.rows_before || 0) + ' -> ' + fmt(t.rows_after || 0) + ')';
      }).join(', ');
      var target = String(r.target_domain || '-') + (r.target_material_id ? (' | material_id=' + String(r.target_material_id)) : '');
      return '<tr>'
        + '<td><code>' + esc(r.profile_key || '-') + '</code></td>'
        + '<td>' + esc(r.line_kind || '-') + '</td>'
        + '<td>' + esc(target) + '</td>'
        + '<td>' + esc((r.catalog_name || '-') + ((r.brand_name || '') ? (' | ' + r.brand_name) : '')) + '</td>'
        + '<td class="small">' + esc(tableText || '-') + '</td>'
        + '</tr>';
    }).join('');
    profileCard.style.display = '';
  }

  function setButtonsDisabled(flag) {
    document.querySelectorAll('.btn-run').forEach(function (b) {
      b.disabled = !!flag;
    });
  }

  function run(dryRun) {
    var p = payload(dryRun);
    if (p.limit <= 0 || p.limit > 2000) {
      setAlert('warning', 'Limit harus di antara 1 sampai 2000.');
      return;
    }

    setButtonsDisabled(true);
    summaryCard.style.display = 'none';
    tableSummaryCard.style.display = 'none';
    profileCard.style.display = 'none';

    fetch(runUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Purchase-Maintenance-CSRF': maintenanceCsrfToken
      },
      body: JSON.stringify(p)
    })
    .then(function (res) {
      return res.json().then(function (json) {
        return { ok: res.ok, json: json };
      });
    })
    .then(function (res) {
      if (!res.ok || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Request gagal.');
      }
      setAlert('success', esc(res.json.message || 'Sukses.'));
      renderSummary(res.json.data || {});
      renderTableSummary(res.json.data || {});
      renderProfiles(res.json.data || {});
    })
    .catch(function (err) {
      setAlert('danger', esc(err.message || 'Terjadi kesalahan.'));
    })
    .finally(function () {
      setButtonsDisabled(false);
    });
  }

  document.querySelectorAll('.btn-run').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dryRun = String(btn.getAttribute('data-dry-run') || '1') === '1';
      run(dryRun);
    });
  });
})();
</script>
