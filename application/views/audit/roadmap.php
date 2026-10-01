<?php
$roadmap = is_array($roadmap ?? null) ? $roadmap : [];
$isAvailable = !empty($roadmap['ok']);
$readiness = is_array($roadmap['readiness'] ?? null) ? $roadmap['readiness'] : [];
$phases = is_array($roadmap['phases'] ?? null) ? $roadmap['phases'] : [];
$findings = is_array($roadmap['findings'] ?? null) ? $roadmap['findings'] : [];
$uiWaves = is_array($roadmap['ui_waves'] ?? null) ? $roadmap['ui_waves'] : [];
$sqlRegister = is_array($roadmap['sql_register'] ?? null) ? $roadmap['sql_register'] : [];
$commercialPhases = is_array($roadmap['commercial_phases'] ?? null) ? $roadmap['commercial_phases'] : [];
$readinessReasons = is_array($readiness['reasons'] ?? null) ? $readiness['reasons'] : [];
$readinessBadgeClass = (($readiness['status'] ?? '') === 'READY') ? 'bg-label-success' : 'bg-label-danger';
$readinessTextClass = (($readiness['status'] ?? '') === 'READY') ? 'text-success' : 'text-danger';
$escape = static function ($value): string {
    return (string)html_escape((string)$value);
};
$badgeClass = static function ($value): string {
    $value = strtoupper((string)$value);
    if (in_array($value, ['CODE_PASS', 'AUTO_PASS', 'STAGING_PASS', 'UAT_PASS', 'PROD_READY', 'REPAIRED_VALIDATED', 'DONE'], true)) {
        return 'bg-label-success';
    }
    if (in_array($value, ['BLOCKED', 'NONE', 'NOT_STARTED', 'UNAVAILABLE'], true)) {
        return 'bg-label-danger';
    }
    if ($value === 'N/A') {
        return 'bg-label-secondary';
    }
    return 'bg-label-warning';
};
?>

<style>
  #audit-roadmap-dashboard .ard-card { border:0; border-radius:1rem; box-shadow:0 .25rem 1rem rgba(58,38,30,.07); }
  #audit-roadmap-dashboard .ard-phase { border-left:.25rem solid var(--bs-warning); height:100%; }
  #audit-roadmap-dashboard .ard-label { font-size:.7rem; letter-spacing:.04em; text-transform:uppercase; }
  #audit-roadmap-dashboard .ard-dimensions { display:flex; flex-wrap:wrap; gap:.4rem; }
  #audit-roadmap-dashboard .ard-dimension { display:inline-flex; align-items:center; gap:.3rem; }
  #audit-roadmap-dashboard .ard-table th { white-space:nowrap; font-size:.75rem; }
  #audit-roadmap-dashboard .ard-table td { min-width:9rem; vertical-align:top; font-size:.79rem; }
  #audit-roadmap-dashboard .ard-table td:first-child { min-width:7rem; }
  #audit-roadmap-dashboard .ard-section-anchor { scroll-margin-top:5rem; }
  #audit-roadmap-dashboard .ard-filter-status { min-height:1.5rem; }
  #audit-roadmap-dashboard .ard-tabs-shell { background:var(--bs-body-bg, #f5f5f9); }
  #audit-roadmap-dashboard .ard-tabs { min-width:max-content; }
  #audit-roadmap-dashboard .ard-tabs-shell { overflow-x:auto; scrollbar-width:thin; }
  #audit-roadmap-dashboard .ard-tabs .nav-link { white-space:nowrap; }
  @media (max-width:767.98px) {
    #audit-roadmap-dashboard .ard-table td { min-width:12rem; }
    #audit-roadmap-dashboard .ard-summary-number { font-size:1.25rem; }
    #audit-roadmap-dashboard .ard-tabs-shell { position:sticky; top:.5rem; z-index:10; margin-right:-.75rem; margin-left:-.75rem; padding:.5rem .75rem; }
  }
</style>

<div id="audit-roadmap-dashboard" data-audit-roadmap-dashboard>
  <div class="fin-page-header mb-4">
    <div>
      <p class="fin-breadcrumb">Internal / Audit</p>
      <h4 class="fin-page-title"><i class="ri ri-road-map-line me-1 text-primary" aria-hidden="true"></i>Roadmap kesiapan Finance</h4>
      <p class="fin-page-subtitle mb-0">Ringkasan baca-saja dari tabel status kanonis audit dan komersialisasi.</p>
    </div>
    <div class="fin-page-actions">
      <span class="badge <?php echo $readinessBadgeClass; ?> fs-6" role="status"><?php echo $escape($readiness['label'] ?? 'Belum siap jual'); ?></span>
    </div>
  </div>

  <?php if (!$isAvailable): ?>
    <div class="alert alert-warning ard-card" role="alert">
      <div class="fw-semibold"><i class="ri ri-alert-line me-1" aria-hidden="true"></i>Data roadmap tidak tersedia</div>
      <div class="small mt-1"><?php echo $escape($roadmap['message'] ?? 'Ringkasan roadmap belum dapat ditampilkan saat ini.'); ?></div>
    </div>
  <?php else: ?>
    <nav class="ard-tabs-shell mb-4" aria-label="Bagian roadmap audit">
      <div class="nav nav-pills ard-tabs flex-nowrap" id="audit-roadmap-tabs" role="tablist">
        <button class="nav-link active" id="audit-summary-tab" data-bs-toggle="tab" data-bs-target="#audit-summary" data-audit-tab-hash="#audit-summary" type="button" role="tab" aria-controls="audit-summary" aria-selected="true">Ringkasan</button>
        <button class="nav-link" id="audit-findings-tab" data-bs-toggle="tab" data-bs-target="#audit-findings" data-audit-tab-hash="#audit-findings" type="button" role="tab" aria-controls="audit-findings" aria-selected="false" tabindex="-1">Temuan</button>
        <button class="nav-link" id="audit-ui-tab" data-bs-toggle="tab" data-bs-target="#audit-ui-waves" data-audit-tab-hash="#audit-ui-waves" type="button" role="tab" aria-controls="audit-ui-waves" aria-selected="false" tabindex="-1">UI 8.3</button>
        <button class="nav-link" id="audit-sql-tab" data-bs-toggle="tab" data-bs-target="#audit-sql" data-audit-tab-hash="#audit-sql" type="button" role="tab" aria-controls="audit-sql" aria-selected="false" tabindex="-1">SQL</button>
        <button class="nav-link" id="audit-commercial-tab" data-bs-toggle="tab" data-bs-target="#audit-commercial" data-audit-tab-hash="#audit-commercial" type="button" role="tab" aria-controls="audit-commercial" aria-selected="false" tabindex="-1">Komersialisasi</button>
      </div>
    </nav>

    <div class="tab-content" id="audit-roadmap-tab-content">
      <div class="tab-pane fade show active" id="audit-summary" role="tabpanel" aria-labelledby="audit-summary-tab" tabindex="0">
    <section class="row g-3 mb-4" aria-label="Ringkasan kesiapan">
      <div class="col-12 col-md-4">
        <div class="card ard-card h-100"><div class="card-body">
          <div class="ard-label text-muted">Status penjualan</div>
          <div class="h5 <?php echo $readinessTextClass; ?> mb-1 ard-summary-number"><?php echo $escape($readiness['label'] ?? 'Belum siap jual'); ?></div>
          <div class="small text-muted"><?php echo (($readiness['status'] ?? '') === 'READY') ? 'Seluruh fase teknis dan komersial berstatus DONE.' : 'Ada fase teknis atau komersial yang belum DONE.'; ?></div>
        </div></div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card ard-card h-100"><div class="card-body">
          <div class="ard-label text-muted">Fase teknis belum selesai</div>
          <div class="h4 mb-1 ard-summary-number"><?php echo $escape($readiness['technical_incomplete_count'] ?? 0); ?></div>
          <div class="small text-muted">dari 6 fase teknis A0–A5</div>
        </div></div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card ard-card h-100"><div class="card-body">
          <div class="ard-label text-muted">Total temuan</div>
          <div class="h4 mb-1 ard-summary-number"><?php echo $escape($readiness['total_findings'] ?? 0); ?></div>
          <div class="small text-muted">Register bukti; bukan kesimpulan status fase</div>
        </div></div>
      </div>
    </section>

    <?php if ($readinessReasons !== []): ?>
      <section class="alert alert-warning ard-card mb-4" aria-labelledby="audit-readiness-reasons-title">
        <h5 id="audit-readiness-reasons-title" class="alert-heading fs-6 mb-2">Alasan gerbang belum siap</h5>
        <ul class="small mb-0 ps-3">
          <?php foreach ($readinessReasons as $reason): ?>
            <li><?php echo $escape($reason); ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="ard-section-anchor mb-4" id="audit-phases" aria-labelledby="audit-phases-title">
      <div class="d-flex justify-content-between align-items-end gap-2 mb-2">
        <div><h5 id="audit-phases-title" class="mb-1">Fase teknis A0–A5</h5><p class="text-muted small mb-0">Tiga dimensi harus dibaca bersama; lulus tooling bukan berarti siap rilis.</p></div>
      </div>
      <div class="row g-3">
        <?php foreach ($phases as $phase): ?>
          <div class="col-12 col-lg-6">
            <article class="card ard-card ard-phase"><div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <h6 class="mb-0"><?php echo $escape($phase['Fase'] ?? '-'); ?></h6>
                <span class="badge <?php echo $badgeClass($phase['Status fase'] ?? ''); ?>"><?php echo $escape($phase['Status fase'] ?? '-'); ?></span>
              </div>
              <div class="ard-dimensions mb-3" aria-label="Dimensi status">
                <span class="ard-dimension"><span class="ard-label text-muted">Implementasi</span><span class="badge <?php echo $badgeClass($phase['Implementasi'] ?? ''); ?>"><?php echo $escape($phase['Implementasi'] ?? '-'); ?></span></span>
                <span class="ard-dimension"><span class="ard-label text-muted">Validasi</span><span class="badge <?php echo $badgeClass($phase['Validasi tertinggi'] ?? ''); ?>"><?php echo $escape($phase['Validasi tertinggi'] ?? '-'); ?></span></span>
                <span class="ard-dimension"><span class="ard-label text-muted">Release/data</span><span class="badge <?php echo $badgeClass($phase['Release/data'] ?? ''); ?>"><?php echo $escape($phase['Release/data'] ?? '-'); ?></span></span>
              </div>
              <p class="small mb-0"><?php echo $escape($phase['Alasan/gerbang berikutnya'] ?? '-'); ?></p>
            </div></article>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

      </div>

      <div class="tab-pane fade" id="audit-findings" role="tabpanel" aria-labelledby="audit-findings-tab" tabindex="0">
    <section class="card ard-card ard-section-anchor mb-4" aria-labelledby="audit-findings-title">
      <div class="card-header border-bottom">
        <h5 id="audit-findings-title" class="mb-1">Register temuan audit</h5>
        <p class="text-muted small mb-0">Cari ID, masalah, acceptance, atau bukti; filter memakai status implementasi.</p>
      </div>
      <div class="card-body border-bottom">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-8">
            <label class="form-label small" for="audit-finding-search">Cari temuan</label>
            <input id="audit-finding-search" class="form-control" type="search" placeholder="Contoh: RBAC, payroll, AUD-A1" autocomplete="off">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small" for="audit-finding-status">Status implementasi</label>
            <select id="audit-finding-status" class="form-select">
              <option value="">Semua status</option>
              <option value="NOT_STARTED">Belum dimulai</option>
              <option value="IN_PROGRESS">Sedang berjalan</option>
              <option value="CODE_PASS">Kode lulus</option>
            </select>
          </div>
        </div>
        <div id="audit-filter-status" class="small text-muted mt-2 ard-filter-status" role="status" aria-live="polite"></div>
      </div>
      <div class="table-responsive" tabindex="0" aria-label="Tabel temuan audit dapat digulir horizontal">
        <table class="table table-hover mb-0 ard-table">
          <thead><tr><th>ID / prioritas</th><th>Masalah dan acceptance</th><th>Tiga dimensi</th><th>Bukti / berikutnya</th></tr></thead>
          <tbody>
          <?php foreach ($findings as $finding):
              $searchText = implode(' ', array_values($finding));
          ?>
            <tr data-audit-finding data-search="<?php echo $escape($searchText); ?>" data-status="<?php echo $escape($finding['Implementasi'] ?? ''); ?>">
              <td><div class="fw-semibold"><?php echo $escape($finding['ID'] ?? '-'); ?></div><div class="small text-muted"><?php echo $escape($finding['Sumber'] ?? '-'); ?> · <?php echo $escape($finding['Prioritas/fase'] ?? '-'); ?></div></td>
              <td><div class="fw-semibold mb-1"><?php echo $escape($finding['Masalah'] ?? '-'); ?></div><div class="small text-muted"><?php echo $escape($finding['Solusi/acceptance'] ?? '-'); ?></div></td>
              <td><div class="ard-dimensions">
                <span class="badge <?php echo $badgeClass($finding['Implementasi'] ?? ''); ?>"><?php echo $escape($finding['Implementasi'] ?? '-'); ?></span>
                <span class="badge <?php echo $badgeClass($finding['Validasi'] ?? ''); ?>"><?php echo $escape($finding['Validasi'] ?? '-'); ?></span>
                <span class="badge <?php echo $badgeClass($finding['Release/data'] ?? ''); ?>"><?php echo $escape($finding['Release/data'] ?? '-'); ?></span>
              </div></td>
              <td><?php echo $escape($finding['Bukti atau langkah berikutnya'] ?? '-'); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div id="audit-findings-empty" class="card-body text-center text-muted d-none" role="status">Tidak ada temuan yang cocok dengan filter.</div>
    </section>

      </div>

      <div class="tab-pane fade" id="audit-ui-waves" role="tabpanel" aria-labelledby="audit-ui-tab" tabindex="0">
    <section class="card ard-card ard-section-anchor mb-4" aria-labelledby="audit-ui-title">
      <div class="card-header"><h5 id="audit-ui-title" class="mb-1">Rollout UI 8.3</h5><p class="small text-muted mb-0">Adopsi primitive dilakukan per gelombang dan tetap memerlukan UAT visual.</p></div>
      <div class="table-responsive" tabindex="0"><table class="table mb-0 ard-table"><thead><tr><th>ID / wave</th><th>Scope</th><th>Implementasi</th><th>Validasi</th><th>Status nyata</th></tr></thead><tbody>
        <?php foreach ($uiWaves as $wave): ?>
          <tr><td><strong><?php echo $escape($wave['ID'] ?? '-'); ?></strong><div class="text-muted small">Wave <?php echo $escape($wave['Gelombang'] ?? '-'); ?></div></td><td><?php echo $escape($wave['Scope/acceptance'] ?? '-'); ?></td><td><span class="badge <?php echo $badgeClass($wave['Implementasi'] ?? ''); ?>"><?php echo $escape($wave['Implementasi'] ?? '-'); ?></span></td><td><span class="badge <?php echo $badgeClass($wave['Validasi'] ?? ''); ?>"><?php echo $escape($wave['Validasi'] ?? '-'); ?></span></td><td><?php echo $escape($wave['Status nyata'] ?? '-'); ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </section>

      </div>

      <div class="tab-pane fade" id="audit-sql" role="tabpanel" aria-labelledby="audit-sql-tab" tabindex="0">
    <section class="card ard-card ard-section-anchor mb-4" aria-labelledby="audit-sql-title">
      <div class="card-header"><h5 id="audit-sql-title" class="mb-1">Register SQL</h5><p class="small text-muted mb-0">Status bukti staging dan tindakan server utama; halaman ini tidak menjalankan query.</p></div>
      <div class="table-responsive" tabindex="0"><table class="table mb-0 ard-table"><thead><tr><th>File / klasifikasi</th><th>Staging</th><th>Bukti</th><th>Server utama</th><th>Berikutnya</th></tr></thead><tbody>
        <?php foreach ($sqlRegister as $sql): ?>
          <tr><td><strong><?php echo $escape($sql['File SQL'] ?? '-'); ?></strong><div class="text-muted small"><?php echo $escape($sql['Klasifikasi'] ?? '-'); ?></div></td><td><span class="badge <?php echo $badgeClass($sql['Status staging'] ?? ''); ?>"><?php echo $escape($sql['Status staging'] ?? '-'); ?></span></td><td><?php echo $escape($sql['Bukti staging'] ?? '-'); ?></td><td><span class="badge <?php echo $badgeClass($sql['Status server utama'] ?? ''); ?>"><?php echo $escape($sql['Status server utama'] ?? '-'); ?></span></td><td><?php echo $escape($sql['Tindakan berikutnya'] ?? '-'); ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </section>

      </div>

      <div class="tab-pane fade" id="audit-commercial" role="tabpanel" aria-labelledby="audit-commercial-tab" tabindex="0">
    <section class="ard-section-anchor mb-4" aria-labelledby="audit-commercial-title">
      <div class="mb-2"><h5 id="audit-commercial-title" class="mb-1">Fase komersialisasi C0–C5</h5><p class="small text-muted mb-0">Berjalan setelah handoff teknis; status berasal dari tabel kanonis roadmap komersialisasi.</p></div>
      <div class="row g-3">
        <?php foreach ($commercialPhases as $phase): ?>
          <div class="col-12 col-lg-6"><article class="card ard-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between gap-2 mb-2"><h6 class="mb-0"><?php echo $escape($phase['Fase'] ?? '-'); ?></h6><span class="badge <?php echo $badgeClass($phase['Status fase'] ?? ''); ?>"><?php echo $escape($phase['Status fase'] ?? '-'); ?></span></div>
            <div class="ard-dimensions mb-3" aria-label="Dimensi status">
              <span class="badge <?php echo $badgeClass($phase['Implementasi'] ?? ''); ?>"><?php echo $escape($phase['Implementasi'] ?? '-'); ?></span>
              <span class="badge <?php echo $badgeClass($phase['Validasi tertinggi'] ?? ''); ?>"><?php echo $escape($phase['Validasi tertinggi'] ?? '-'); ?></span>
              <span class="badge <?php echo $badgeClass($phase['Release/data'] ?? ''); ?>"><?php echo $escape($phase['Release/data'] ?? '-'); ?></span>
            </div>
            <p class="small mb-0"><?php echo $escape($phase['Alasan/gerbang berikutnya'] ?? '-'); ?></p>
          </div></article></div>
        <?php endforeach; ?>
      </div>
    </section>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($isAvailable): ?>
<script>
(function () {
  'use strict';
  var root = document.querySelector('[data-audit-roadmap-dashboard]');
  if (!root) return;
  var tabs = Array.prototype.slice.call(root.querySelectorAll('#audit-roadmap-tabs [role="tab"]'));
  var panels = Array.prototype.slice.call(root.querySelectorAll('#audit-roadmap-tab-content > [role="tabpanel"]'));
  var tabByHash = {
    '#audit-summary': '#audit-summary-tab',
    '#audit-phases': '#audit-summary-tab',
    '#audit-findings': '#audit-findings-tab',
    '#audit-ui-waves': '#audit-ui-tab',
    '#audit-sql': '#audit-sql-tab',
    '#audit-commercial': '#audit-commercial-tab'
  };
  var hashRestoreTab = null;
  var updateHash = function (tab) {
    var hash = tab.getAttribute('data-audit-tab-hash');
    if (!hash || window.location.hash === hash) return;
    if (window.history && window.history.pushState) {
      window.history.pushState(null, '', hash);
      return;
    }
    window.location.hash = hash;
  };
  var activateWithoutBootstrap = function (tab) {
    var targetId = tab.getAttribute('aria-controls');
    tabs.forEach(function (item) {
      var isActive = item === tab;
      item.classList.toggle('active', isActive);
      item.setAttribute('aria-selected', isActive ? 'true' : 'false');
      item.setAttribute('tabindex', isActive ? '0' : '-1');
    });
    panels.forEach(function (panel) {
      var isActive = panel.id === targetId;
      panel.classList.toggle('active', isActive);
      panel.classList.toggle('show', isActive);
    });
  };
  var restoreFromHash = function () {
    var selector = tabByHash[window.location.hash];
    var tab = selector ? root.querySelector(selector) : null;
    if (!tab || tab.classList.contains('active')) return;
    if (window.bootstrap && window.bootstrap.Tab) {
      hashRestoreTab = tab;
      window.bootstrap.Tab.getOrCreateInstance(tab).show();
      return;
    }
    activateWithoutBootstrap(tab);
  };
  tabs.forEach(function (tab) {
    tab.addEventListener('shown.bs.tab', function (event) {
      if (hashRestoreTab === event.target) {
        hashRestoreTab = null;
        return;
      }
      updateHash(event.target);
    });
    tab.addEventListener('click', function (event) {
      if (window.bootstrap && window.bootstrap.Tab) return;
      event.preventDefault();
      activateWithoutBootstrap(tab);
      updateHash(tab);
    });
  });
  window.addEventListener('hashchange', restoreFromHash);
  window.addEventListener('popstate', restoreFromHash);
  if (document.readyState === 'complete') {
    restoreFromHash();
  } else {
    window.addEventListener('load', restoreFromHash, { once: true });
  }

  var search = root.querySelector('#audit-finding-search');
  var status = root.querySelector('#audit-finding-status');
  var output = root.querySelector('#audit-filter-status');
  var empty = root.querySelector('#audit-findings-empty');
  var rows = Array.prototype.slice.call(root.querySelectorAll('[data-audit-finding]'));
  var applyFilter = function () {
    var query = (search.value || '').trim().toLocaleLowerCase('id-ID');
    var selected = status.value || '';
    var visible = 0;
    rows.forEach(function (row) {
      var matchesQuery = query === '' || (row.getAttribute('data-search') || '').toLocaleLowerCase('id-ID').indexOf(query) !== -1;
      var matchesStatus = selected === '' || row.getAttribute('data-status') === selected;
      row.classList.toggle('d-none', !(matchesQuery && matchesStatus));
      if (matchesQuery && matchesStatus) visible++;
    });
    output.textContent = visible + ' dari ' + rows.length + ' temuan ditampilkan.';
    empty.classList.toggle('d-none', visible !== 0);
  };
  search.addEventListener('input', applyFilter);
  status.addEventListener('change', applyFilter);
  applyFilter();
}());
</script>
<?php endif; ?>
