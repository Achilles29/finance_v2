<?php
$month = $month ?? date('Y-m');
$tab = $tab ?? 'overview';
$summary = $summary ?? [];
$configRows = $config_rows ?? [];
$configTableRows = $config_table_rows ?? [];
$outletRows = $outlet_rows ?? [];
$divisionRows = $division_rows ?? [];
$positionRows = $position_rows ?? [];
$employeeRows = $employee_rows ?? [];
$shiftRows = $shift_rows ?? [];
$bonusRuleOptionRows = $bonus_rule_option_rows ?? [];
$targetPlanRows = $target_plan_rows ?? [];
$monthlyTargetPlanRows = $monthly_target_plan_rows ?? [];
$ruleRows = $rule_rows ?? [];
$weightRows = $weight_rows ?? [];
$employeeDailyRows = $employee_daily_rows ?? [];
$penaltyTypeRows = $penalty_type_rows ?? [];
$penaltyEventRows = $penalty_event_rows ?? [];
$penaltyDetailRows = $penalty_detail_rows ?? [];
$penaltyEventDetailMap = $penalty_event_detail_map ?? [];
$penaltyView = $penalty_view ?? 'master';
$poolRows = $pool_rows ?? [];
$pendingPeerRows = $pending_peer_rows ?? [];
$serviceMetricRows = $service_metric_rows ?? [];
$monthlySummaryRows = $monthly_summary_rows ?? [];
$poolFilters = $pool_filters ?? ['q' => ''];
$configFilters = $config_filters ?? ['q' => ''];
$ruleFilters = $rule_filters ?? ['q' => ''];
$weightFilters = $weight_filters ?? ['q' => ''];
$employeeDailyFilters = $employee_daily_filters ?? ['q' => '', 'date_start' => '', 'date_end' => ''];
$penaltyTypeFilters = $penalty_type_filters ?? ['q' => ''];
$penaltyEventFilters = $penalty_event_filters ?? ['q' => '', 'date_start' => '', 'date_end' => ''];
$peerFilters = $peer_filters ?? ['q' => ''];
$serviceFilters = $service_filters ?? ['q' => ''];
$monthlyFilters = $monthly_filters ?? ['q' => ''];
$poolPg = $pool_pg ?? ['total' => count($poolRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$configPg = $config_pg ?? ['total' => count($configTableRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$rulePg = $rule_pg ?? ['total' => count($ruleRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$weightPg = $weight_pg ?? ['total' => count($weightRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$employeeDailyPg = $employee_daily_pg ?? ['total' => count($employeeDailyRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$penaltyTypePg = $penalty_type_pg ?? ['total' => count($penaltyTypeRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$penaltyEventPg = $penalty_event_pg ?? ['total' => count($penaltyEventRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$peerPg = $peer_pg ?? ['total' => count($pendingPeerRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$servicePg = $service_pg ?? ['total' => count($serviceMetricRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$monthlyPg = $monthly_pg ?? ['total' => count($monthlySummaryRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$penaltyCategoryLabels = [
    'ATTENDANCE' => 'Absensi',
    'DISCIPLINE' => 'Disiplin',
    'PERFORMANCE' => 'Kinerja',
    'SERVICE' => 'Layanan',
    'PROPERTY' => 'Aset / Kerusakan',
    'SOCIAL_MEDIA' => 'Sosial Media',
    'HYGIENE' => 'Kebersihan',
    'OTHER' => 'Lainnya',
    'PERSONAL' => 'Personal',
    'TEAM' => 'Tim',
];
$penaltyScopeLabels = [
    'PERSONAL' => 'Personal',
    'TEAM' => 'Tim',
    'BOTH' => 'Personal & Tim',
];
$penaltyBehaviorLabels = [
    'AUTO' => 'Otomatis',
    'MANUAL' => 'Manual',
    'SEMI_MANUAL' => 'Semi Manual',
];
$penaltyDeductionLabels = [
    'FIXED_POINT' => 'Potong poin tetap',
    'FIXED_AMOUNT' => 'Potong nominal tetap',
    'VARIABLE' => 'Variabel / kasus per kasus',
];
$verificationCycleLabels = [
    'PER_EVENT' => 'Per kejadian',
    'DAILY' => 'Harian',
    'MONTHLY' => 'Bulanan',
    'UNTIL_CHANGED' => 'Berlaku sampai ada perubahan',
];
$autoSourceLabels = [
    '' => 'Tidak otomatis',
    'ATTENDANCE' => 'Absensi',
    'SERVICE' => 'Layanan',
    'TARGET' => 'Target',
    'PEER' => 'Penilaian 360',
    'SOCIAL_MEDIA' => 'Verifikasi sosial media',
    'AUDIT' => 'Audit',
    'CHECKLIST' => 'Checklist',
    'OTHER' => 'Lainnya',
];
$buildPenaltyAutoRule = static function (array $row): array {
    $autoSource = strtoupper((string)($row['auto_source'] ?? ''));
    if ($autoSource === 'SERVICE') {
        $targetMinute = (float)($row['service_target_minute'] ?? 15);
        $stepMinute = (float)($row['service_step_minute'] ?? 5);
        if ($targetMinute <= 0) {
            $targetMinute = 15;
        }
        if ($stepMinute <= 0) {
            $stepMinute = 5;
        }
        return [
            'summary' => 'Batas ' . number_format($targetMinute, 0, ',', '.') . ' menit',
            'detail' => '+1 poin tiap ' . number_format($stepMinute, 0, ',', '.') . ' menit lebih lambat',
        ];
    }
    if ($autoSource === 'PEER') {
        $star4 = (float)($row['peer_star_4_points'] ?? 1);
        $star3 = (float)($row['peer_star_3_points'] ?? 2);
        $star2 = (float)($row['peer_star_2_points'] ?? 3);
        $star1 = (float)($row['peer_star_1_points'] ?? 4);
        return [
            'summary' => '4★=-' . number_format($star4, 0, ',', '.') . ' | 3★=-' . number_format($star3, 0, ',', '.'),
            'detail' => '2★=-' . number_format($star2, 0, ',', '.') . ' | 1★=-' . number_format($star1, 0, ',', '.'),
        ];
    }
    if ($autoSource === 'ATTENDANCE') {
        $trigger = trim((string)($row['attendance_trigger'] ?? ''));
        return [
            'summary' => $trigger !== '' ? 'Trigger ' . $trigger : 'Sinkron dari absensi',
            'detail' => 'Mengikuti status presensi yang cocok',
        ];
    }
    if ($autoSource === 'SOCIAL_MEDIA') {
        return [
            'summary' => 'Perlu verifikasi admin',
            'detail' => 'Aktif sesuai siklus verifikasi yang dipilih',
        ];
    }
    return [
        'summary' => 'Tanpa parameter tambahan',
        'detail' => 'Pengurangan mengikuti poin/nominal default',
    ];
};
$ruleTargetGateLabels = [
    'WEIGHTED_SCORE' => 'Bertingkat',
    'ALL_REQUIRED' => 'Semua target wajib lolos',
    'NONE' => 'Tanpa gerbang target',
];
$phBonusModeLabels = [
    'EXCLUDE' => 'Tidak ikut bonus',
    'REDUCE' => 'Ikut tapi dipotong',
    'ALLOW' => 'Tetap ikut penuh',
];
$weightScopeOptions = [
    'DIVISION' => array_map(static function ($row) {
        return [
            'id' => (int)($row['value'] ?? $row['id'] ?? 0),
            'label' => (string)($row['label'] ?? $row['name'] ?? $row['division_name'] ?? '-'),
        ];
    }, $divisionRows),
    'POSITION' => array_map(static function ($row) {
        return [
            'id' => (int)($row['value'] ?? $row['id'] ?? 0),
            'label' => (string)($row['label'] ?? $row['name'] ?? $row['position_name'] ?? '-'),
        ];
    }, $positionRows),
    'EMPLOYEE' => array_map(static function ($row) {
        return [
            'id' => (int)($row['value'] ?? $row['id'] ?? 0),
            'label' => (string)($row['label'] ?? $row['employee_name'] ?? '-'),
        ];
    }, $employeeRows),
    'SHIFT' => array_map(static function ($row) {
        $label = trim((string)($row['label'] ?? ''));
        if ($label === '') {
            $label = trim((string)($row['shift_code'] ?? '') . ' - ' . (string)($row['shift_name'] ?? ''));
        }
        return [
            'id' => (int)($row['id'] ?? $row['value'] ?? 0),
            'label' => $label !== '' ? $label : '-',
        ];
    }, $shiftRows),
];
$weightScopeLabels = [
    'DIVISION' => 'Divisi',
    'POSITION' => 'Jabatan',
    'EMPLOYEE' => 'Pegawai',
    'SHIFT' => 'Shift',
];
$hasPenaltyBehaviorMode = false;
foreach ($penaltyTypeRows as $penaltyTypeRow) {
    if (array_key_exists('behavior_mode', $penaltyTypeRow)) {
        $hasPenaltyBehaviorMode = true;
        break;
    }
}
$isPenaltySelectableForEvent = static function (array $row) use ($hasPenaltyBehaviorMode): bool {
    if (!$hasPenaltyBehaviorMode) {
        return true;
    }
    return strtoupper((string)($row['behavior_mode'] ?? 'MANUAL')) !== 'AUTO';
};
$buildUrl = static function (array $overrides = []) use ($month, $tab) {
    return site_url('payroll/bonus') . '?' . http_build_query(array_merge([
        'month' => $month,
        'tab' => $tab,
    ], $overrides));
};
$buildTableUrl = static function (array $overrides = []) use ($month, $tab, $poolFilters, $configFilters, $ruleFilters, $weightFilters, $penaltyTypeFilters, $penaltyEventFilters, $penaltyView, $peerFilters, $serviceFilters, $monthlyFilters, $poolPg, $configPg, $rulePg, $weightPg, $penaltyTypePg, $penaltyEventPg, $peerPg, $servicePg, $monthlyPg) {
    $base = [
        'month' => $month,
        'tab' => $tab,
        'pool_q' => $poolFilters['q'] ?? '',
        'pool_page' => $poolPg['page'] ?? 1,
        'pool_per_page' => $poolPg['per_page'] ?? 25,
        'config_q' => $configFilters['q'] ?? '',
        'config_page' => $configPg['page'] ?? 1,
        'config_per_page' => $configPg['per_page'] ?? 25,
        'rule_q' => $ruleFilters['q'] ?? '',
        'rule_page' => $rulePg['page'] ?? 1,
        'rule_per_page' => $rulePg['per_page'] ?? 25,
        'weight_q' => $weightFilters['q'] ?? '',
        'weight_status' => $weightFilters['status'] ?? 'ACTIVE',
        'weight_page' => $weightPg['page'] ?? 1,
        'weight_per_page' => $weightPg['per_page'] ?? 25,
        'employee_daily_q' => $employeeDailyFilters['q'] ?? '',
        'employee_daily_date_start' => $employeeDailyFilters['date_start'] ?? '',
        'employee_daily_date_end' => $employeeDailyFilters['date_end'] ?? '',
        'employee_daily_page' => $employeeDailyPg['page'] ?? 1,
        'employee_daily_per_page' => $employeeDailyPg['per_page'] ?? 25,
        'penalty_type_q' => $penaltyTypeFilters['q'] ?? '',
        'penalty_type_page' => $penaltyTypePg['page'] ?? 1,
        'penalty_type_per_page' => $penaltyTypePg['per_page'] ?? 25,
        'penalty_event_q' => $penaltyEventFilters['q'] ?? '',
        'penalty_event_date_start' => $penaltyEventFilters['date_start'] ?? '',
        'penalty_event_date_end' => $penaltyEventFilters['date_end'] ?? '',
        'penalty_event_page' => $penaltyEventPg['page'] ?? 1,
        'penalty_event_per_page' => $penaltyEventPg['per_page'] ?? 25,
        'penalty_view' => $penaltyView,
        'peer_q' => $peerFilters['q'] ?? '',
        'peer_page' => $peerPg['page'] ?? 1,
        'peer_per_page' => $peerPg['per_page'] ?? 25,
        'service_q' => $serviceFilters['q'] ?? '',
        'service_page' => $servicePg['page'] ?? 1,
        'service_per_page' => $servicePg['per_page'] ?? 25,
        'monthly_q' => $monthlyFilters['q'] ?? '',
        'monthly_page' => $monthlyPg['page'] ?? 1,
        'monthly_per_page' => $monthlyPg['per_page'] ?? 25,
    ];
    return site_url('payroll/bonus') . '?' . http_build_query(array_merge($base, $overrides));
};
$defaultBonusDate = date('Y-m-d', strtotime($month . '-01'));
$activeDailyTargetRows = array_values(array_filter($targetPlanRows, static function ($row) use ($month) {
    return strtoupper((string)($row['status'] ?? '')) === 'ACTIVE'
        && strtoupper((string)($row['target_scope'] ?? '')) === 'DAILY'
        && strpos((string)($row['date_start'] ?? ''), $month) === 0;
}));
$renderPager = static function (array $pg, callable $urlBuilder, string $pageParam, string $perPageParam) {
    if (($pg['total'] ?? 0) <= 0) {
        return;
    }
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
      <div class="small text-muted">
        Menampilkan
        <strong><?php echo number_format((int)($pg['offset'] ?? 0) + 1); ?></strong>
        -
        <strong><?php echo number_format(min((int)($pg['offset'] ?? 0) + (int)($pg['per_page'] ?? 25), (int)($pg['total'] ?? 0))); ?></strong>
        dari
        <strong><?php echo number_format((int)($pg['total'] ?? 0)); ?></strong>
        baris
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="d-flex align-items-center gap-2 mb-0">
          <?php parse_str(parse_url($urlBuilder([]), PHP_URL_QUERY) ?? '', $currentQuery); ?>
          <?php foreach ($currentQuery as $queryKey => $queryValue): ?>
            <?php if ($queryKey === $perPageParam) { continue; } ?>
            <input type="hidden" name="<?php echo html_escape($queryKey); ?>" value="<?php echo html_escape((string)$queryValue); ?>">
          <?php endforeach; ?>
          <label class="small text-muted mb-0">Baris</label>
          <select name="<?php echo html_escape($perPageParam); ?>" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ([10, 25, 50, 100] as $size): ?>
              <option value="<?php echo $size; ?>" <?php echo (int)($pg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <div class="btn-group btn-group-sm" role="group">
          <a class="btn btn-outline-secondary <?php echo (int)($pg['page'] ?? 1) <= 1 ? 'disabled' : ''; ?>" href="<?php echo (int)($pg['page'] ?? 1) <= 1 ? '#' : $urlBuilder([$pageParam => (int)$pg['page'] - 1]); ?>">Sebelumnya</a>
          <button type="button" class="btn btn-outline-secondary disabled">Hal. <?php echo number_format((int)($pg['page'] ?? 1)); ?> / <?php echo number_format((int)($pg['total_pages'] ?? 1)); ?></button>
          <a class="btn btn-outline-secondary <?php echo (int)($pg['page'] ?? 1) >= (int)($pg['total_pages'] ?? 1) ? 'disabled' : ''; ?>" href="<?php echo (int)($pg['page'] ?? 1) >= (int)($pg['total_pages'] ?? 1) ? '#' : $urlBuilder([$pageParam => (int)$pg['page'] + 1]); ?>">Berikutnya</a>
        </div>
      </div>
    </div>
    <?php
};
?>

<style>
  .bonus-hero { background: radial-gradient(circle at top left, rgba(177,18,38,.14), transparent 42%), linear-gradient(135deg, #fff8f5 0%, #ffffff 62%, #fff3ef 100%); border: 1px solid rgba(122,24,36,.08); border-radius: 28px; box-shadow: 0 20px 48px rgba(122,24,36,.08); }
  .bonus-card { border: 1px solid rgba(122,24,36,.08); border-radius: 22px; box-shadow: 0 16px 34px rgba(89,57,41,.06); }
  .bonus-kpi-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:1rem; }
  .bonus-kpi { padding:1rem 1.1rem; border-radius:20px; border:1px solid rgba(122,24,36,.08); background:linear-gradient(180deg,#fff,#fff7f4); }
  .bonus-kpi .label { font-size:.78rem; text-transform:uppercase; color:#8d7368; letter-spacing:.04em; }
  .bonus-kpi .value { font-size:1.25rem; font-weight:800; color:#5f1720; }
  .bonus-kpi .sub { font-size:.85rem; color:#7e6a60; }
  .bonus-table thead th { background: linear-gradient(135deg, #b11226, #8f1021); color:#fff; border:0; text-transform:uppercase; letter-spacing:.04em; font-size:.79rem; white-space: nowrap; }
  .bonus-table-wrap { max-height: 62vh; overflow: auto; border-radius: 18px; border: 1px solid rgba(122,24,36,.08); }
  .bonus-table-wrap table { margin-bottom: 0; }
  .bonus-table-wrap thead th { position: sticky; top: 0; z-index: 2; }
  .bonus-table-compact thead th,
  .bonus-table-compact tbody td { padding: .6rem .72rem; }
  .bonus-table-compact .btn.btn-sm { padding: .32rem .58rem; }
  .bonus-table-compact .bonus-soft-badge { padding: .26rem .56rem; font-size: .72rem; }
  .bonus-weight-table { table-layout: fixed; }
  .bonus-weight-table th.col-frequency { width: 110px; }
  .bonus-weight-table th.col-scope { width: 118px; }
  .bonus-weight-table th.col-target { width: 220px; }
  .bonus-weight-table th.col-weight { width: 110px; }
  .bonus-weight-table th.col-status { width: 92px; }
  .bonus-weight-table th.col-actions { width: 188px; }
  .bonus-weight-table td { font-size: .93rem; }
  .bonus-weight-target { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .bonus-weight-table .bonus-table-actions { flex-wrap: nowrap; white-space: nowrap; }
  .bonus-penalty-master-table { min-width: 1180px; }
  .bonus-penalty-master-table .penalty-name-cell { min-width: 240px; }
  .bonus-penalty-master-table .penalty-auto-cell { min-width: 240px; }
  .bonus-penalty-parameter-box { border: 1px dashed rgba(122,24,36,.16); border-radius: 18px; padding: .9rem 1rem; background: linear-gradient(180deg, #fff, #fff8f5); }
  .bonus-penalty-parameter-box .label { font-size: .76rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #8d7368; margin-bottom: .45rem; display: block; }
  .bonus-penalty-parameter-box p { margin-bottom: 0; color: #7e6a60; font-size: .88rem; }
  .bonus-filter-bar { border: 1px solid rgba(122,24,36,.08); border-radius: 18px; background: linear-gradient(180deg, #fff, #fff8f5); padding: 1rem; }
  .bonus-soft-badge { display:inline-flex; align-items:center; padding:.34rem .68rem; border-radius:999px; font-size:.75rem; font-weight:700; }
  .bonus-soft-badge.ok { background:#e8f8ef; color:#157347; }
  .bonus-soft-badge.warn { background:#fff4df; color:#b26b00; }
  .bonus-soft-badge.info { background:#eef3ff; color:#335ec9; }
  .bonus-soft-badge.muted { background:#f3f4f6; color:#6b7280; }
  .bonus-guide-list li { margin-bottom:.55rem; }
  .bonus-section-nav { display:flex; flex-wrap:wrap; gap:.65rem; margin-bottom:1rem; }
  .bonus-section-nav .nav-link { border-radius:999px; border:1px solid rgba(122,24,36,.12); color:#7a1824; font-weight:700; background:#fff; }
  .bonus-section-nav .nav-link.active { background:linear-gradient(135deg,#b11226,#8f1021); border-color:transparent; color:#fff; box-shadow:0 12px 24px rgba(122,24,36,.12); }
  .bonus-toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
  .bonus-toolbar .copy { color:#7e6a60; max-width:780px; }
  .bonus-action-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; }
  .bonus-action-card { border:1px solid rgba(122,24,36,.08); border-radius:20px; padding:1rem 1.1rem; background:linear-gradient(180deg,#fff,#fff8f5); }
  .bonus-action-card p { color:#7e6a60; font-size:.9rem; margin-bottom:1rem; }
  .bonus-modal .modal-dialog { max-width:min(980px, calc(100vw - 2rem)); }
  .bonus-modal .modal-content { border:none; border-radius:26px; box-shadow:0 24px 60px rgba(89,57,41,.18); }
  .bonus-modal .modal-header { border-bottom:1px solid rgba(122,24,36,.08); padding:1.25rem 1.4rem 1rem; }
  .bonus-modal .modal-body { padding:1.25rem 1.4rem; }
  .bonus-modal .modal-footer { border-top:1px solid rgba(122,24,36,.08); padding:1rem 1.4rem 1.25rem; }
  .bonus-form-scroll { max-height:calc(100vh - 260px); overflow:auto; padding-right:.25rem; }
  .bonus-detail-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:1rem; }
  .bonus-penalty-event-layout { display:block; width:100%; }
  .bonus-penalty-summary-box { border:1px solid rgba(122,24,36,.08); border-radius:20px; padding:.85rem .95rem; background:linear-gradient(180deg,#fff,#fff8f5); }
  .bonus-penalty-event-headline { border:1px solid rgba(122,24,36,.08); border-radius:16px; padding:.75rem .9rem; background:#fff; margin-bottom:.7rem; }
  .bonus-penalty-event-summary-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:.7rem; align-items:stretch; }
  .bonus-penalty-event-summary-item { border:1px solid rgba(122,24,36,.08); border-radius:14px; padding:.6rem .75rem; background:#fffaf8; min-height:100%; }
  .bonus-penalty-event-note { margin-top:.65rem; color:#7e6a60; font-size:.86rem; }
  .bonus-penalty-event-table-wrap { max-height:50vh; overflow:auto; border:1px solid rgba(122,24,36,.08); border-radius:18px; }
  .bonus-penalty-event-table-wrap table { margin-bottom:0; }
  .bonus-penalty-event-table-wrap thead th { position:sticky; top:0; z-index:2; }
  .bonus-detail-item { border:1px solid rgba(122,24,36,.08); border-radius:18px; padding:.9rem 1rem; background:#fffaf8; }
  .bonus-detail-item .label { display:block; font-size:.76rem; text-transform:uppercase; color:#8d7368; letter-spacing:.04em; margin-bottom:.35rem; }
  .bonus-detail-item .value { color:#5f1720; font-weight:700; }
  .bonus-table-actions { display:flex; flex-wrap:wrap; gap:.45rem; }
  .bonus-table-actions form { margin: 0; }
  .bonus-empty-note { border:1px dashed rgba(122,24,36,.18); border-radius:18px; padding:1rem 1.1rem; background:#fffaf8; color:#7e6a60; }
  .bonus-subcopy { color:#8d7368; font-size:.88rem; }
  @media (max-width: 991.98px) { .bonus-kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 767.98px) {
    .bonus-action-grid,
    .bonus-detail-grid { grid-template-columns:1fr; }
  }
  @media (max-width: 575.98px) { .bonus-kpi-grid { grid-template-columns: 1fr; } }
    @media (max-width: 991.98px) { .bonus-penalty-event-summary-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
    @media (max-width: 575.98px) { .bonus-penalty-event-summary-grid { grid-template-columns:1fr; } }
</style>

<div class="bonus-hero p-4 p-lg-5 mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div style="max-width:760px;">
      <div class="text-uppercase small fw-semibold text-muted mb-2">Payroll Workspace</div>
      <h3 class="mb-2">Bonus Pegawai yang membaca target usaha, performa shift, absensi, dan budaya tim.</h3>
      <p class="mb-0 text-muted">Arah barunya sederhana: target usaha disusun di Keuangan, lalu halaman bonus ini dipakai untuk membagi pool ke pegawai secara adil. Jadi target tidak dobel, bobot pegawai lebih konsisten, dan estimasi bonus bisa dipantau tanpa langsung menggerakkan kas.</p>
    </div>
    <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="d-flex gap-2 align-items-end flex-wrap">
      <input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
      <div>
        <label class="form-label small text-muted mb-1">Bulan kerja</label>
        <input type="month" name="month" class="form-control" value="<?php echo html_escape($month); ?>">
      </div>
      <div>
        <button type="submit" class="btn btn-primary">Baca Data</button>
      </div>
    </form>
  </div>
</div>

<?php
$workspace_tabs = [
  ['label' => 'Ringkasan', 'url' => $buildUrl(['tab' => 'overview']), 'active' => $tab === 'overview'],
  ['label' => 'Kebijakan Bonus', 'url' => $buildUrl(['tab' => 'rules']), 'active' => $tab === 'rules'],
  ['label' => 'Bobot Global', 'url' => $buildUrl(['tab' => 'weights']), 'active' => $tab === 'weights'],
  ['label' => 'Bonus Harian Pegawai', 'url' => $buildUrl(['tab' => 'employee_daily']), 'active' => $tab === 'employee_daily'],
  ['label' => 'Penalti', 'url' => $buildUrl(['tab' => 'penalties']), 'active' => $tab === 'penalties'],
  ['label' => 'Penilaian 360', 'url' => $buildUrl(['tab' => 'peer']), 'active' => $tab === 'peer'],
  ['label' => 'Metrik Layanan', 'url' => $buildUrl(['tab' => 'service']), 'active' => $tab === 'service'],
  ['label' => 'Rekap Bulanan', 'url' => $buildUrl(['tab' => 'monthly']), 'active' => $tab === 'monthly'],
  ['label' => 'Panduan', 'url' => $buildUrl(['tab' => 'guide']), 'active' => $tab === 'guide'],
];
$workspace_tab_label = 'Bonus';
$workspace_tab_aria_label = 'Navigasi workspace bonus pegawai';
$this->load->view('layout/_workspace_tabs', get_defined_vars());
?>

<?php if ($tab === 'overview'): ?>
  <div class="bonus-kpi-grid mb-4">
    <div class="bonus-kpi"><div class="label">Konfigurasi Bonus</div><div class="value"><?php echo number_format((int)($summary['config_count'] ?? 0)); ?></div><div class="sub">Rumah aturan bonus yang sudah terdaftar</div></div>
    <div class="bonus-kpi"><div class="label">Rule Aktif</div><div class="value"><?php echo number_format((int)($summary['active_rule_count'] ?? 0)); ?></div><div class="sub"><?php echo number_format((int)($summary['target_linked_rule_count'] ?? 0)); ?> rule sudah ditautkan ke target</div></div>
    <div class="bonus-kpi"><div class="label">Pool Harian</div><div class="value"><?php echo number_format((int)($summary['pool_count'] ?? 0)); ?></div><div class="sub"><?php echo number_format((int)($summary['approved_pool_count'] ?? 0)); ?> pool sudah disetujui</div></div>
    <div class="bonus-kpi"><div class="label">Rekap Bulanan</div><div class="value">Rp <?php echo number_format((float)($summary['monthly_final_amount'] ?? 0), 2, ',', '.'); ?></div><div class="sub"><?php echo number_format((int)($summary['monthly_summary_count'] ?? 0)); ?> pegawai sudah punya ringkasan</div></div>
  </div>
  <ul class="nav nav-pills bonus-section-nav" id="overviewSectionTab" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#overviewPoolPane">Pool Bonus</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#overviewActionPane">Aksi Harian</button></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="overviewPoolPane">
      <div class="card bonus-card">
        <div class="card-body">
          <div class="bonus-toolbar">
            <div class="copy">
              <h5 class="mb-1">Pool bonus harian terbaru</h5>
              <div class="bonus-subcopy">Fokus utama di tab ini hanya daftar pool. Detail tiap hari bisa dibuka dari tombol aksi tanpa membuat halaman jadi panjang ke bawah.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-outline-primary" id="openSyncPenaltyModalBtn">Sync penalti otomatis</button>
              <button type="button" class="btn btn-outline-primary" id="openGeneratePoolSingleModalBtn">Generate Satuan</button>
              <button type="button" class="btn btn-primary" id="openGeneratePoolModalBtn">Generate Bulk dari Target Harian</button>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?php
              $poolStatusCounts = is_array($pool_status_counts ?? null) ? $pool_status_counts : [];
              $poolStatusTabs = [
                'ALL' => 'Semua',
                'DRAFT' => 'Draft',
                'APPROVED' => 'Published',
                'VOID' => 'Void',
              ];
            ?>
            <?php foreach ($poolStatusTabs as $poolStatusKey => $poolStatusLabel): ?>
              <a
                href="<?php echo $buildTableUrl(['tab' => 'overview', 'pool_status' => $poolStatusKey, 'pool_page' => 1]); ?>"
                class="btn btn-sm <?php echo (($poolFilters['status'] ?? 'ALL') === $poolStatusKey) ? 'btn-primary' : 'btn-outline-primary'; ?>"
              >
                <?php echo html_escape($poolStatusLabel); ?>
                <span class="ms-1">(<?php echo number_format((int)($poolStatusCounts[$poolStatusKey] ?? 0)); ?>)</span>
              </a>
            <?php endforeach; ?>
          </div>
          <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <input type="hidden" name="tab" value="overview">
            <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
            <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
            <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
            <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
            <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
            <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
            <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
            <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
            <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
            <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
            <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
            <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
            <div class="row g-3 align-items-end">
              <div class="col-md-5">
                <label class="form-label">Cari pool bonus</label>
                <input type="text" name="pool_q" class="form-control" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>" placeholder="Cari tanggal, nama aturan, outlet, atau status">
              </div>
              <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="pool_status" class="form-select">
                  <option value="ALL" <?php echo (($poolFilters['status'] ?? 'ALL') === 'ALL') ? 'selected' : ''; ?>>Semua status</option>
                  <option value="DRAFT" <?php echo (($poolFilters['status'] ?? 'ALL') === 'DRAFT') ? 'selected' : ''; ?>>Draft</option>
                  <option value="APPROVED" <?php echo (($poolFilters['status'] ?? 'ALL') === 'APPROVED') ? 'selected' : ''; ?>>Published</option>
                  <option value="VOID" <?php echo (($poolFilters['status'] ?? 'ALL') === 'VOID') ? 'selected' : ''; ?>>Void</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Baris</label>
                <select name="pool_per_page" class="form-select">
                  <?php foreach ([10, 25, 50, 100] as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo (int)($poolPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
              </div>
            </div>
          </form>
          <form method="post" action="<?php echo site_url('payroll/bonus/delete-pool-bulk'); ?>" onsubmit="return confirm('Hapus semua pool VOID yang dicentang? Data detail pegawai ikut terhapus permanen.');">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
              <div class="small text-muted">Bulk hapus hanya aman untuk baris berstatus VOID. Baris selain VOID akan dilewati otomatis.</div>
              <button type="submit" class="btn btn-sm btn-outline-danger">Hapus VOID Terpilih</button>
            </div>
            <div class="bonus-table-wrap bonus-table">
              <table class="table align-middle mb-0">
                <thead><tr><th style="width:46px;"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.pool-bulk-checkbox').forEach(cb => cb.checked = this.checked);"></th><th>Tanggal</th><th>Kebijakan</th><th>Target</th><th class="text-end">Penjualan Bersih</th><th class="text-end">Pool</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($poolRows)): ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">Belum ada pool bonus pada bulan ini.</td></tr>
                <?php else: foreach ($poolRows as $row): ?>
                <?php
                  $targetPlanName = trim((string)($row['target_plan_name'] ?? ''));
                  $targetScorePercent = (float)($row['target_score_percent'] ?? 100);
                  $targetGatePassed = (int)($row['target_gate_passed'] ?? 1) === 1;
                  $status = strtoupper((string)($row['approval_status'] ?? 'DRAFT'));
                ?>
                <tr>
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input pool-bulk-checkbox" name="pool_ids[]" value="<?php echo (int)($row['id'] ?? 0); ?>">
                  </td>
                  <td><?php echo html_escape((string)($row['bonus_date'] ?? '-')); ?></td>
                  <td><strong><?php echo html_escape((string)($row['config_name'] ?? $row['rule_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['outlet_name'] ?? $row['division_name'] ?? '-')); ?></div></td>
                  <td>
                    <?php if ($targetPlanName !== ''): ?>
                      <strong><?php echo html_escape($targetPlanName); ?></strong>
                      <div class="small text-muted">
                        Skor <?php echo number_format($targetScorePercent, 2, ',', '.'); ?>%
                        <?php if ($targetGatePassed): ?>
                          <span class="bonus-soft-badge ok ms-1">Lolos</span>
                        <?php else: ?>
                          <span class="bonus-soft-badge warn ms-1">Tertahan</span>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span class="small text-muted">Tidak pakai target khusus</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['net_sales_amount'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end fw-semibold">Rp <?php echo number_format((float)($row['payout_amount'] ?? $row['pool_amount'] ?? 0), 2, ',', '.'); ?></td>
                  <td><span class="bonus-soft-badge <?php echo $status === 'APPROVED' ? 'ok' : ($status === 'VOID' ? 'muted' : 'warn'); ?>"><?php echo html_escape($status); ?></span></td>
                  <td>
                    <div class="bonus-table-actions">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary bonus-pool-detail-btn"
                        data-bonus-date="<?php echo html_escape((string)($row['bonus_date'] ?? '-')); ?>"
                        data-rule-name="<?php echo html_escape((string)($row['config_name'] ?? $row['rule_name'] ?? '-')); ?>"
                        data-scope-name="<?php echo html_escape((string)($row['outlet_name'] ?? $row['division_name'] ?? $row['config_name'] ?? '-')); ?>"
                        data-target-name="<?php echo html_escape($targetPlanName !== '' ? $targetPlanName : 'Tidak pakai target khusus'); ?>"
                        data-target-score="<?php echo number_format($targetScorePercent, 2, ',', '.'); ?>%"
                        data-target-gate="<?php echo $targetGatePassed ? 'Lolos' : 'Tertahan'; ?>"
                        data-net-sales="Rp <?php echo number_format((float)($row['net_sales_amount'] ?? 0), 2, ',', '.'); ?>"
                        data-pool-amount="Rp <?php echo number_format((float)($row['payout_amount'] ?? $row['pool_amount'] ?? 0), 2, ',', '.'); ?>"
                        data-status="<?php echo html_escape($status); ?>"
                      >Detail</button>
                      <?php if ($status === 'DRAFT'): ?>
                        <form method="post" action="<?php echo site_url('payroll/bonus/approve-pool/' . (int)($row['id'] ?? 0)); ?>">
                          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                          <button type="submit" class="btn btn-sm btn-outline-success">Publikasikan</button>
                        </form>
                      <?php endif; ?>
                      <?php if ($status !== 'VOID'): ?>
                        <form method="post" action="<?php echo site_url('payroll/bonus/void-pool/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('VOID pool bonus ini? Data pool lama akan tetap tercatat, tetapi tidak dipakai lagi.');">
                          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                          <input type="hidden" name="notes" value="Override superadmin dari workspace bonus">
                          <button type="submit" class="btn btn-sm btn-outline-warning">Void</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="<?php echo site_url('payroll/bonus/delete-pool/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('Hapus permanen pool bonus ini beserta detail pegawainya?');">
                        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </form>
          <?php $renderPager($poolPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'overview'], $overrides)); }, 'pool_page', 'pool_per_page'); ?>
        </div>
      </div>
    </div>
    <div class="tab-pane fade" id="overviewActionPane">
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card bonus-card">
            <div class="card-body">
              <h5 class="mb-1">Aksi operasional harian</h5>
              <div class="bonus-subcopy mb-4">Semua form operasional saya pindahkan ke modal supaya tampilan utama tetap ringan. Tinggal klik aksi yang dibutuhkan.</div>
              <div class="bonus-action-grid">
                <div class="bonus-action-card">
                  <h6 class="mb-2">1. Sinkron penalti otomatis</h6>
                  <p>Tarik telat, pulang cepat, alpha, tidak hadir shift PH, dan ambil PH dari absensi.</p>
                  <button type="button" class="btn btn-outline-primary btn-sm w-100" id="openSyncPenaltyModalBtnAlt">Buka Form Sync</button>
                </div>
                <div class="bonus-action-card">
                  <h6 class="mb-2">2. Generate pool harian</h6>
                  <p>Pakai mode satuan untuk satu tanggal, atau mode bulk untuk beberapa target harian aktif sekaligus.</p>
                  <button type="button" class="btn btn-primary btn-sm w-100" id="openGeneratePoolModalBtnAlt">Buka Form Generate</button>
                </div>
                <div class="bonus-action-card">
                  <h6 class="mb-2">3. Bangun metric waktu saji</h6>
                  <p>Bangun metrik layanan dari rentang order hingga tersaji per outlet dan per shift.</p>
                  <button type="button" class="btn btn-outline-dark btn-sm w-100" id="openServiceMetricModalBtn">Buka Form Metric</button>
                </div>
                <div class="bonus-action-card">
                  <h6 class="mb-2">4. Refresh rekap bulanan</h6>
                  <p>Segarkan ringkasan bonus bulanan setelah draft harian dipublikasikan.</p>
                  <button type="button" class="btn btn-outline-success btn-sm w-100" id="openMonthlySummaryModalBtn">Buka Form Rekap</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card bonus-card h-100">
            <div class="card-header bg-white border-0 pb-0">
              <h5 class="mb-1">Sinyal yang butuh perhatian</h5>
              <div class="small text-muted">Ringkasan cepat supaya admin tahu mana yang harus dituntaskan dulu.</div>
            </div>
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Tipe penalti aktif</span><strong><?php echo number_format((int)($summary['penalty_type_count'] ?? 0)); ?></strong></div>
              <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Kejadian penalti bulan ini</span><strong><?php echo number_format((int)($summary['penalty_event_count'] ?? 0)); ?></strong></div>
              <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Peer review menunggu moderasi</span><strong><?php echo number_format((int)($summary['pending_peer_review_count'] ?? 0)); ?></strong></div>
              <div class="d-flex justify-content-between align-items-center py-2"><span>Peer review yang sudah lolos</span><strong><?php echo number_format((int)($summary['approved_peer_review_count'] ?? 0)); ?></strong></div>
              <hr>
              <div class="small text-muted">Tahap berikutnya yang paling penting:</div>
              <ol class="small mb-0 mt-2 ps-3">
                <li>Isi konfigurasi bonus global lebih dulu.</li>
                <li>Hubungkan kebijakan bonus ke target keuangan yang relevan.</li>
                <li>Finalkan master penalti personal dan tim.</li>
                <li>Aktifkan form penilaian 360 lewat portal pegawai.</li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'rules'): ?>
  <div class="card bonus-card">
    <div class="card-body">
      <div class="bonus-toolbar">
        <div class="copy">
          <h5 class="mb-1">Kebijakan bonus</h5>
          <div class="bonus-subcopy">Kebijakan ini menghubungkan target keuangan dengan bonus. Cukup isi satu kali, lalu generator pool akan membaca target dan menghitung bonus pegawai.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-primary" id="openConfigCreateModalBtn">Tambah kebijakan bonus</button>
        </div>
      </div>
      <div class="bonus-empty-note mb-3">Fokus halaman ini hanya pada hubungan target keuangan, persentase bonus yang dibuka, dan aturan konversi penalti poin. Bobot pembagian pegawai diatur terpisah di tab Bobot Bonus.</div>
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="rules">
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label">Cari kebijakan bonus</label>
            <input type="text" name="config_q" class="form-control" value="<?php echo html_escape((string)($configFilters['q'] ?? '')); ?>" placeholder="Cari nama, kode, target, divisi, atau catatan">
          </div>
          <div class="col-md-2">
            <label class="form-label">Baris</label>
            <select name="config_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($configPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table mb-2">
        <table class="table align-middle mb-0">
          <thead><tr><th>Kebijakan</th><th>Scope</th><th>Gerbang Bulanan</th><th>Sumber Pool</th><th>% Bonus</th><th>Konversi Penalti</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php if (empty($configTableRows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada kebijakan bonus.</td></tr>
          <?php else: foreach ($configTableRows as $row): ?>
            <?php
              $cfgStatus = strtoupper((string)($row['status'] ?? 'DRAFT'));
              $scopeName = (string)($row['outlet_name'] ?? $row['division_name'] ?? ($row['distribution_scope'] ?? 'GLOBAL'));
              $penaltyModeText = $pointPenaltyModeLabels[strtoupper((string)($row['point_penalty_currency_mode'] ?? 'PERCENT_SHARE'))] ?? (string)($row['point_penalty_currency_mode'] ?? 'PERCENT_SHARE');
            ?>
            <tr>
              <td>
                <strong><?php echo html_escape((string)($row['config_name'] ?? '-')); ?></strong>
                <div class="small text-muted"><?php echo html_escape((string)($row['config_code'] ?? '-')); ?></div>
              </td>
              <td>
                <div><?php echo html_escape($scopeName !== '' ? $scopeName : 'Global'); ?></div>
                <div class="small text-muted"><?php echo html_escape((string)($row['distribution_scope'] ?? 'GLOBAL')); ?></div>
              </td>
              <td>
                <div><?php echo html_escape((string)($row['target_plan_name'] ?? 'Belum ditautkan')); ?></div>
                <div class="small text-muted">Status aktif bulanan</div>
              </td>
              <td>
                <div><?php echo html_escape($poolSourceModeLabels[strtoupper((string)($row['pool_source_mode'] ?? 'TARGET_LINKED'))] ?? (string)($row['pool_source_mode'] ?? 'TARGET_LINKED')); ?></div>
                <div class="small text-muted">Nilai sumber: <?php echo number_format((float)($row['pool_source_value'] ?? 0), 4, ',', '.'); ?></div>
              </td>
              <td>
                <div class="fw-semibold"><?php echo number_format((float)($row['payout_percent'] ?? 100), 2, ',', '.'); ?>%</div>
                <div class="small text-muted">dibuka dari realisasi target</div>
              </td>
              <td>
                <div><?php echo html_escape($penaltyModeText); ?></div>
                <div class="small text-muted">Nilai: <?php echo number_format((float)($row['point_penalty_currency_value'] ?? 0), 4, ',', '.'); ?></div>
              </td>
              <td><span class="bonus-soft-badge <?php echo $cfgStatus === 'ACTIVE' ? 'ok' : ($cfgStatus === 'INACTIVE' ? 'muted' : 'warn'); ?>"><?php echo html_escape($cfgStatus); ?></span></td>
              <td>
                <div class="bonus-table-actions">
                  <button type="button" class="btn btn-sm btn-outline-secondary bonus-config-detail-btn"
                          data-config-name="<?php echo html_escape((string)($row['config_name'] ?? '-')); ?>"
                          data-config-code="<?php echo html_escape((string)($row['config_code'] ?? '-')); ?>"
                          data-description="<?php echo html_escape((string)($row['description'] ?? '-')); ?>"
                          data-distribution-scope="<?php echo html_escape((string)($row['distribution_scope'] ?? 'GLOBAL')); ?>"
                          data-pool-source-mode="<?php echo html_escape((string)($row['pool_source_mode'] ?? 'TARGET_LINKED')); ?>"
                          data-pool-source-value="<?php echo html_escape((string)($row['pool_source_value'] ?? '0')); ?>"
                          data-payout-percent="<?php echo html_escape((string)($row['payout_percent'] ?? '100')); ?>"
                          data-point-penalty-mode="<?php echo html_escape((string)($row['point_penalty_currency_mode'] ?? 'PERCENT_SHARE')); ?>"
                          data-point-penalty-value="<?php echo html_escape((string)($row['point_penalty_currency_value'] ?? '5')); ?>"
                          data-target-name="<?php echo html_escape((string)($row['target_plan_name'] ?? 'Belum ditautkan')); ?>"
                          data-daily-target-name="Otomatis mengikuti target DAILY aktif saat generate"
                          data-pool-formula="<?php echo html_escape(number_format((float)($row['payout_percent'] ?? 100), 2, ',', '.') . '%'); ?>"
                          data-scope-name="<?php echo html_escape($scopeName !== '' ? $scopeName : 'Global'); ?>"
                          data-status="<?php echo html_escape($cfgStatus); ?>"
                          data-notes="<?php echo html_escape((string)($row['notes'] ?? '-')); ?>">Detail</button>
                  <button type="button" class="btn btn-sm btn-outline-primary bonus-config-edit-btn"
                          data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                          data-config-name="<?php echo html_escape((string)($row['config_name'] ?? '')); ?>"
                          data-config-code="<?php echo html_escape((string)($row['config_code'] ?? '')); ?>"
                          data-description="<?php echo html_escape((string)($row['description'] ?? '')); ?>"
                          data-distribution-scope="<?php echo html_escape((string)($row['distribution_scope'] ?? 'GLOBAL')); ?>"
                          data-pool-source-mode="<?php echo html_escape((string)($row['pool_source_mode'] ?? 'TARGET_LINKED')); ?>"
                          data-pool-source-value="<?php echo html_escape((string)($row['pool_source_value'] ?? '0')); ?>"
                          data-payout-percent="<?php echo html_escape((string)($row['payout_percent'] ?? '100')); ?>"
                          data-point-penalty-mode="<?php echo html_escape((string)($row['point_penalty_currency_mode'] ?? 'PERCENT_SHARE')); ?>"
                          data-point-penalty-value="<?php echo html_escape((string)($row['point_penalty_currency_value'] ?? '5')); ?>"
                          data-linked-target-required="<?php echo (int)($row['linked_target_required'] ?? 1); ?>"
                          data-include-shift-revenue-factor="<?php echo (int)($row['include_shift_revenue_factor'] ?? 1); ?>"
                          data-include-service-time-factor="<?php echo (int)($row['include_service_time_factor'] ?? 1); ?>"
                          data-include-peer-review-factor="<?php echo (int)($row['include_peer_review_factor'] ?? 1); ?>"
                          data-include-attendance-factor="<?php echo (int)($row['include_attendance_factor'] ?? 1); ?>"
                          data-include-manual-penalty-factor="<?php echo (int)($row['include_manual_penalty_factor'] ?? 1); ?>"
                          data-status-value="<?php echo html_escape((string)($row['status'] ?? 'ACTIVE')); ?>"
                          data-notes="<?php echo html_escape((string)($row['notes'] ?? '')); ?>"
                          data-rule-name="<?php echo html_escape((string)($row['primary_rule_name'] ?? '')); ?>"
                          data-rule-code="<?php echo html_escape((string)($row['primary_rule_code'] ?? '')); ?>"
                          data-outlet-id="<?php echo (int)($row['outlet_id'] ?? 0); ?>"
                          data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                          data-target-plan-id="<?php echo (int)($row['linked_target_plan_id'] ?? 0); ?>"
                          data-daily-target-plan-id="<?php echo (int)($row['daily_target_plan_id'] ?? 0); ?>"
                          data-target-gate-mode="<?php echo html_escape((string)($row['target_gate_mode'] ?? 'WEIGHTED_SCORE')); ?>"
                          data-min-target-score="<?php echo html_escape((string)($row['min_target_score'] ?? '100')); ?>"
                          data-threshold-amount="<?php echo html_escape((string)($row['threshold_amount'] ?? '0')); ?>"
                          data-pool-formula-type="<?php echo html_escape((string)($row['pool_formula_type'] ?? 'PERCENTAGE')); ?>"
                          data-pool-formula-value="<?php echo html_escape((string)($row['pool_formula_value'] ?? '0')); ?>"
                          data-min-shift-base-pct="<?php echo html_escape((string)($row['min_shift_base_pct'] ?? '30')); ?>"
                          data-ph-bonus-mode="<?php echo html_escape((string)($row['ph_bonus_mode'] ?? 'EXCLUDE')); ?>"
                          data-ph-point-deduction="<?php echo html_escape((string)($row['ph_point_deduction'] ?? '0')); ?>"
                          data-service-time-target-minute="<?php echo html_escape((string)($row['service_time_target_minute'] ?? '0')); ?>"
                          data-service-time-weight="<?php echo html_escape((string)($row['service_time_weight'] ?? '0')); ?>"
                          data-shift-revenue-weight="<?php echo html_escape((string)($row['shift_revenue_weight'] ?? '1')); ?>"
                          data-peer-review-weight="<?php echo html_escape((string)($row['peer_review_weight'] ?? '0')); ?>"
                          data-attendance-weight="<?php echo html_escape((string)($row['attendance_weight'] ?? '1')); ?>"
                          data-manual-penalty-weight="<?php echo html_escape((string)($row['manual_penalty_weight'] ?? '1')); ?>">Edit</button>
                  <form method="post" action="<?php echo site_url('payroll/bonus/config-delete/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('Nonaktifkan kebijakan bonus ini?');">
                    <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Nonaktifkan</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($configPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'rules'], $overrides)); }, 'config_page', 'config_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'employee_daily'): ?>
  <div class="card bonus-card">
    <div class="card-body">
      <div class="bonus-toolbar">
        <div class="copy">
          <h5 class="mb-1">Bonus harian masing-masing pegawai</h5>
          <div class="bonus-subcopy">Tab ini membaca hasil generate pool per hari, lalu menampilkan hasil pembagian ke tiap pegawai setelah bobot dan penalti diterapkan.</div>
        </div>
      </div>
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="employee_daily">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Cari bonus harian pegawai</label>
            <input type="text" name="employee_daily_q" class="form-control" value="<?php echo html_escape((string)($employeeDailyFilters['q'] ?? '')); ?>" placeholder="Cari pegawai, divisi, jabatan, kebijakan, atau shift">
          </div>
          <div class="col-md-3">
            <label class="form-label">Tanggal awal</label>
            <input type="date" name="employee_daily_date_start" class="form-control" value="<?php echo html_escape((string)($employeeDailyFilters['date_start'] ?? '')); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Tanggal akhir</label>
            <input type="date" name="employee_daily_date_end" class="form-control" value="<?php echo html_escape((string)($employeeDailyFilters['date_end'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Baris</label>
            <select name="employee_daily_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($employeeDailyPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead><tr><th>Tanggal</th><th>Pegawai</th><th>Shift Kerja</th><th>Kebijakan</th><th class="text-center">Irisan</th><th class="text-end">Omzet Porsi Saya</th><th class="text-end">Bonus Kotor Saya</th><th class="text-end">Potongan Penalti</th><th class="text-end">Bonus Bersih Saya</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php if (empty($employeeDailyRows)): ?>
            <tr><td colspan="11" class="text-center text-muted py-4">Belum ada bonus harian pegawai untuk bulan ini.</td></tr>
          <?php else: foreach ($employeeDailyRows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['attendance_date'] ?? $row['bonus_date'] ?? '-')); ?></td>
              <td><strong><?php echo html_escape((string)($row['employee_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape(trim((string)($row['division_name'] ?? '-') . ' · ' . (string)($row['position_name'] ?? '-'))); ?></div></td>
              <td><?php echo html_escape(trim((string)($row['shift_code'] ?? '') . ' ' . (string)($row['shift_name'] ?? ''))); ?></td>
              <td>
                <div><?php echo html_escape((string)($row['config_name'] ?? '-')); ?></div>
                <div class="small text-muted">
                  <?php echo strtoupper((string)($row['approval_status'] ?? 'DRAFT')) === 'DRAFT' ? 'Estimasi draft bonus harian' : 'Pool bonus harian'; ?>
                </div>
              </td>
              <td class="text-center"><?php echo number_format((int)($row['slice_count'] ?? 0)); ?>x</td>
              <td class="text-end">Rp <?php echo number_format((float)($row['revenue_in_shift'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end">Rp <?php echo number_format((float)($row['raw_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end text-danger">Rp <?php echo number_format((float)($row['penalty_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold">Rp <?php echo number_format((float)($row['final_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td><span class="bonus-soft-badge <?php echo strtoupper((string)($row['approval_status'] ?? 'DRAFT')) === 'APPROVED' ? 'ok' : 'warn'; ?>"><?php echo html_escape((string)($row['approval_status'] ?? 'DRAFT')); ?></span></td>
              <td>
                <?php if ((int)($row['id'] ?? 0) > 0): ?>
                  <a href="<?php echo site_url('payroll/bonus/daily-detail/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary">Audit</a>
                <?php else: ?>
                  <span class="text-muted small">Penalty manual</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($employeeDailyPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'employee_daily'], $overrides)); }, 'employee_daily_page', 'employee_daily_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'weights'): ?>
  <div class="card bonus-card">
    <div class="card-body">
      <div class="bonus-toolbar">
        <div class="copy">
          <h5 class="mb-1">Bobot bonus umum per divisi, jabatan, pegawai, dan shift</h5>
          <div class="bonus-subcopy">Bobot di sini berlaku sebagai dasar umum. Jadi kita tidak perlu membuat pembobotan ulang tiap target harian, cukup atur sekali lalu engine membaca saat pool digenerate.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary" id="openWeightCreateModalBtn">Tambah bobot bonus</button>
        </div>
      </div>
      <div class="bonus-empty-note mb-3">
        Gunakan tab ini jika pembagian bonus tidak ingin rata. Contoh: supervisor punya bobot lebih tinggi daripada crew, atau shift malam mendapat porsi khusus. Bobot di sini bersifat global dan berlaku untuk semua kebijakan bonus.
      </div>
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="weights">
        <input type="hidden" name="weight_status" value="<?php echo html_escape((string)($weightFilters['status'] ?? 'ACTIVE')); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-7">
            <label class="form-label">Cari bobot bonus</label>
            <input type="text" name="weight_q" class="form-control" value="<?php echo html_escape((string)($weightFilters['q'] ?? '')); ?>" placeholder="Cari frekuensi target, jenis bobot, atau target bobot">
          </div>
          <div class="col-md-3">
            <label class="form-label">Baris</label>
            <select name="weight_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($weightPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?php echo $buildTableUrl(['tab' => 'weights', 'weight_status' => 'ACTIVE', 'weight_page' => 1]); ?>" class="btn btn-sm <?php echo strtoupper((string)($weightFilters['status'] ?? 'ACTIVE')) === 'ACTIVE' ? 'btn-primary' : 'btn-outline-primary'; ?>">Aktif</a>
        <a href="<?php echo $buildTableUrl(['tab' => 'weights', 'weight_status' => 'INACTIVE', 'weight_page' => 1]); ?>" class="btn btn-sm <?php echo strtoupper((string)($weightFilters['status'] ?? 'ACTIVE')) === 'INACTIVE' ? 'btn-primary' : 'btn-outline-primary'; ?>">Nonaktif</a>
        <a href="<?php echo $buildTableUrl(['tab' => 'weights', 'weight_status' => 'ALL', 'weight_page' => 1]); ?>" class="btn btn-sm <?php echo strtoupper((string)($weightFilters['status'] ?? 'ACTIVE')) === 'ALL' ? 'btn-primary' : 'btn-outline-primary'; ?>">Semua</a>
      </div>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0 bonus-table-compact bonus-weight-table">
          <thead><tr><th class="col-frequency">Frekuensi</th><th class="col-scope">Jenis Bobot</th><th class="col-target">Target</th><th class="text-end col-weight">Bobot</th><th class="col-status">Status</th><th class="col-actions">Aksi</th></tr></thead>
          <tbody>
          <?php if (empty($weightRows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada bobot bonus yang disimpan.</td></tr>
          <?php else: foreach ($weightRows as $row): ?>
            <tr>
              <td><span class="small fw-semibold"><?php echo html_escape((string)($row['target_frequency_label'] ?? 'ALL')); ?></span></td>
              <td><span class="small fw-semibold"><?php echo html_escape($weightScopeLabels[strtoupper((string)($row['weight_scope'] ?? ''))] ?? (string)($row['weight_scope'] ?? '-')); ?></span></td>
              <td><div class="bonus-weight-target" title="<?php echo html_escape((string)($row['scope_label'] ?? ('Scope #' . (int)($row['scope_id'] ?? 0)))); ?>"><?php echo html_escape((string)($row['scope_label'] ?? ('Scope #' . (int)($row['scope_id'] ?? 0)))); ?></div></td>
              <td class="text-end"><?php echo number_format((float)($row['point_weight'] ?? 1), 4, ',', '.'); ?></td>
              <td><span class="bonus-soft-badge <?php echo (int)($row['is_active'] ?? 0) === 1 ? 'ok' : 'muted'; ?>"><?php echo (int)($row['is_active'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif'; ?></span></td>
              <td>
                <div class="bonus-table-actions">
                  <button type="button" class="btn btn-sm btn-outline-secondary bonus-weight-detail-btn"
                    data-target-frequency="<?php echo html_escape((string)($row['target_frequency_label'] ?? 'ALL')); ?>"
                    data-weight-scope="<?php echo html_escape($weightScopeLabels[strtoupper((string)($row['weight_scope'] ?? ''))] ?? (string)($row['weight_scope'] ?? '-')); ?>"
                    data-scope-label="<?php echo html_escape((string)($row['scope_label'] ?? ('Scope #' . (int)($row['scope_id'] ?? 0)))); ?>"
                    data-point-weight="<?php echo number_format((float)($row['point_weight'] ?? 1), 4, ',', '.'); ?>"
                    data-status="<?php echo (int)($row['is_active'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif'; ?>"
                    data-notes="<?php echo html_escape((string)($row['notes'] ?? '-')); ?>"
                  >Detail</button>
                  <button type="button" class="btn btn-sm btn-outline-primary bonus-weight-edit-btn"
                    data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                    data-target-frequency="<?php echo html_escape((string)($row['target_frequency_label'] ?? 'ALL')); ?>"
                    data-weight-scope="<?php echo html_escape((string)($row['weight_scope'] ?? 'DIVISION')); ?>"
                    data-scope-id="<?php echo (int)($row['scope_id'] ?? 0); ?>"
                    data-point-weight="<?php echo html_escape((string)($row['point_weight'] ?? '1')); ?>"
                    data-is-active="<?php echo (int)($row['is_active'] ?? 0); ?>"
                    data-notes="<?php echo html_escape((string)($row['notes'] ?? '')); ?>"
                  >Edit</button>
                  <form method="post" action="<?php echo site_url('payroll/bonus/weight-delete/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('Nonaktifkan bobot bonus ini?');">
                    <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Nonaktifkan</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($weightPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'weights'], $overrides)); }, 'weight_page', 'weight_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'penalties'): ?>
  <ul class="nav nav-pills bonus-section-nav" id="penaltySectionTab" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link <?php echo $penaltyView === 'master' ? 'active' : ''; ?>" type="button" data-bs-toggle="tab" data-bs-target="#penaltyMasterPane">Master Penalti</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link <?php echo $penaltyView === 'events' ? 'active' : ''; ?>" type="button" data-bs-toggle="tab" data-bs-target="#penaltyEventPane">Kejadian Penalti</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link <?php echo $penaltyView === 'detail' ? 'active' : ''; ?>" type="button" data-bs-toggle="tab" data-bs-target="#penaltyDetailPane">Rincian Penalti</button></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade <?php echo $penaltyView === 'master' ? 'show active' : ''; ?>" id="penaltyMasterPane">
      <div class="card bonus-card mb-3">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="mb-1">Cara paling aman mengelompokkan penalti</h5>
        </div>
        <div class="card-body small text-muted">
          <ul class="mb-0 bonus-guide-list ps-3">
            <li><strong>Otomatis</strong>: telat, alpha, pulang cepat, tidak hadir shift PH, ambil PH, layanan lambat, atau peer rating yang sudah dimoderasi.</li>
            <li><strong>Semi manual</strong>: follow IG, story/tagging, checklist sosial media, atau audit kepatuhan yang diverifikasi admin dan tidak perlu diinput ulang tiap hari.</li>
            <li><strong>Manual</strong>: komplain tamu, area kotor, salah order, pemborosan bahan, kerusakan alat, atau pelanggaran khusus yang memang dinilai per kejadian.</li>
            <li>Kalau sebuah penalti bisa berlaku sampai ada perubahan, gunakan siklus <em>sampai ada perubahan</em>.</li>
            <li>Kalau sebuah penalti memang kejadian harian, gunakan siklus <em>harian</em> atau <em>per kejadian</em>.</li>
          </ul>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-12">
          <div class="card bonus-card">
            <div class="card-body">
              <div class="bonus-toolbar">
                <div class="copy">
                  <h5 class="mb-1">Master penalti bonus</h5>
                  <div class="bonus-subcopy">Daftar master dibuat fokus ke tabel. Form tambah dan edit dipindah ke modal, dan detail bisa dibuka tanpa mengganggu alur baca.</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-primary" id="openPenaltyCreateModalBtn">Tambah master penalti</button>
                </div>
              </div>
              <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
                <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                <input type="hidden" name="tab" value="penalties">
                <input type="hidden" name="penalty_view" value="master">
                <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
                <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
                <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
                <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
                <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
                <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
                <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
                <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
                <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
                <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
                <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
                <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
                <div class="row g-3 align-items-end">
                  <div class="col-md-7">
                    <label class="form-label">Cari master penalti</label>
                    <input type="text" name="penalty_type_q" class="form-control" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>" placeholder="Cari nama penalti, kode, kategori, mode, atau sumber">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Baris</label>
                    <select name="penalty_type_per_page" class="form-select">
                      <?php foreach ([10, 25, 50, 100] as $size): ?>
                        <option value="<?php echo $size; ?>" <?php echo (int)($penaltyTypePg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                  </div>
                </div>
              </form>
              <div class="bonus-table-wrap bonus-table">
                <table class="table align-middle mb-0 bonus-table-compact bonus-penalty-master-table">
                  <thead><tr><th class="penalty-name-cell">Nama Penalti</th><th>Kategori</th><th>Mode</th><th>Scope</th><th class="text-end">Poin</th><th class="penalty-auto-cell">Auto / Parameter</th><th>Approval</th><th>Aksi</th></tr></thead>
                  <tbody>
                  <?php if (empty($penaltyTypeRows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada master penalti bonus.</td></tr>
                  <?php else: foreach ($penaltyTypeRows as $row): ?>
                    <?php $autoSource = strtoupper((string)($row['auto_source'] ?? '')); ?>
                    <?php $autoRule = $buildPenaltyAutoRule($row); ?>
                    <tr>
                      <td class="penalty-name-cell"><strong><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?></div></td>
                      <td><?php echo html_escape($penaltyCategoryLabels[strtoupper((string)($row['category'] ?? '-'))] ?? (string)($row['category'] ?? '-')); ?></td>
                      <td><span class="bonus-soft-badge info"><?php echo html_escape($penaltyBehaviorLabels[strtoupper((string)($row['behavior_mode'] ?? 'MANUAL'))] ?? 'Manual'); ?></span><div class="small text-muted"><?php echo html_escape($penaltyDeductionLabels[strtoupper((string)($row['deduction_mode'] ?? 'FIXED_POINT'))] ?? 'Potong poin tetap'); ?></div></td>
                      <td><?php echo html_escape($penaltyScopeLabels[strtoupper((string)($row['applies_scope'] ?? '-'))] ?? (string)($row['applies_scope'] ?? '-')); ?></td>
                      <td class="text-end"><?php echo number_format((float)($row['default_points_deducted'] ?? 0), 2, ',', '.'); ?><div class="small text-muted">Rp <?php echo number_format((float)($row['default_amount_deducted'] ?? 0), 2, ',', '.'); ?></div></td>
                      <td class="penalty-auto-cell"><span class="bonus-soft-badge <?php echo $autoSource !== '' ? 'ok' : 'muted'; ?>"><?php echo html_escape($autoSourceLabels[$autoSource] ?? ($autoSource !== '' ? $autoSource : 'Tidak')); ?></span><div class="small text-muted"><?php echo html_escape($verificationCycleLabels[strtoupper((string)($row['verification_cycle'] ?? 'PER_EVENT'))] ?? 'Per kejadian'); ?></div><div class="small text-muted mt-1"><?php echo html_escape($autoRule['summary']); ?></div><div class="small text-muted"><?php echo html_escape($autoRule['detail']); ?></div></td>
                      <td><span class="bonus-soft-badge <?php echo (int)($row['approval_required'] ?? 0) === 1 ? 'warn' : 'muted'; ?>"><?php echo (int)($row['approval_required'] ?? 0) === 1 ? 'Ya' : 'Tidak'; ?></span></td>
                      <td>
                        <div class="bonus-table-actions">
                          <button type="button" class="btn btn-sm btn-outline-secondary bonus-penalty-detail-btn" data-penalty-name="<?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?>" data-penalty-code="<?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?>" data-category="<?php echo html_escape($penaltyCategoryLabels[strtoupper((string)($row['category'] ?? '-'))] ?? (string)($row['category'] ?? '-')); ?>" data-scope="<?php echo html_escape($penaltyScopeLabels[strtoupper((string)($row['applies_scope'] ?? '-'))] ?? (string)($row['applies_scope'] ?? '-')); ?>" data-mode="<?php echo html_escape($penaltyBehaviorLabels[strtoupper((string)($row['behavior_mode'] ?? 'MANUAL'))] ?? 'Manual'); ?>" data-deduction-mode="<?php echo html_escape($penaltyDeductionLabels[strtoupper((string)($row['deduction_mode'] ?? 'FIXED_POINT'))] ?? 'Potong poin tetap'); ?>" data-auto-source="<?php echo html_escape($autoSourceLabels[$autoSource] ?? ($autoSource !== '' ? $autoSource : 'Tidak')); ?>" data-attendance-trigger="<?php echo html_escape((string)($row['attendance_trigger'] ?? '-')); ?>" data-verification-cycle="<?php echo html_escape($verificationCycleLabels[strtoupper((string)($row['verification_cycle'] ?? 'PER_EVENT'))] ?? 'Per kejadian'); ?>" data-auto-rule-summary="<?php echo html_escape($autoRule['summary']); ?>" data-auto-rule-detail="<?php echo html_escape($autoRule['detail']); ?>" data-points="<?php echo number_format((float)($row['default_points_deducted'] ?? 0), 2, ',', '.'); ?>" data-amount="Rp <?php echo number_format((float)($row['default_amount_deducted'] ?? 0), 2, ',', '.'); ?>" data-approval="<?php echo (int)($row['approval_required'] ?? 0) === 1 ? 'Ya' : 'Tidak'; ?>" data-evidence="<?php echo (int)($row['requires_evidence'] ?? 0) === 1 ? 'Ya' : 'Tidak'; ?>" data-status="<?php echo (int)($row['is_active'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif'; ?>" data-notes="<?php echo html_escape((string)($row['notes'] ?? '-')); ?>">Detail</button>
                          <button type="button" class="btn btn-sm btn-outline-primary bonus-penalty-edit-btn" data-id="<?php echo (int)($row['id'] ?? 0); ?>" data-penalty-name="<?php echo html_escape((string)($row['penalty_name'] ?? '')); ?>" data-penalty-code="<?php echo html_escape((string)($row['penalty_code'] ?? '')); ?>" data-category="<?php echo html_escape((string)($row['category'] ?? 'OTHER')); ?>" data-applies-scope="<?php echo html_escape((string)($row['applies_scope'] ?? 'BOTH')); ?>" data-deduction-mode="<?php echo html_escape((string)($row['deduction_mode'] ?? 'FIXED_POINT')); ?>" data-behavior-mode="<?php echo html_escape((string)($row['behavior_mode'] ?? 'MANUAL')); ?>" data-auto-source="<?php echo html_escape((string)($row['auto_source'] ?? '')); ?>" data-attendance-trigger="<?php echo html_escape((string)($row['attendance_trigger'] ?? '')); ?>" data-verification-cycle="<?php echo html_escape((string)($row['verification_cycle'] ?? 'PER_EVENT')); ?>" data-default-points-deducted="<?php echo html_escape((string)($row['default_points_deducted'] ?? '0')); ?>" data-default-amount-deducted="<?php echo html_escape((string)($row['default_amount_deducted'] ?? '0')); ?>" data-service-target-minute="<?php echo html_escape((string)($row['service_target_minute'] ?? '15')); ?>" data-service-step-minute="<?php echo html_escape((string)($row['service_step_minute'] ?? '5')); ?>" data-peer-star-4-points="<?php echo html_escape((string)($row['peer_star_4_points'] ?? '1')); ?>" data-peer-star-3-points="<?php echo html_escape((string)($row['peer_star_3_points'] ?? '2')); ?>" data-peer-star-2-points="<?php echo html_escape((string)($row['peer_star_2_points'] ?? '3')); ?>" data-peer-star-1-points="<?php echo html_escape((string)($row['peer_star_1_points'] ?? '4')); ?>" data-is-manual-only="<?php echo (int)($row['is_manual_only'] ?? 0); ?>" data-approval-required="<?php echo (int)($row['approval_required'] ?? 1); ?>" data-requires-evidence="<?php echo (int)($row['requires_evidence'] ?? 0); ?>" data-is-active="<?php echo (int)($row['is_active'] ?? 0); ?>" data-sort-order="<?php echo (int)($row['sort_order'] ?? 0); ?>" data-notes="<?php echo html_escape((string)($row['notes'] ?? '')); ?>">Edit</button>
                          <form method="post" action="<?php echo site_url('payroll/bonus/penalty-delete/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('Nonaktifkan master penalti ini?');">
                            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Nonaktifkan</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
              <?php $renderPager($penaltyTypePg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'penalties'], $overrides)); }, 'penalty_type_page', 'penalty_type_per_page'); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="tab-pane fade <?php echo $penaltyView === 'events' ? 'show active' : ''; ?>" id="penaltyEventPane">
      <div class="card bonus-card"><div class="card-body"><div class="bonus-toolbar"><div class="copy"><h5 class="mb-1">Rekap kejadian penalti per nama</h5><div class="bonus-subcopy">Di sini kita baca ringkasan penalti per pegawai atau per tim. Kalau ingin audit satu per satu, buka tab <strong>Rincian Penalti</strong>.</div></div><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-primary" id="openPenaltyEventCreateModalBtn">Tambah kejadian penalti</button></div></div>
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>"><input type="hidden" name="tab" value="penalties"><input type="hidden" name="penalty_view" value="events"><input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>"><input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>"><input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>"><input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>"><input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>"><input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>"><input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>"><input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>"><input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>"><input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>"><input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>"><input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Cari nama / penalti</label>
            <input type="text" name="penalty_event_q" class="form-control" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>" placeholder="Cari pegawai, divisi, jenis penalti, kode, alasan, atau status">
          </div>
          <div class="col-md-2">
            <label class="form-label">Tanggal awal</label>
            <input type="date" name="penalty_event_date_start" class="form-control" value="<?php echo html_escape((string)($penaltyEventFilters['date_start'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Tanggal akhir</label>
            <input type="date" name="penalty_event_date_end" class="form-control" value="<?php echo html_escape((string)($penaltyEventFilters['date_end'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Baris</label>
            <select name="penalty_event_per_page" class="form-select"><?php foreach ([10, 25, 50, 100] as $size): ?><option value="<?php echo $size; ?>" <?php echo (int)($penaltyEventPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option><?php endforeach; ?></select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
          <div class="col-md-2 d-grid">
            <a href="<?php echo $buildTableUrl([
              'tab' => 'penalties',
              'penalty_view' => 'events',
              'penalty_event_q' => '',
              'penalty_event_date_start' => $month . '-01',
              'penalty_event_date_end' => date('Y-m-t', strtotime($month . '-01')),
              'penalty_event_page' => 1,
            ]); ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Nama / Target</th>
              <th>Scope</th>
              <th class="text-center">Hari</th>
              <th class="text-center">Kejadian</th>
              <th class="text-end">Total Poin</th>
              <th class="text-end">Total Nominal</th>
              <th>Jenis Penalti</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($penaltyEventRows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada kejadian penalti pada bulan ini.</td></tr>
          <?php else: foreach ($penaltyEventRows as $row): ?>
            <?php
              $eventScope = strtoupper((string)($row['penalty_scope'] ?? 'PERSONAL'));
              $targetName = $eventScope === 'TEAM'
                  ? (
                      trim((string)($row['employee_name'] ?? '')) !== ''
                          ? trim((string)$row['employee_name'])
                          : (trim((string)($row['division_name'] ?? '')) !== '' ? trim((string)$row['division_name']) : 'Semua divisi')
                    )
                  : (string)($row['employee_name'] ?? '-');
              $mapKey = $eventScope . ':' . (int)($row['employee_id'] ?? 0) . ':' . (int)($row['division_id'] ?? 0);
              $detailRows = (array)($penaltyEventDetailMap[$mapKey] ?? []);
              $detailPayload = [];
              foreach ($detailRows as $detailRow) {
                  $detailPayload[] = [
                      'id' => (int)($detailRow['id'] ?? 0),
                      'date' => (string)($detailRow['penalty_date'] ?? '-'),
                      'name' => (string)($detailRow['penalty_name'] ?? '-'),
                      'code' => (string)($detailRow['penalty_code'] ?? '-'),
                      'source_type' => (string)($detailRow['source_type'] ?? 'MANUAL'),
                      'status' => (string)($detailRow['status'] ?? 'APPROVED'),
                      'points' => (float)($detailRow['points_deducted'] ?? 0),
                      'amount' => (float)($detailRow['amount_deducted'] ?? 0),
                      'reason' => (string)($detailRow['reason_text'] ?? ''),
                      'rule_name' => (string)($detailRow['rule_name'] ?? 'Tanpa aturan spesifik'),
                      'shift_name' => trim((string)(($detailRow['shift_code'] ?? '') . ' ' . ($detailRow['shift_name'] ?? ''))),
                  ];
              }
            ?>
            <tr>
              <td>
                <strong><?php echo html_escape($targetName); ?></strong>
                <div class="small text-muted">
                  <?php echo html_escape((string)($row['first_penalty_date'] ?? '-')); ?> s/d <?php echo html_escape((string)($row['last_penalty_date'] ?? '-')); ?>
                </div>
              </td>
              <td><span class="bonus-soft-badge info"><?php echo html_escape($eventScope === 'TEAM' ? 'Tim' : 'Personal'); ?></span></td>
              <td class="text-center"><?php echo number_format((int)($row['active_days'] ?? 0)); ?></td>
              <td class="text-center"><?php echo number_format((int)($row['event_count'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['total_points_deducted'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end text-danger">Rp <?php echo number_format((float)($row['total_amount_deducted'] ?? 0), 2, ',', '.'); ?></td>
              <td>
                <div class="small fw-semibold"><?php echo html_escape((string)($row['penalty_name_list'] ?? '-')); ?></div>
                <div class="small text-muted">
                  Auto <?php echo number_format((int)($row['auto_event_count'] ?? 0)); ?> · Manual <?php echo number_format((int)($row['manual_event_count'] ?? 0)); ?>
                </div>
              </td>
              <td>
                <div class="bonus-table-actions">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary bonus-penalty-event-detail-btn"
                    data-scope="<?php echo html_escape($eventScope === 'TEAM' ? 'Tim' : 'Personal'); ?>"
                    data-target-name="<?php echo html_escape($targetName); ?>"
                    data-penalty-date="<?php echo html_escape((string)($row['first_penalty_date'] ?? '-')); ?> s/d <?php echo html_escape((string)($row['last_penalty_date'] ?? '-')); ?>"
                    data-points="<?php echo number_format((float)($row['total_points_deducted'] ?? 0), 2, ',', '.'); ?>"
                    data-amount="Rp <?php echo number_format((float)($row['total_amount_deducted'] ?? 0), 2, ',', '.'); ?>"
                    data-detail-json="<?php echo html_escape(json_encode($detailPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
                  >Lihat detail</button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($penaltyEventPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'penalties'], $overrides)); }, 'penalty_event_page', 'penalty_event_per_page'); ?>
      </div></div>
    </div>
    <div class="tab-pane fade <?php echo $penaltyView === 'detail' ? 'show active' : ''; ?>" id="penaltyDetailPane">
      <div class="card bonus-card">
        <div class="card-body">
          <div class="bonus-toolbar">
            <div class="copy">
              <h5 class="mb-1">Rincian kejadian penalti per tanggal</h5>
              <div class="bonus-subcopy">Tab ini menampilkan semua baris penalti mentah urut tanggal. Paling cocok untuk audit, edit override, atau VOID kejadian tertentu.</div>
            </div>
          </div>
          <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <input type="hidden" name="tab" value="penalties">
            <input type="hidden" name="penalty_view" value="detail">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Cari nama / penalti</label>
                <input type="text" name="penalty_event_q" class="form-control" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>" placeholder="Cari pegawai, divisi, jenis penalti, kode, alasan, atau status">
              </div>
              <div class="col-md-2">
                <label class="form-label">Tanggal awal</label>
                <input type="date" name="penalty_event_date_start" class="form-control" value="<?php echo html_escape((string)($penaltyEventFilters['date_start'] ?? '')); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Tanggal akhir</label>
                <input type="date" name="penalty_event_date_end" class="form-control" value="<?php echo html_escape((string)($penaltyEventFilters['date_end'] ?? '')); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Baris</label>
                <select name="penalty_event_per_page" class="form-select">
                  <?php foreach ([10, 25, 50, 100] as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo (int)($penaltyEventPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
              </div>
              <div class="col-md-2 d-grid">
                <a href="<?php echo $buildTableUrl([
                  'tab' => 'penalties',
                  'penalty_view' => 'detail',
                  'penalty_event_q' => '',
                  'penalty_event_date_start' => $month . '-01',
                  'penalty_event_date_end' => date('Y-m-t', strtotime($month . '-01')),
                  'penalty_event_page' => 1,
                ]); ?>" class="btn btn-outline-secondary">Reset</a>
              </div>
            </div>
          </form>
          <div class="bonus-table-wrap bonus-table">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Target</th>
                  <th>Jenis</th>
                  <th>Scope</th>
                  <th>Shift</th>
                  <th class="text-end">Poin</th>
                  <th class="text-end">Nominal</th>
                  <th>Status</th>
                  <th>Catatan</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($penaltyDetailRows)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">Belum ada rincian penalti pada bulan ini.</td></tr>
              <?php else: foreach ($penaltyDetailRows as $row): ?>
                <?php $detailScope = strtoupper((string)($row['penalty_scope'] ?? 'PERSONAL')); ?>
                <tr>
                  <td><?php echo html_escape((string)($row['penalty_date'] ?? '-')); ?></td>
                  <td>
                    <strong><?php echo html_escape($detailScope === 'TEAM'
                        ? (trim((string)($row['employee_name'] ?? '')) !== '' ? (string)$row['employee_name'] : ((string)($row['division_name'] ?? 'Semua divisi')))
                        : ((string)($row['employee_name'] ?? '-'))); ?></strong>
                    <div class="small text-muted"><?php echo html_escape((string)($row['source_type'] ?? 'MANUAL')); ?></div>
                  </td>
                  <td>
                    <strong><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?></strong>
                    <div class="small text-muted"><?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?></div>
                  </td>
                  <td><?php echo html_escape($detailScope === 'TEAM' ? 'Tim' : 'Personal'); ?></td>
                  <td><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></td>
                  <td class="text-end"><?php echo number_format((float)($row['points_deducted'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-danger">Rp <?php echo number_format((float)($row['amount_deducted'] ?? 0), 2, ',', '.'); ?></td>
                  <td><span class="bonus-soft-badge <?php echo strtoupper((string)($row['status'] ?? 'DRAFT')) === 'APPROVED' ? 'ok' : (strtoupper((string)($row['status'] ?? 'DRAFT')) === 'VOID' ? 'muted' : 'warn'); ?>"><?php echo html_escape((string)($row['status'] ?? '-')); ?></span></td>
                  <td class="small text-muted"><?php echo html_escape((string)($row['reason_text'] ?? '-')); ?></td>
                  <td>
                    <div class="bonus-table-actions">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary bonus-penalty-event-edit-btn"
                        data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                        data-penalty-date="<?php echo html_escape((string)($row['penalty_date'] ?? '')); ?>"
                        data-penalty-type-id="<?php echo (int)($row['penalty_type_id'] ?? 0); ?>"
                        data-rule-id="<?php echo (int)($row['rule_id'] ?? 0); ?>"
                        data-penalty-scope="<?php echo html_escape((string)($row['penalty_scope'] ?? 'PERSONAL')); ?>"
                        data-employee-id="<?php echo (int)($row['employee_id'] ?? 0); ?>"
                        data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                        data-shift-id="<?php echo (int)($row['shift_id'] ?? 0); ?>"
                        data-points-deducted="<?php echo html_escape((string)($row['points_deducted'] ?? '0')); ?>"
                        data-amount-deducted="<?php echo html_escape((string)($row['amount_deducted'] ?? '0')); ?>"
                        data-status-value="<?php echo html_escape((string)($row['status'] ?? 'APPROVED')); ?>"
                        data-reason-text="<?php echo html_escape((string)($row['reason_text'] ?? '')); ?>"
                      >Edit</button>
                      <?php if (strtoupper((string)($row['status'] ?? '')) !== 'VOID'): ?>
                        <form method="post" action="<?php echo site_url('payroll/bonus/penalty-event-void/' . (int)($row['id'] ?? 0)); ?>" onsubmit="return confirm('VOID kejadian penalti ini?');">
                          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                          <input type="hidden" name="penalty_view" value="detail">
                          <input type="hidden" name="notes" value="VOID dari tab rincian penalti">
                          <button type="submit" class="btn btn-sm btn-outline-danger">VOID</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <?php $renderPager($penaltyEventPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'penalties', 'penalty_view' => 'detail'], $overrides)); }, 'penalty_event_page', 'penalty_event_per_page'); ?>
        </div>
      </div>
    </div>
  </div><?php elseif ($tab === 'peer'): ?>
  <div class="card bonus-card">
    <div class="card-header bg-white border-0 pb-0 d-flex flex-wrap justify-content-between gap-2 align-items-start">
      <div>
        <h5 class="mb-1">Penilaian 360 yang menunggu moderasi</h5>
        <div class="small text-muted">Hanya superadmin/moderator yang boleh membaca isi mentah penilaian ini. Hasilnya nanti bisa diterjemahkan menjadi koreksi poin bonus yang lebih adil.</div>
      </div>
      <span class="bonus-soft-badge warn"><?php echo number_format((int)($summary['pending_peer_review_count'] ?? 0)); ?> pending</span>
    </div>
    <div class="card-body">
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="peer">
        <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
        <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
        <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
        <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
        <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-7">
            <label class="form-label">Cari penilaian 360</label>
            <input type="text" name="peer_q" class="form-control" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>" placeholder="Cari penilai, pegawai, divisi, alasan, atau shift">
          </div>
          <div class="col-md-3">
            <label class="form-label">Baris</label>
            <select name="peer_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($peerPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead><tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th class="text-center">Bintang</th><th>Alasan</th><th>Shift</th><th>Moderasi</th></tr></thead>
          <tbody>
          <?php if (empty($pendingPeerRows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada peer review yang menunggu moderasi.</td></tr>
          <?php else: foreach ($pendingPeerRows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['feedback_date'] ?? '-')); ?></td>
              <td><strong><?php echo html_escape((string)($row['from_employee_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['from_employee_code'] ?? '')); ?></div></td>
              <td><strong><?php echo html_escape((string)($row['to_employee_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['division_name'] ?? '')); ?></div></td>
              <td class="text-center fw-semibold"><?php echo str_repeat('*', (int)($row['star_rating'] ?? 0)); ?></td>
              <td><?php echo html_escape((string)($row['reason_text'] ?? '-')); ?></td>
              <td><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></td>
              <td style="min-width:280px;">
                <form method="post" action="<?php echo site_url('payroll/bonus/peer-moderate/' . (int)($row['id'] ?? 0)); ?>" class="d-grid gap-2">
                  <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                  <input type="text" name="moderation_notes" class="form-control form-control-sm" placeholder="Catatan moderator, opsional">
                  <div class="d-flex gap-2">
                    <button type="submit" name="decision" value="APPROVED" class="btn btn-sm btn-success">Setujui</button>
                    <button type="submit" name="decision" value="REJECTED" class="btn btn-sm btn-outline-danger">Tolak</button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($peerPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'peer'], $overrides)); }, 'peer_page', 'peer_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'service'): ?>
  <div class="card bonus-card">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="mb-1">Metric layanan harian</h5>
      <div class="small text-muted">Di sini kita lihat kualitas layanan per hari, per outlet, dan per shift. Data ini dipakai untuk penalti layanan otomatis dan audit bonus operasional.</div>
    </div>
    <div class="card-body">
      <div class="bonus-toolbar mb-3">
        <div class="copy">
          <h6 class="mb-1">Sumber data metric layanan</h6>
          <div class="bonus-subcopy">Metric ini dibentuk dari order POS yang punya jejak `ordered_at`, `served_at`, dan status kitchen `SERVED`. Kalau bulan ini kosong, biasanya metric bulan itu belum digenerate atau data saji di POS belum lengkap.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <form method="post" action="<?php echo site_url('payroll/bonus/service-metric-generate-month'); ?>">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <button type="submit" class="btn btn-primary">Generate Metric 1 Bulan</button>
          </form>
        </div>
      </div>
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="service">
        <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
        <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
        <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
        <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
        <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
        <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
        <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="monthly_q" value="<?php echo html_escape((string)($monthlyFilters['q'] ?? '')); ?>">
        <input type="hidden" name="monthly_page" value="<?php echo (int)($monthlyPg['page'] ?? 1); ?>">
        <input type="hidden" name="monthly_per_page" value="<?php echo (int)($monthlyPg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label">Cari metric</label>
            <input type="text" name="service_q" class="form-control" value="<?php echo html_escape((string)($serviceFilters['q'] ?? '')); ?>" placeholder="Cari outlet, divisi, shift, atau catatan sumber">
          </div>
          <div class="col-md-2">
            <label class="form-label">Baris</label>
            <select name="service_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($servicePg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead>
          <tr>
            <th>Tanggal</th>
            <th>Scope</th>
            <th class="text-end">Order</th>
            <th class="text-end">Tersaji</th>
            <th class="text-end">On Time</th>
            <th class="text-end">Lewat SLA</th>
            <th class="text-end">Rata2 Menit</th>
            <th class="text-end">Skor</th>
            <th>Catatan</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($serviceMetricRows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada metric layanan untuk bulan ini. Klik <strong>Generate Metric 1 Bulan</strong> di atas. Jika sesudah itu tetap kosong, berarti order di bulan ini belum punya data saji yang cukup untuk dihitung.</td></tr>
          <?php else: foreach ($serviceMetricRows as $row): ?>
            <?php
              $scopeBits = [];
              $scopeBits[] = !empty($row['outlet_name']) ? (string)$row['outlet_name'] : 'Semua outlet';
              if (!empty($row['division_name'])) {
                  $scopeBits[] = (string)$row['division_name'];
              }
              if (!empty($row['shift_code']) || !empty($row['shift_name'])) {
                  $scopeBits[] = trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')));
              } else {
                  $scopeBits[] = 'Gabungan shift';
              }
              $serviceScore = (float)($row['score_percent'] ?? 0);
            ?>
            <tr>
              <td><?php echo html_escape((string)($row['metric_date'] ?? '-')); ?></td>
              <td><strong><?php echo html_escape(implode(' / ', $scopeBits)); ?></strong></td>
              <td class="text-end"><?php echo number_format((int)($row['total_orders'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format((int)($row['served_orders'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format((int)($row['on_time_orders'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format(max(0, (int)($row['served_orders'] ?? 0) - (int)($row['on_time_orders'] ?? 0))); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['avg_service_minutes'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end">
                <span class="bonus-soft-badge <?php echo $serviceScore >= 90 ? 'ok' : ($serviceScore >= 75 ? 'warn' : 'muted'); ?>">
                  <?php echo number_format($serviceScore, 2, ',', '.'); ?>%
                </span>
              </td>
              <td class="small text-muted"><?php echo html_escape((string)($row['source_notes'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($servicePg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'service'], $overrides)); }, 'service_page', 'service_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'monthly'): ?>
  <div class="card bonus-card">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="mb-1">Rekap bonus bulanan per pegawai</h5>
      <div class="small text-muted">Ringkasan ini menggabungkan bonus dan penalti per pegawai untuk bulan terpilih. Draft tetap tampil supaya posisi bulan berjalan bisa dibaca sebelum publish.</div>
    </div>
    <div class="card-body">
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="monthly">
        <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
        <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
        <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
        <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
        <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
        <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
        <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="service_q" value="<?php echo html_escape((string)($serviceFilters['q'] ?? '')); ?>">
        <input type="hidden" name="service_page" value="<?php echo (int)($servicePg['page'] ?? 1); ?>">
        <input type="hidden" name="service_per_page" value="<?php echo (int)($servicePg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label">Cari pegawai / kebijakan bonus</label>
            <input type="text" name="monthly_q" class="form-control" value="<?php echo html_escape((string)($monthlyFilters['q'] ?? '')); ?>" placeholder="Cari nama pegawai, kebijakan bonus, outlet, divisi, atau status">
          </div>
          <div class="col-md-2">
            <label class="form-label">Baris</label>
            <select name="monthly_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($monthlyPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead>
          <tr>
            <th>Pegawai</th>
            <th>Kebijakan Bonus</th>
            <th>Scope</th>
            <th class="text-end">Poin Final</th>
            <th class="text-end">Bonus Kotor</th>
            <th class="text-end">Penalti</th>
            <th class="text-end">Bonus Bersih</th>
            <th class="text-center">Telat / Alpha / PH</th>
            <th class="text-end">Peer</th>
            <th class="text-end">Layanan</th>
            <th class="text-end">Target</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($monthlySummaryRows)): ?>
            <tr><td colspan="13" class="text-center text-muted py-4">Belum ada rekap bonus bulanan untuk bulan ini.</td></tr>
          <?php else: foreach ($monthlySummaryRows as $row): ?>
            <?php
              $scopeLabel = trim((string)(($row['outlet_name'] ?? '') . ' ' . (!empty($row['division_name']) ? '/ ' . $row['division_name'] : '')));
              if ($scopeLabel === '') {
                  $scopeLabel = 'Global';
              }
              $status = strtoupper((string)($row['monthly_status'] ?? $row['payout_status'] ?? 'DRAFT'));
            ?>
            <tr>
              <td>
                <strong><?php echo html_escape((string)($row['employee_name'] ?? '-')); ?></strong>
                <div class="small text-muted"><?php echo html_escape((string)($row['employee_code'] ?? '')); ?></div>
              </td>
              <td>
                <strong><?php echo html_escape((string)($row['config_name'] ?? '-')); ?></strong>
                <div class="small text-muted"><?php echo html_escape((string)($row['rule_name'] ?? 'Tanpa rule spesifik')); ?></div>
              </td>
              <td class="small text-muted"><?php echo html_escape($scopeLabel); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($row['total_final_point'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end">Rp <?php echo number_format((float)($row['total_raw_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end text-danger">Rp <?php echo number_format((float)($row['total_penalty_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold">Rp <?php echo number_format((float)($row['total_final_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-center small">
                <?php echo number_format((int)($row['late_count'] ?? 0)); ?>
                /
                <?php echo number_format((int)($row['alpha_count'] ?? 0)); ?>
                /
                <?php echo number_format((int)($row['ph_taken_count'] ?? 0)); ?>
              </td>
              <td class="text-end"><?php echo number_format((float)($row['peer_avg_star'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['service_avg_score'] ?? 0), 2, ',', '.'); ?>%</td>
              <td class="text-end"><?php echo number_format((float)($row['target_avg_score'] ?? 0), 2, ',', '.'); ?>%</td>
              <td>
                <span class="bonus-soft-badge <?php echo in_array($status, ['POSTED', 'APPROVED'], true) ? 'ok' : ($status === 'MIXED' ? 'info' : ($status === 'VOID' ? 'muted' : 'warn')); ?>">
                  <?php echo html_escape($status); ?>
                </span>
              </td>
              <td>
                <a href="<?php echo site_url('payroll/bonus/monthly-detail/' . (int)($row['config_id'] ?? 0) . '/' . (int)($row['employee_id'] ?? 0) . '?month=' . rawurlencode($month)); ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($monthlyPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'monthly'], $overrides)); }, 'monthly_page', 'monthly_per_page'); ?>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card bonus-card h-100">
        <div class="card-header bg-white border-0 pb-0"><h5 class="mb-1">Cara baca modul bonus</h5></div>
        <div class="card-body small text-muted">
          <ol class="mb-0 ps-3 bonus-guide-list">
            <li>Target keuangan disusun di modul target, bukan di sini.</li>
            <li>Bonus membaca target sebagai syarat kelulusan atau bobot tambahan.</li>
            <li>Pool bonus harian membaca omzet shift, kualitas layanan, dan penalti.</li>
            <li>Rekap bulanan baru didorong ke payroll setelah disetujui.</li>
          </ol>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card bonus-card h-100">
        <div class="card-header bg-white border-0 pb-0"><h5 class="mb-1">Prinsip penting</h5></div>
        <div class="card-body small text-muted">
          <ul class="mb-0 ps-3 bonus-guide-list">
            <li>Libur biasa berbeda dengan PH.</li>
            <li>PH bisa diperlakukan sebagai tidak dapat bonus atau potong poin, tergantung rule.</li>
            <li>Penilaian 360 hanya sinyal moderasi, bukan hukuman otomatis.</li>
            <li>Waktu penyajian harus ikut menentukan kualitas bonus operasional.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="modal fade bonus-modal" id="bonusPoolDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Detail Pool Bonus Harian</h5>
          <div class="small text-muted">Ringkasan hari bonus yang sudah diinput.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><div class="bonus-detail-grid" id="bonusPoolDetailBody"></div></div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusConfigDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Detail Kebijakan Bonus</h5>
          <div class="small text-muted">Ringkasan hubungan target keuangan dan bonus yang dibuka.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="bonus-detail-grid" id="bonusConfigDetailBody"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusConfigModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="bonusConfigModalTitle">Form Kebijakan Bonus</h5>
          <div class="small text-muted">Isi sekali di sini. Sistem akan menyiapkan setting bonus sekaligus rule teknis di belakang layar.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/config-save'); ?>" id="bonusConfigForm">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <div class="bonus-form-scroll">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Nama kebijakan bonus</label>
                <input type="text" name="config_name" class="form-control" placeholder="Contoh: Bonus Operasional Default" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Kode kebijakan</label>
                <input type="text" name="config_code" class="form-control" placeholder="Kosongkan agar dibuat otomatis">
              </div>
              <div class="col-md-4">
                <label class="form-label">Scope kebijakan</label>
                <select name="distribution_scope" class="form-select">
                  <option value="GLOBAL">Global</option>
                  <option value="OUTLET">Per outlet</option>
                  <option value="DIVISION">Per divisi</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Sumber pool default</label>
                <select name="pool_source_mode" class="form-select">
                  <option value="TARGET_LINKED">Ikut target keuangan</option>
                  <option value="FIXED">Pool tetap</option>
                  <option value="PERCENT_REVENUE">% omzet</option>
                  <option value="PERCENT_PROFIT">% profit</option>
                  <option value="MANUAL">Manual</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Nilai sumber pool</label>
                <input type="number" step="0.0001" name="pool_source_value" class="form-control" value="0">
                <small class="text-muted d-block mt-1">Jika sumber pool = ikut target keuangan, biarkan `0`.</small>
              </div>
              <div class="col-md-4">
                <label class="form-label">% bonus dari realisasi target</label>
                <input type="number" step="0.0001" name="payout_percent" class="form-control" value="3">
                <small class="text-muted d-block mt-1">Contoh isi `3` jika bonus dibuka 3% dari realisasi target yang lolos.</small>
              </div>
              <div class="col-md-4">
                <label class="form-label">Mode konversi penalti poin</label>
                <select name="point_penalty_currency_mode" class="form-select">
                  <option value="PERCENT_SHARE">Potong % dari jatah bonus</option>
                  <option value="FIXED_RUPIAH">Potong rupiah tetap per poin</option>
                  <option value="NONE">Tidak dikonversi ke rupiah</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Nilai konversi penalti poin</label>
                <input type="number" step="0.0001" name="point_penalty_currency_value" class="form-control" value="5">
              </div>
              <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="ACTIVE">Aktif</option>
                  <option value="DRAFT">Draft</option>
                  <option value="INACTIVE">Nonaktif</option>
                </select>
              </div>
              <input type="hidden" name="rule_name" value="">
              <input type="hidden" name="rule_code" value="">
              <div class="col-md-6">
                <label class="form-label">Outlet</label>
                <select name="outlet_id" class="form-select">
                  <option value="">Semua outlet</option>
                  <?php foreach ($outletRows as $row): ?>
                    <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Divisi</label>
                <select name="division_id" class="form-select">
                  <option value="">Semua divisi</option>
                  <?php foreach ($divisionRows as $row): ?>
                    <option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Gerbang target bulanan</label>
                <select name="linked_target_plan_id" class="form-select">
                  <option value="">Belum ditautkan</option>
                  <?php foreach ($monthlyTargetPlanRows as $row): ?>
                    <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['target_name'] ?? $row['plan_name'] ?? '-')); ?><?php echo !empty($row['status']) ? (' [' . html_escape((string)$row['status']) . ']') : ''; ?></option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted d-block mt-1">Pilih target bulanan yang menjadi syarat bonus boleh cair.</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Pembuka target harian</label>
                <input type="hidden" name="daily_target_plan_id" value="">
                <div class="form-control bg-light">Otomatis mengikuti target DAILY aktif sesuai tanggal generate</div>
                <small class="text-muted d-block mt-1">Tidak perlu pilih target harian satu per satu. Saat generate pool, sistem mencari target DAILY yang aktif pada tanggal itu.</small>
              </div>
              <input type="hidden" name="target_gate_mode" value="WEIGHTED_SCORE">
              <input type="hidden" name="min_target_score" value="100">
              <input type="hidden" name="threshold_amount" value="0">
              <input type="hidden" name="pool_formula_type" value="PERCENTAGE">
              <input type="hidden" name="pool_formula_value" value="0">
              <input type="hidden" name="min_shift_base_pct" value="0">
              <input type="hidden" name="ph_bonus_mode" value="EXCLUDE">
              <input type="hidden" name="ph_point_deduction" value="0">
              <input type="hidden" name="service_time_target_minute" value="0">
              <input type="hidden" name="service_time_weight" value="0">
              <input type="hidden" name="shift_revenue_weight" value="1">
              <input type="hidden" name="peer_review_weight" value="0">
              <input type="hidden" name="attendance_weight" value="1">
              <input type="hidden" name="manual_penalty_weight" value="1">
              <div class="col-12">
                <label class="form-label">Deskripsi singkat</label>
                <input type="text" name="description" class="form-control" placeholder="Opsional, jelaskan kapan kebijakan ini dipakai">
              </div>
              <input type="hidden" name="linked_target_required" value="1">
              <input type="hidden" name="include_shift_revenue_factor" value="1">
              <input type="hidden" name="include_service_time_factor" value="1">
              <input type="hidden" name="include_peer_review_factor" value="1">
              <input type="hidden" name="include_attendance_factor" value="1">
              <input type="hidden" name="include_manual_penalty_factor" value="1">
              <div class="col-12">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Opsional"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer px-0 pb-0 mt-4">
            <button type="button" class="btn btn-outline-secondary" id="bonusConfigResetBtn">Reset</button>
            <button type="submit" class="btn btn-primary">Simpan Kebijakan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusWeightDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Detail Bobot Bonus</h5>
          <div class="small text-muted">Baca pembobotan tanpa mengubah datanya.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><div class="bonus-detail-grid" id="bonusWeightDetailBody"></div></div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusPenaltyDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Detail Master Penalti</h5>
          <div class="small text-muted">Penjelasan lengkap penalti yang sudah disimpan.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><div class="bonus-detail-grid" id="bonusPenaltyDetailBody"></div></div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusPenaltyEventDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Detail Kejadian Penalti</h5>
          <div class="small text-muted">Ringkasan penalti dibuat lebih mudah dibaca: informasi utama di atas, lalu rincian kejadian lengkap di bawah.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><div id="bonusPenaltyEventDetailBody"></div></div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusPenaltyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="bonusPenaltyModalTitle">Form Master Penalti</h5>
          <div class="small text-muted">Bedakan penalti otomatis, semi manual, dan manual per kejadian.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/penalty-save'); ?>" id="bonusPenaltyForm">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <div class="bonus-form-scroll">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Nama penalti</label><input type="text" name="penalty_name" class="form-control" placeholder="Contoh: Area kitchen kotor"></div>
              <div class="col-md-6"><label class="form-label">Kode penalti</label><input type="text" name="penalty_code" class="form-control" placeholder="Kosongkan agar dibuat otomatis"></div>
              <div class="col-md-4"><label class="form-label">Kategori</label><select name="category" class="form-select"><option value="OTHER">Lainnya</option><option value="ATTENDANCE">Absensi</option><option value="SOCIAL_MEDIA">Sosial media</option><option value="DISCIPLINE">Disiplin</option><option value="PERFORMANCE">Kinerja</option><option value="SERVICE">Layanan</option><option value="PROPERTY">Aset / kerusakan</option><option value="HYGIENE">Kebersihan</option></select></div>
              <div class="col-md-4"><label class="form-label">Berlaku untuk</label><select name="applies_scope" class="form-select"><option value="BOTH">Personal dan tim</option><option value="PERSONAL">Personal saja</option><option value="TEAM">Tim saja</option></select></div>
              <div class="col-md-4"><label class="form-label">Urutan tampil</label><input type="number" name="sort_order" class="form-control" value="0"></div>
              <div class="col-md-4"><label class="form-label">Cara kerja penalti</label><select name="behavior_mode" id="penaltyBehaviorMode" class="form-select"><option value="AUTO">Otomatis dari sistem</option><option value="SEMI_MANUAL">Semi manual / verifikasi</option><option value="MANUAL">Manual per kejadian</option></select></div>
              <div class="col-md-4"><label class="form-label">Sumber otomatis / verifikasi</label><select name="auto_source" id="penaltyAutoSource" class="form-select"><option value="">Tidak otomatis</option><option value="ATTENDANCE">Absensi</option><option value="SERVICE">Layanan</option><option value="TARGET">Target</option><option value="PEER">Penilaian 360</option><option value="SOCIAL_MEDIA">Sosial media</option><option value="AUDIT">Audit</option><option value="CHECKLIST">Checklist</option><option value="OTHER">Lainnya</option></select></div>
              <div class="col-md-4"><label class="form-label">Cara potong</label><select name="deduction_mode" class="form-select"><option value="FIXED_POINT">Potong poin tetap</option><option value="FIXED_AMOUNT">Potong nominal tetap</option><option value="VARIABLE">Variabel / fleksibel</option></select></div>
              <div class="col-md-6"><label class="form-label">Trigger absensi</label><input type="text" name="attendance_trigger" class="form-control" placeholder="Contoh: LATE_MINOR, ALPHA, ABSENT_PH"></div>
              <div class="col-md-6"><label class="form-label">Siklus verifikasi</label><select name="verification_cycle" class="form-select"><option value="PER_EVENT">Per kejadian</option><option value="DAILY">Harian</option><option value="MONTHLY">Bulanan</option><option value="UNTIL_CHANGED">Sampai ada perubahan</option></select></div>
              <div class="col-md-6"><label class="form-label">Potong poin default</label><input type="number" step="0.01" name="default_points_deducted" class="form-control" value="0"></div>
              <div class="col-md-6"><label class="form-label">Potong nominal default</label><input type="number" step="0.01" name="default_amount_deducted" class="form-control" value="0"></div>
              <div class="col-12">
                <div class="bonus-penalty-parameter-box">
                  <span class="label">Parameter otomatis</span>
                  <p>Jika sumber = <strong>Layanan</strong>, isi batas menit dan kenaikan poin keterlambatan. Jika sumber = <strong>Penilaian 360</strong>, isi potongan poin untuk 4, 3, 2, dan 1 bintang. Field ini dipakai engine saat sync penalti otomatis.</p>
                </div>
              </div>
              <div class="col-md-6 bonus-penalty-parameter bonus-penalty-parameter-service"><label class="form-label">Batas waktu saji (menit)</label><input type="number" step="0.01" name="service_target_minute" class="form-control" value="15"></div>
              <div class="col-md-6 bonus-penalty-parameter bonus-penalty-parameter-service"><label class="form-label">Tambahan poin tiap keterlambatan (menit)</label><input type="number" step="0.01" name="service_step_minute" class="form-control" value="5"></div>
              <div class="col-md-3 bonus-penalty-parameter bonus-penalty-parameter-peer"><label class="form-label">Potong poin rating 4</label><input type="number" step="0.01" name="peer_star_4_points" class="form-control" value="1"></div>
              <div class="col-md-3 bonus-penalty-parameter bonus-penalty-parameter-peer"><label class="form-label">Potong poin rating 3</label><input type="number" step="0.01" name="peer_star_3_points" class="form-control" value="2"></div>
              <div class="col-md-3 bonus-penalty-parameter bonus-penalty-parameter-peer"><label class="form-label">Potong poin rating 2</label><input type="number" step="0.01" name="peer_star_2_points" class="form-control" value="3"></div>
              <div class="col-md-3 bonus-penalty-parameter bonus-penalty-parameter-peer"><label class="form-label">Potong poin rating 1</label><input type="number" step="0.01" name="peer_star_1_points" class="form-control" value="4"></div>
              <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2" placeholder="Misalnya untuk audit pagi, kewajiban sosial media, atau kejadian khusus."></textarea></div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3">
              <div class="form-check"><input class="form-check-input" type="checkbox" id="penaltyManualOnly" name="is_manual_only" value="1"><label class="form-check-label" for="penaltyManualOnly">Paksa manual saja</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="penaltyApprovalRequired" name="approval_required" value="1" checked><label class="form-check-label" for="penaltyApprovalRequired">Perlu persetujuan</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="penaltyRequiresEvidence" name="requires_evidence" value="1"><label class="form-check-label" for="penaltyRequiresEvidence">Butuh bukti / lampiran</label></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="penaltyIsActive" name="is_active" value="1" checked><label class="form-check-label" for="penaltyIsActive">Aktif</label></div>
            </div>
          </div>
          <div class="modal-footer px-0 pb-0 mt-4">
            <button type="button" class="btn btn-outline-secondary" id="bonusPenaltyResetBtn">Reset Form</button>
            <button type="submit" class="btn btn-primary">Simpan Penalti</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusWeightModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="bonusWeightModalTitle">Form Bobot Bonus Global</h5>
          <div class="small text-muted">Bobot ini berlaku lintas target. Gunakan untuk membedakan porsi divisi, jabatan, pegawai, atau shift tanpa harus buat rule per target.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/weight-save'); ?>" id="bonusWeightForm">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <div class="bonus-form-scroll">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Keterikatan</label>
                <div class="form-control bg-light">Global untuk semua kebijakan bonus</div>
                <input type="hidden" name="rule_id" value="">
                <div class="small text-muted mt-1">Bobot dasar pembagian pegawai tidak lagi dikunci ke kebijakan tertentu.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Berlaku untuk target</label>
                <select name="target_frequency" class="form-select">
                  <option value="ALL">Semua target</option>
                  <option value="DAILY">Hanya target harian</option>
                  <option value="MONTHLY">Hanya target bulanan</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Jenis bobot</label>
                <select name="weight_scope" id="bonusWeightScope" class="form-select">
                  <option value="DIVISION">Divisi</option>
                  <option value="POSITION">Jabatan</option>
                  <option value="EMPLOYEE">Pegawai</option>
                  <option value="SHIFT">Shift</option>
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label">Target bobot</label>
                <select name="scope_id" id="bonusWeightScopeId" class="form-select" required></select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Bobot poin</label>
                <input type="number" step="0.0001" name="point_weight" class="form-control" value="1">
                <div class="small text-muted mt-1">Ini angka utama pembagi bonus. Semakin besar bobot, semakin besar porsi pegawai/divisi/jabatan/shift itu saat pool dibagi.</div>
              </div>
              <input type="hidden" name="pool_weight" value="1">
              <div class="col-12">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Misalnya supervisor x1.2, shift malam x1.1, atau pegawai senior x1.3."></textarea>
              </div>
            </div>
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" role="switch" id="weightIsActive" name="is_active" value="1" checked>
              <label class="form-check-label" for="weightIsActive">Bobot aktif</label>
            </div>
          </div>
          <div class="modal-footer px-0 pb-0 mt-4">
            <button type="button" class="btn btn-outline-secondary" id="bonusWeightResetBtn">Reset Form</button>
            <button type="submit" class="btn btn-primary">Simpan Bobot</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusPenaltyEventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="bonusPenaltyEventModalTitle">Form Kejadian Penalti</h5>
          <div class="small text-muted">Dipakai untuk kejadian lapangan yang belum otomatis atau butuh verifikasi admin.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/penalty-event-save'); ?>" id="bonusPenaltyEventForm">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
          <input type="hidden" name="penalty_view" value="<?php echo html_escape($penaltyView); ?>">
          <div class="bonus-form-scroll">
            <div class="row g-3">
              <div class="col-md-3"><label class="form-label">Tanggal kejadian</label><input type="date" name="penalty_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
              <div class="col-md-3"><label class="form-label">Jenis penalti</label><select name="penalty_type_id" id="penaltyTypeEventSelect" class="form-select" required><option value="">Pilih jenis penalti...</option><option value="-1" data-points="0" data-amount="0" data-scope="BOTH" data-behavior="MANUAL">Penalti custom superadmin</option><?php foreach ($penaltyTypeRows as $row): ?><?php if (!$isPenaltySelectableForEvent($row)) { continue; } ?><option value="<?php echo (int)($row['id'] ?? 0); ?>" data-points="<?php echo html_escape((string)($row['default_points_deducted'] ?? '0')); ?>" data-amount="<?php echo html_escape((string)($row['default_amount_deducted'] ?? '0')); ?>" data-scope="<?php echo html_escape((string)($row['applies_scope'] ?? 'BOTH')); ?>" data-behavior="<?php echo html_escape((string)($row['behavior_mode'] ?? 'MANUAL')); ?>"><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?> - <?php echo html_escape($penaltyBehaviorLabels[strtoupper((string)($row['behavior_mode'] ?? 'MANUAL'))] ?? 'Manual'); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label">Kebijakan/aturan terkait</label><select name="rule_id" class="form-select"><option value="">Tanpa aturan spesifik</option><?php foreach ($bonusRuleOptionRows as $row): ?><option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['rule_name'] ?? '-')); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label">Scope penalti</label><select name="penalty_scope" id="penaltyScopeSelect" class="form-select"><option value="PERSONAL">Personal</option><option value="TEAM">Tim</option></select></div>
              <div class="col-md-3" id="penaltyCustomNameWrap" style="display:none;"><label class="form-label">Nama penalti custom</label><input type="text" name="custom_penalty_name" id="penaltyCustomNameInput" class="form-control" placeholder="Contoh: Teguran custom supervisor"></div>
              <div class="col-md-4"><label class="form-label">Pegawai</label><select name="employee_id" id="penaltyEmployeeSelect" class="form-select"><option value="">Pilih pegawai...</option><?php foreach ($employeeRows as $row): ?><option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-4"><label class="form-label">Divisi tim</label><select name="division_id" id="penaltyDivisionSelect" class="form-select"><option value="">Pilih divisi...</option><option value="0">Semua divisi tim</option><?php foreach ($divisionRows as $row): ?><option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-4"><label class="form-label">Shift terkait</label><select name="shift_id" class="form-select"><option value="">Opsional</option><?php foreach ($shiftRows as $row): ?><option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label">Potong poin</label><input type="number" step="0.01" name="points_deducted" id="penaltyPointsInput" class="form-control" placeholder="Default dari master"></div>
              <div class="col-md-3"><label class="form-label">Potong nominal otomatis</label><input type="text" id="penaltyAmountInput" class="form-control" placeholder="Dihitung otomatis dari poin dan kebijakan bonus" readonly></div>
              <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="APPROVED">Langsung disetujui</option><option value="DRAFT">Simpan draft dulu</option></select></div>
              <div class="col-md-3"><label class="form-label">Catatan cepat</label><input type="text" class="form-control" value="Nominal akan dihitung sistem sesuai konversi poin kebijakan bonus." disabled></div>
              <div class="col-12"><label class="form-label">Alasan kejadian</label><textarea name="reason_text" class="form-control" rows="2" placeholder="Contoh: Audit pagi menemukan station kitchen shift malam belum bersih."></textarea></div>
            </div>
          </div>
          <div class="modal-footer px-0 pb-0 mt-4">
            <button type="submit" class="btn btn-primary" id="bonusPenaltyEventSubmitBtn">Simpan Kejadian Penalti</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusSyncPenaltyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Sinkron Penalti Otomatis</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/sync-auto-penalties'); ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Tanggal mulai</label>
              <input type="date" name="bonus_date_start" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal akhir</label>
              <input type="date" name="bonus_date_end" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Kebijakan bonus untuk konversi nominal</label>
              <select name="config_id" class="form-select">
                <option value="">Pakai kebijakan default aktif</option>
                <?php foreach ($configRows as $row): ?>
                  <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['config_name'] ?? '-')); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="small text-muted mt-1">Kalau dipilih, nominal penalti otomatis akan ikut nilai konversi poin dari kebijakan ini.</div>
            </div>
          </div>
          <div class="small text-muted mt-2">Ini menarik penalti otomatis dari absensi, waktu saji layanan, dan peer review yang sudah dimoderasi. Tanggal masa depan otomatis dilewati.</div>
          <div class="modal-footer px-0 pb-0 mt-4"><button type="submit" class="btn btn-outline-primary">Sync Penalti Auto</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusGeneratePoolModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="bonusGeneratePoolModalTitle">Generate Pool Bonus Harian</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/generate-pool'); ?>">
          <div class="row g-3">
            <div class="col-12">
              <div class="bonus-empty-note mb-0">
                <strong>Mode Satuan</strong>: isi tanggal mulai dan akhir dengan tanggal yang sama.
                <br>
                <strong>Mode Bulk dari Target Harian</strong>: centang beberapa target DAILY aktif di bawah, lalu sistem akan generate sekaligus untuk semua tanggal yang dipilih.
              </div>
            </div>
            <div class="col-md-6"><label class="form-label">Tanggal mulai</label><input type="date" name="bonus_date_start" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>"></div>
            <div class="col-md-6"><label class="form-label">Tanggal akhir</label><input type="date" name="bonus_date_end" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>"></div>
            <div class="col-md-12">
              <label class="form-label">Pilih target harian untuk mode bulk</label>
              <div class="form-control bg-light mb-2">Centang target DAILY aktif yang ingin dihitung. Jika tidak ada yang dicentang, sistem akan memakai tanggal manual di atas.</div>
              <div class="bonus-table-wrap bonus-table" style="max-height:240px;">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr><th style="width:48px;"><input type="checkbox" class="form-check-input" id="bonusGenerateSelectAllTargets"></th><th>Tanggal</th><th>Target Harian</th></tr>
                  </thead>
                  <tbody>
                    <?php if (empty($activeDailyTargetRows)): ?>
                      <tr><td colspan="3" class="text-center text-muted py-3">Belum ada target DAILY aktif di bulan ini.</td></tr>
                    <?php else: ?>
                      <?php foreach ($activeDailyTargetRows as $row): ?>
                        <tr>
                          <td><input type="checkbox" class="form-check-input" name="target_plan_ids[]" value="<?php echo (int)($row['id'] ?? 0); ?>"></td>
                          <td><?php echo html_escape((string)($row['date_start'] ?? '-')); ?></td>
                          <td><strong><?php echo html_escape((string)($row['target_name'] ?? '-')); ?></strong></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <div class="small text-muted mt-1">Pola bulk ini lebih aman karena generator membaca target DAILY yang benar-benar aktif di target keuangan.</div>
            </div>
            <div class="col-md-12">
              <label class="form-label">Kebijakan bonus</label>
              <select name="config_id" class="form-select" required>
                <option value="">Pilih kebijakan bonus...</option>
                <?php foreach ($configRows as $row): ?>
                  <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['config_name'] ?? '-')); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="small text-muted mt-1">Generator akan otomatis membaca rule teknis utama yang terpasang di kebijakan ini. Jadi user cukup memilih kebijakan bonus, bukan rule teknisnya.</div>
            </div>
          </div>
          <div class="small text-muted mt-3">Saat draft pool dibuat, sistem otomatis menarik penalti absensi, layanan, dan peer review yang sudah lolos moderasi; membaca omzet shift; membagi bonus berdasarkan bobot global; lalu menyimpan estimasi bonus pegawai tanpa mengubah kas. Kas baru bergerak saat nanti dibuat pencairan bonus.</div>
          <div class="modal-footer px-0 pb-0 mt-4"><button type="submit" class="btn btn-primary">Generate Draft</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusServiceMetricModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Bangun Metric Waktu Saji</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/generate-service-metric'); ?>">
          <div class="mb-3"><label class="form-label">Tanggal metric</label><input type="date" name="metric_date" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>"></div>
          <div class="mb-3"><label class="form-label">Outlet</label><select name="outlet_id" class="form-select"><option value="0">Semua outlet</option><?php foreach ($outletRows as $row): ?><option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></option><?php endforeach; ?></select></div>
          <div class="small text-muted">Sumbernya dari <code>ordered_at - served_at</code> order POS, lalu diringkas per shift dan outlet sebagai dasar faktor layanan bonus.</div>
          <div class="modal-footer px-0 pb-0 mt-4"><button type="submit" class="btn btn-outline-dark">Bangun Metric</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade bonus-modal" id="bonusMonthlySummaryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Refresh Rekap Bonus Bulanan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="<?php echo site_url('payroll/bonus/generate-monthly-summary'); ?>">
          <div class="mb-3"><label class="form-label">Bulan rekap</label><input type="month" name="summary_month" class="form-control" value="<?php echo html_escape($month); ?>"></div>
          <div class="small text-muted">Rekap bulanan dibangun dari bonus harian yang sudah dipublikasikan, termasuk hitung keterlambatan, alpha, PH, peer review, dan penyesuaian manual.</div>
          <div class="modal-footer px-0 pb-0 mt-4"><button type="submit" class="btn btn-outline-success">Refresh Rekap</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    function modalInstance(id) {
      var el = document.getElementById(id);
      if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return null;
      }
      return bootstrap.Modal.getOrCreateInstance(el);
    }

    function openModal(id) {
      var instance = modalInstance(id);
      if (instance) {
        instance.show();
      }
    }

    function detailItem(label, value) {
      return '<div class="bonus-detail-item"><span class="label">' + label + '</span><div class="value">' + value + '</div></div>';
    }

    function detailValue(value, fallback) {
      var text = value == null ? '' : String(value).trim();
      if (text === '') {
        return fallback || '-';
      }
      return text;
    }

    function renderDetailGrid(targetId, rows) {
        var target = document.getElementById(targetId);
        if (!target) {
          return;
        }
      target.innerHTML = rows.map(function (row) {
        return detailItem(row.label, detailValue(row.value, row.fallback || '-'));
      }).join('');
    }

    var configForm = document.getElementById('bonusConfigForm');
    var configResetBtn = document.getElementById('bonusConfigResetBtn');
    var configModalTitle = document.getElementById('bonusConfigModalTitle');
    var weightForm = document.getElementById('bonusWeightForm');
    var weightResetBtn = document.getElementById('bonusWeightResetBtn');
    var weightModalTitle = document.getElementById('bonusWeightModalTitle');
    var penaltyForm = document.getElementById('bonusPenaltyForm');
    var penaltyResetBtn = document.getElementById('bonusPenaltyResetBtn');
    var penaltyModalTitle = document.getElementById('bonusPenaltyModalTitle');
    var penaltyEventForm = document.getElementById('bonusPenaltyEventForm');
    var penaltyEventModalTitle = document.getElementById('bonusPenaltyEventModalTitle');
    var penaltyEventSubmitBtn = document.getElementById('bonusPenaltyEventSubmitBtn');
    var weightScopeSelect = document.getElementById('bonusWeightScope');
    var weightScopeIdSelect = document.getElementById('bonusWeightScopeId');
    var bonusGenerateSelectAllTargets = document.getElementById('bonusGenerateSelectAllTargets');
    var bonusGeneratePoolModalTitle = document.getElementById('bonusGeneratePoolModalTitle');
    var bonusWeightScopeOptions = <?php echo json_encode($weightScopeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function fillValue(form, name, value) {
      if (!form) return;
      var field = form.querySelector('[name="' + name + '"]');
      if (!field) return;
      if (field.type === 'checkbox') {
        field.checked = String(value) === '1';
        return;
      }
      field.value = value == null ? '' : value;
    }

    function resetConfigForm() {
      if (!configForm) return;
      configForm.reset();
      fillValue(configForm, 'id', '');
      fillValue(configForm, 'distribution_scope', 'GLOBAL');
      fillValue(configForm, 'pool_source_mode', 'TARGET_LINKED');
      fillValue(configForm, 'pool_source_value', '0');
      fillValue(configForm, 'payout_percent', '3');
      fillValue(configForm, 'point_penalty_currency_mode', 'PERCENT_SHARE');
      fillValue(configForm, 'point_penalty_currency_value', '5');
      fillValue(configForm, 'status', 'ACTIVE');
      fillValue(configForm, 'rule_name', '');
      fillValue(configForm, 'rule_code', '');
      fillValue(configForm, 'outlet_id', '');
      fillValue(configForm, 'division_id', '');
      fillValue(configForm, 'linked_target_plan_id', '');
      fillValue(configForm, 'daily_target_plan_id', '');
      fillValue(configForm, 'target_gate_mode', 'WEIGHTED_SCORE');
      fillValue(configForm, 'min_target_score', '100');
      fillValue(configForm, 'threshold_amount', '0');
      fillValue(configForm, 'pool_formula_type', 'PERCENTAGE');
      fillValue(configForm, 'pool_formula_value', '0');
      fillValue(configForm, 'min_shift_base_pct', '0');
      fillValue(configForm, 'ph_bonus_mode', 'EXCLUDE');
      fillValue(configForm, 'ph_point_deduction', '0');
      fillValue(configForm, 'service_time_target_minute', '0');
      fillValue(configForm, 'service_time_weight', '0');
      fillValue(configForm, 'shift_revenue_weight', '1');
      fillValue(configForm, 'peer_review_weight', '0');
      fillValue(configForm, 'attendance_weight', '1');
      fillValue(configForm, 'manual_penalty_weight', '1');
      fillValue(configForm, 'linked_target_required', '1');
      fillValue(configForm, 'include_shift_revenue_factor', '1');
      fillValue(configForm, 'include_service_time_factor', '1');
      fillValue(configForm, 'include_peer_review_factor', '1');
      fillValue(configForm, 'include_attendance_factor', '1');
      fillValue(configForm, 'include_manual_penalty_factor', '1');
      if (configModalTitle) {
        configModalTitle.textContent = 'Form Kebijakan Bonus';
      }
    }

    function syncWeightScopeOptions(selectedScope, selectedId) {
        if (!weightScopeSelect || !weightScopeIdSelect) return;
        var scope = String(selectedScope || weightScopeSelect.value || 'DIVISION').toUpperCase();
        var selected = String(selectedId || '');
        var rows = Array.isArray(bonusWeightScopeOptions[scope]) ? bonusWeightScopeOptions[scope] : [];
        var html = rows.length
          ? rows.map(function (row) {
              var rowId = String(row.id || '');
              var isSelected = selected !== '' && rowId === selected ? ' selected' : '';
              return '<option value="' + rowId + '"' + isSelected + '>' + detailValue(row.label, '-') + '</option>';
            }).join('')
          : '<option value="">Belum ada data scope untuk pilihan ini</option>';
        weightScopeIdSelect.innerHTML = html;
        if (selected === '' && rows.length) {
          weightScopeIdSelect.value = String(rows[0].id || '');
        }
    }

    function resetWeightForm() {
        if (!weightForm) return;
        weightForm.reset();
        fillValue(weightForm, 'id', '');
        fillValue(weightForm, 'target_frequency', 'ALL');
        fillValue(weightForm, 'weight_scope', 'DIVISION');
        fillValue(weightForm, 'point_weight', '1');
        fillValue(weightForm, 'pool_weight', '1');
        fillValue(weightForm, 'is_active', '1');
        syncWeightScopeOptions('DIVISION', '');
        if (weightModalTitle) {
          weightModalTitle.textContent = 'Form Bobot Bonus Global';
        }
    }

    function resetPenaltyForm() {
      if (!penaltyForm) return;
      penaltyForm.reset();
      fillValue(penaltyForm, 'id', '');
      fillValue(penaltyForm, 'category', 'OTHER');
      fillValue(penaltyForm, 'applies_scope', 'BOTH');
      fillValue(penaltyForm, 'behavior_mode', 'MANUAL');
      fillValue(penaltyForm, 'deduction_mode', 'FIXED_POINT');
      fillValue(penaltyForm, 'verification_cycle', 'PER_EVENT');
      fillValue(penaltyForm, 'default_points_deducted', '0');
      fillValue(penaltyForm, 'default_amount_deducted', '0');
      fillValue(penaltyForm, 'service_target_minute', '15');
      fillValue(penaltyForm, 'service_step_minute', '5');
      fillValue(penaltyForm, 'peer_star_4_points', '1');
      fillValue(penaltyForm, 'peer_star_3_points', '2');
      fillValue(penaltyForm, 'peer_star_2_points', '3');
      fillValue(penaltyForm, 'peer_star_1_points', '4');
      fillValue(penaltyForm, 'sort_order', '0');
      fillValue(penaltyForm, 'approval_required', '1');
      fillValue(penaltyForm, 'is_active', '1');
      if (penaltyModalTitle) {
        penaltyModalTitle.textContent = 'Form Master Penalti';
      }
      if (typeof syncPenaltyBehaviorMode === 'function') {
        syncPenaltyBehaviorMode();
      }
    }

    function resetPenaltyEventForm() {
      if (!penaltyEventForm) return;
      penaltyEventForm.reset();
      fillValue(penaltyEventForm, 'id', '');
      fillValue(penaltyEventForm, 'penalty_date', '<?php echo date('Y-m-d'); ?>');
      fillValue(penaltyEventForm, 'status', 'APPROVED');
      if (penaltyEventModalTitle) {
        penaltyEventModalTitle.textContent = 'Form Kejadian Penalti';
      }
      if (penaltyEventSubmitBtn) {
        penaltyEventSubmitBtn.textContent = 'Simpan Kejadian Penalti';
      }
      if (typeof syncPenaltyEventDefaults === 'function') {
        syncPenaltyEventDefaults();
      }
    }

    if (configResetBtn && configForm) {
      configResetBtn.addEventListener('click', resetConfigForm);
    }

    var openConfigCreateModalBtn = document.getElementById('openConfigCreateModalBtn');
    if (openConfigCreateModalBtn) {
      openConfigCreateModalBtn.addEventListener('click', function () {
        resetConfigForm();
        if (configModalTitle) {
          configModalTitle.textContent = 'Tambah Kebijakan Bonus';
        }
        openModal('bonusConfigModal');
      });
    }

    document.querySelectorAll('.bonus-config-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        resetConfigForm();
        fillValue(configForm, 'id', btn.dataset.id || '');
        fillValue(configForm, 'config_name', btn.dataset.configName || '');
        fillValue(configForm, 'config_code', btn.dataset.configCode || '');
        fillValue(configForm, 'description', btn.dataset.description || '');
        fillValue(configForm, 'distribution_scope', btn.dataset.distributionScope || 'GLOBAL');
        fillValue(configForm, 'pool_source_mode', btn.dataset.poolSourceMode || 'TARGET_LINKED');
        fillValue(configForm, 'pool_source_value', btn.dataset.poolSourceValue || '0');
        fillValue(configForm, 'payout_percent', btn.dataset.payoutPercent || '3');
        fillValue(configForm, 'point_penalty_currency_mode', btn.dataset.pointPenaltyMode || 'PERCENT_SHARE');
        fillValue(configForm, 'point_penalty_currency_value', btn.dataset.pointPenaltyValue || '5');
        fillValue(configForm, 'rule_name', btn.dataset.ruleName || '');
        fillValue(configForm, 'rule_code', btn.dataset.ruleCode || '');
        fillValue(configForm, 'outlet_id', btn.dataset.outletId || '');
        fillValue(configForm, 'division_id', btn.dataset.divisionId || '');
        fillValue(configForm, 'linked_target_plan_id', btn.dataset.targetPlanId || '');
        fillValue(configForm, 'daily_target_plan_id', '');
        fillValue(configForm, 'target_gate_mode', btn.dataset.targetGateMode || 'WEIGHTED_SCORE');
        fillValue(configForm, 'min_target_score', btn.dataset.minTargetScore || '100');
        fillValue(configForm, 'threshold_amount', btn.dataset.thresholdAmount || '0');
        fillValue(configForm, 'pool_formula_type', btn.dataset.poolFormulaType || 'PERCENTAGE');
        fillValue(configForm, 'pool_formula_value', btn.dataset.poolFormulaValue || '0');
        fillValue(configForm, 'min_shift_base_pct', btn.dataset.minShiftBasePct || '0');
        fillValue(configForm, 'ph_bonus_mode', btn.dataset.phBonusMode || 'EXCLUDE');
        fillValue(configForm, 'ph_point_deduction', btn.dataset.phPointDeduction || '0');
        fillValue(configForm, 'service_time_target_minute', btn.dataset.serviceTimeTargetMinute || '0');
        fillValue(configForm, 'service_time_weight', btn.dataset.serviceTimeWeight || '0');
        fillValue(configForm, 'shift_revenue_weight', btn.dataset.shiftRevenueWeight || '1');
        fillValue(configForm, 'peer_review_weight', btn.dataset.peerReviewWeight || '0');
        fillValue(configForm, 'attendance_weight', btn.dataset.attendanceWeight || '1');
        fillValue(configForm, 'manual_penalty_weight', btn.dataset.manualPenaltyWeight || '1');
        fillValue(configForm, 'linked_target_required', btn.dataset.linkedTargetRequired || '1');
        fillValue(configForm, 'include_shift_revenue_factor', btn.dataset.includeShiftRevenueFactor || '1');
        fillValue(configForm, 'include_service_time_factor', btn.dataset.includeServiceTimeFactor || '1');
        fillValue(configForm, 'include_peer_review_factor', btn.dataset.includePeerReviewFactor || '1');
        fillValue(configForm, 'include_attendance_factor', btn.dataset.includeAttendanceFactor || '1');
        fillValue(configForm, 'include_manual_penalty_factor', btn.dataset.includeManualPenaltyFactor || '1');
        fillValue(configForm, 'status', btn.dataset.statusValue || 'ACTIVE');
        fillValue(configForm, 'notes', btn.dataset.notes || '');
        if (configModalTitle) {
          configModalTitle.textContent = 'Edit Kebijakan Bonus';
        }
        openModal('bonusConfigModal');
      });
    });

    document.querySelectorAll('.bonus-config-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderDetailGrid('bonusConfigDetailBody', [
          { label: 'Nama kebijakan', value: btn.dataset.configName },
          { label: 'Kode kebijakan', value: btn.dataset.configCode },
          { label: 'Deskripsi', value: btn.dataset.description },
          { label: 'Scope', value: btn.dataset.distributionScope },
          { label: 'Scope aktif', value: btn.dataset.scopeName },
          { label: 'Gerbang bulanan', value: btn.dataset.targetName },
          { label: 'Pembuka harian', value: 'Otomatis mengikuti target DAILY aktif saat generate' },
          { label: 'Sumber pool', value: btn.dataset.poolSourceMode },
          { label: 'Nilai pool', value: btn.dataset.poolSourceValue },
          { label: '% bonus dibagikan', value: btn.dataset.payoutPercent + '%' },
          { label: 'Mode penalti poin', value: btn.dataset.pointPenaltyMode },
          { label: 'Nilai penalti poin', value: btn.dataset.pointPenaltyValue },
          { label: 'Status', value: btn.dataset.status },
          { label: 'Catatan', value: btn.dataset.notes }
        ]);
        openModal('bonusConfigDetailModal');
      });
    });

    if (weightResetBtn && weightForm) {
      weightResetBtn.addEventListener('click', resetWeightForm);
    }

    if (weightScopeSelect) {
      weightScopeSelect.addEventListener('change', function () {
        syncWeightScopeOptions(weightScopeSelect.value, '');
      });
    }

    var openWeightCreateModalBtn = document.getElementById('openWeightCreateModalBtn');
    if (openWeightCreateModalBtn) {
      openWeightCreateModalBtn.addEventListener('click', function () {
        resetWeightForm();
        if (weightModalTitle) {
          weightModalTitle.textContent = 'Tambah Bobot Bonus Global';
        }
        openModal('bonusWeightModal');
      });
    }

    document.querySelectorAll('.bonus-weight-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        resetWeightForm();
        fillValue(weightForm, 'id', btn.dataset.id || '');
        fillValue(weightForm, 'target_frequency', btn.dataset.targetFrequency || 'ALL');
        fillValue(weightForm, 'weight_scope', btn.dataset.weightScope || 'DIVISION');
        fillValue(weightForm, 'point_weight', btn.dataset.pointWeight || '1');
        fillValue(weightForm, 'pool_weight', btn.dataset.pointWeight || '1');
        fillValue(weightForm, 'is_active', btn.dataset.isActive || '0');
        fillValue(weightForm, 'notes', btn.dataset.notes || '');
        syncWeightScopeOptions(btn.dataset.weightScope || 'DIVISION', btn.dataset.scopeId || '');
        if (weightModalTitle) {
          weightModalTitle.textContent = 'Edit Bobot Bonus Global';
        }
        openModal('bonusWeightModal');
      });
    });

    document.querySelectorAll('.bonus-weight-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderDetailGrid('bonusWeightDetailBody', [
          { label: 'Berlaku untuk target', value: btn.dataset.targetFrequency || 'ALL' },
          { label: 'Jenis bobot', value: btn.dataset.weightScope },
          { label: 'Target bobot', value: btn.dataset.scopeLabel },
          { label: 'Bobot poin', value: btn.dataset.pointWeight },
          { label: 'Status', value: btn.dataset.status },
          { label: 'Catatan', value: btn.dataset.notes }
        ]);
        openModal('bonusWeightDetailModal');
      });
    });

    if (penaltyResetBtn && penaltyForm) {
      penaltyResetBtn.addEventListener('click', resetPenaltyForm);
    }

    if (bonusGenerateSelectAllTargets) {
      bonusGenerateSelectAllTargets.addEventListener('change', function () {
        document.querySelectorAll('input[name="target_plan_ids[]"]').forEach(function (checkbox) {
          checkbox.checked = bonusGenerateSelectAllTargets.checked;
        });
      });
    }

    var openPenaltyCreateModalBtn = document.getElementById('openPenaltyCreateModalBtn');
    if (openPenaltyCreateModalBtn) {
      openPenaltyCreateModalBtn.addEventListener('click', function () {
        resetPenaltyForm();
        if (penaltyModalTitle) {
          penaltyModalTitle.textContent = 'Tambah Master Penalti';
        }
        openModal('bonusPenaltyModal');
      });
    }

    document.querySelectorAll('.bonus-penalty-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        resetPenaltyForm();
        fillValue(penaltyForm, 'id', btn.dataset.id || '');
        fillValue(penaltyForm, 'penalty_name', btn.dataset.penaltyName || '');
        fillValue(penaltyForm, 'penalty_code', btn.dataset.penaltyCode || '');
        fillValue(penaltyForm, 'category', btn.dataset.category || 'OTHER');
        fillValue(penaltyForm, 'applies_scope', btn.dataset.appliesScope || 'BOTH');
        fillValue(penaltyForm, 'deduction_mode', btn.dataset.deductionMode || 'FIXED_POINT');
        fillValue(penaltyForm, 'behavior_mode', btn.dataset.behaviorMode || 'MANUAL');
        fillValue(penaltyForm, 'auto_source', btn.dataset.autoSource || '');
        fillValue(penaltyForm, 'attendance_trigger', btn.dataset.attendanceTrigger || '');
        fillValue(penaltyForm, 'verification_cycle', btn.dataset.verificationCycle || 'PER_EVENT');
        fillValue(penaltyForm, 'default_points_deducted', btn.dataset.defaultPointsDeducted || '0');
        fillValue(penaltyForm, 'default_amount_deducted', btn.dataset.defaultAmountDeducted || '0');
        fillValue(penaltyForm, 'service_target_minute', btn.dataset.serviceTargetMinute || '15');
        fillValue(penaltyForm, 'service_step_minute', btn.dataset.serviceStepMinute || '5');
        fillValue(penaltyForm, 'peer_star_4_points', btn.dataset.peerStar4Points || '1');
        fillValue(penaltyForm, 'peer_star_3_points', btn.dataset.peerStar3Points || '2');
        fillValue(penaltyForm, 'peer_star_2_points', btn.dataset.peerStar2Points || '3');
        fillValue(penaltyForm, 'peer_star_1_points', btn.dataset.peerStar1Points || '4');
        fillValue(penaltyForm, 'is_manual_only', btn.dataset.isManualOnly || '0');
        fillValue(penaltyForm, 'approval_required', btn.dataset.approvalRequired || '1');
        fillValue(penaltyForm, 'requires_evidence', btn.dataset.requiresEvidence || '0');
        fillValue(penaltyForm, 'is_active', btn.dataset.isActive || '0');
        fillValue(penaltyForm, 'sort_order', btn.dataset.sortOrder || '0');
        fillValue(penaltyForm, 'notes', btn.dataset.notes || '');
        if (penaltyModalTitle) {
          penaltyModalTitle.textContent = 'Edit Master Penalti';
        }
        if (typeof syncPenaltyBehaviorMode === 'function') {
          syncPenaltyBehaviorMode();
        }
        openModal('bonusPenaltyModal');
      });
    });

    document.querySelectorAll('.bonus-penalty-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderDetailGrid('bonusPenaltyDetailBody', [
          { label: 'Nama penalti', value: btn.dataset.penaltyName },
          { label: 'Kode penalti', value: btn.dataset.penaltyCode },
          { label: 'Kategori', value: btn.dataset.category },
          { label: 'Scope', value: btn.dataset.scope },
          { label: 'Mode kerja', value: btn.dataset.mode },
          { label: 'Cara potong', value: btn.dataset.deductionMode },
          { label: 'Sumber otomatis', value: btn.dataset.autoSource },
          { label: 'Trigger absensi', value: btn.dataset.attendanceTrigger },
          { label: 'Siklus verifikasi', value: btn.dataset.verificationCycle },
          { label: 'Aturan otomatis', value: btn.dataset.autoRuleSummary },
          { label: 'Rincian otomatis', value: btn.dataset.autoRuleDetail },
          { label: 'Potong poin', value: btn.dataset.points },
          { label: 'Potong nominal', value: btn.dataset.amount },
          { label: 'Perlu approval', value: btn.dataset.approval },
          { label: 'Perlu bukti', value: btn.dataset.evidence },
          { label: 'Status', value: btn.dataset.status },
          { label: 'Catatan', value: btn.dataset.notes }
        ]);
        openModal('bonusPenaltyDetailModal');
      });
    });

    var openPenaltyEventCreateModalBtn = document.getElementById('openPenaltyEventCreateModalBtn');
    if (openPenaltyEventCreateModalBtn) {
      openPenaltyEventCreateModalBtn.addEventListener('click', function () {
        resetPenaltyEventForm();
        openModal('bonusPenaltyEventModal');
      });
    }

    document.querySelectorAll('.bonus-penalty-event-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        resetPenaltyEventForm();
        fillValue(penaltyEventForm, 'id', btn.dataset.id || '');
        fillValue(penaltyEventForm, 'penalty_date', btn.dataset.penaltyDate || '');
        fillValue(penaltyEventForm, 'penalty_type_id', btn.dataset.penaltyTypeId || '');
        fillValue(penaltyEventForm, 'rule_id', btn.dataset.ruleId || '');
        fillValue(penaltyEventForm, 'penalty_scope', btn.dataset.penaltyScope || 'PERSONAL');
        fillValue(penaltyEventForm, 'employee_id', btn.dataset.employeeId || '');
        fillValue(penaltyEventForm, 'division_id', btn.dataset.divisionId || '');
        fillValue(penaltyEventForm, 'shift_id', btn.dataset.shiftId || '');
        fillValue(penaltyEventForm, 'points_deducted', btn.dataset.pointsDeducted || '');
        if (penaltyAmountInput) {
          penaltyAmountInput.value = btn.dataset.amountDeducted
            ? ('Rp ' + Number(btn.dataset.amountDeducted || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
            : 'Otomatis dari kebijakan bonus';
        }
        fillValue(penaltyEventForm, 'status', btn.dataset.statusValue || 'APPROVED');
        fillValue(penaltyEventForm, 'reason_text', btn.dataset.reasonText || '');
        if (penaltyEventModalTitle) {
          penaltyEventModalTitle.textContent = 'Edit Kejadian Penalti';
        }
        if (penaltyEventSubmitBtn) {
          penaltyEventSubmitBtn.textContent = 'Simpan Perubahan Penalti';
        }
        if (typeof syncPenaltyEventDefaults === 'function') {
          syncPenaltyEventDefaults();
        }
        openModal('bonusPenaltyEventModal');
      });
    });

    document.querySelectorAll('.bonus-penalty-event-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var body = document.getElementById('bonusPenaltyEventDetailBody');
        var detailRows = [];
        try {
          detailRows = JSON.parse(btn.dataset.detailJson || '[]');
        } catch (err) {
          detailRows = [];
        }
        if (body) {
          var headerHtml = ''
            + '<div class="bonus-penalty-event-layout">'
            + '<div class="bonus-penalty-summary-box mb-3">'
            + '<div class="bonus-penalty-event-headline"><div class="bonus-detail-label">Nama target penalti</div><div class="bonus-detail-value">' + detailValue(btn.dataset.targetName, '-') + '</div></div>'
            + '<div class="bonus-penalty-event-summary-grid">'
            + '<div class="bonus-penalty-event-summary-item"><div class="bonus-detail-label">Scope</div><div class="bonus-detail-value">' + detailValue(btn.dataset.scope, '-') + '</div></div>'
            + '<div class="bonus-penalty-event-summary-item"><div class="bonus-detail-label">Rentang kejadian</div><div class="bonus-detail-value">' + detailValue(btn.dataset.penaltyDate, '-') + '</div></div>'
            + '<div class="bonus-penalty-event-summary-item"><div class="bonus-detail-label">Total potong poin</div><div class="bonus-detail-value">' + detailValue(btn.dataset.points, '0') + '</div></div>'
            + '<div class="bonus-penalty-event-summary-item"><div class="bonus-detail-label">Total potong nominal</div><div class="bonus-detail-value">' + detailValue(btn.dataset.amount, 'Rp 0') + '</div></div>'
            + '</div>'
            + '<div class="bonus-penalty-event-note">Semua kejadian di bawah ini ikut membentuk total penalti personal atau tim pada periode tersebut.</div>'
            + '</div>';
          var tableRows = detailRows.map(function (row) {
            return ''
              + '<tr>'
              + '<td>' + detailValue(row.date, '-') + '</td>'
              + '<td><strong>' + detailValue(row.name, '-') + '</strong><div class="small text-muted">' + detailValue(row.code, '-') + ' | ' + detailValue(row.source_type, '-') + '</div></td>'
              + '<td>' + detailValue(row.shift_name, '-') + '</td>'
              + '<td class="text-end">' + Number(row.points || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>'
              + '<td class="text-end">Rp ' + Number(row.amount || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>'
              + '<td>' + detailValue(row.status, '-') + '</td>'
              + '<td>' + detailValue(row.reason, '-') + '</td>'
              + '</tr>';
          }).join('');
          body.innerHTML = headerHtml
            + '<div class="bonus-penalty-event-table-wrap bonus-table">'
            + '<table class="table align-middle mb-0">'
            + '<thead><tr><th>Tanggal</th><th>Jenis</th><th>Shift</th><th class="text-end">Poin</th><th class="text-end">Nominal</th><th>Status</th><th>Catatan</th></tr></thead>'
            + '<tbody>' + (tableRows || '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada rincian kejadian.</td></tr>') + '</tbody>'
            + '</table></div></div>';
        }
        openModal('bonusPenaltyEventDetailModal');
      });
    });

    document.querySelectorAll('.bonus-pool-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderDetailGrid('bonusPoolDetailBody', [
          { label: 'Tanggal bonus', value: btn.dataset.bonusDate },
          { label: 'Kebijakan bonus', value: btn.dataset.ruleName },
          { label: 'Scope', value: btn.dataset.scopeName },
          { label: 'Target', value: btn.dataset.targetName },
          { label: 'Skor target', value: btn.dataset.targetScore },
          { label: 'Status target gate', value: btn.dataset.targetGate },
          { label: 'Penjualan bersih', value: btn.dataset.netSales },
          { label: 'Nilai pool', value: btn.dataset.poolAmount },
          { label: 'Status pool', value: btn.dataset.status }
        ]);
        openModal('bonusPoolDetailModal');
      });
    });

    [
      ['openSyncPenaltyModalBtn', 'bonusSyncPenaltyModal'],
      ['openSyncPenaltyModalBtnAlt', 'bonusSyncPenaltyModal'],
      ['openGeneratePoolModalBtn', 'bonusGeneratePoolModal'],
      ['openGeneratePoolSingleModalBtn', 'bonusGeneratePoolModal'],
      ['openGeneratePoolModalBtnAlt', 'bonusGeneratePoolModal'],
      ['openServiceMetricModalBtn', 'bonusServiceMetricModal'],
      ['openMonthlySummaryModalBtn', 'bonusMonthlySummaryModal']
    ].forEach(function (map) {
      var trigger = document.getElementById(map[0]);
      if (trigger) {
        trigger.addEventListener('click', function () {
          if (map[0] === 'openGeneratePoolSingleModalBtn' && bonusGeneratePoolModalTitle) {
            bonusGeneratePoolModalTitle.textContent = 'Generate Satuan Pool Bonus Harian';
          } else if ((map[0] === 'openGeneratePoolModalBtn' || map[0] === 'openGeneratePoolModalBtnAlt') && bonusGeneratePoolModalTitle) {
            bonusGeneratePoolModalTitle.textContent = 'Generate Bulk dari Target Harian';
          }
          openModal(map[1]);
        });
      }
    });

    var penaltyTypeEventSelect = document.getElementById('penaltyTypeEventSelect');
    var penaltyScopeSelect = document.getElementById('penaltyScopeSelect');
    var penaltyEmployeeSelect = document.getElementById('penaltyEmployeeSelect');
    var penaltyDivisionSelect = document.getElementById('penaltyDivisionSelect');
    var penaltyPointsInput = document.getElementById('penaltyPointsInput');
    var penaltyAmountInput = document.getElementById('penaltyAmountInput');
    var penaltyCustomNameWrap = document.getElementById('penaltyCustomNameWrap');
    var penaltyCustomNameInput = document.getElementById('penaltyCustomNameInput');
    var penaltyBehaviorMode = document.getElementById('penaltyBehaviorMode');
    var penaltyAutoSource = document.getElementById('penaltyAutoSource');
    var penaltyServiceFields = document.querySelectorAll('.bonus-penalty-parameter-service');
    var penaltyPeerFields = document.querySelectorAll('.bonus-penalty-parameter-peer');
    function syncPenaltyAutoSourceParameters() {
      var source = penaltyAutoSource ? String(penaltyAutoSource.value || '').toUpperCase() : '';
      penaltyServiceFields.forEach(function (node) {
        node.style.display = source === 'SERVICE' ? '' : 'none';
      });
      penaltyPeerFields.forEach(function (node) {
        node.style.display = source === 'PEER' ? '' : 'none';
      });
    }
    function syncPenaltyBehaviorMode() {
      if (!penaltyBehaviorMode || !penaltyAutoSource) return;
      if (penaltyBehaviorMode.value === 'MANUAL') {
        penaltyAutoSource.value = '';
        penaltyAutoSource.setAttribute('disabled', 'disabled');
      } else {
        penaltyAutoSource.removeAttribute('disabled');
      }
      if (penaltyForm) {
        var manualOnlyField = penaltyForm.querySelector('[name="is_manual_only"]');
        if (manualOnlyField) {
          manualOnlyField.checked = penaltyBehaviorMode.value === 'MANUAL';
        }
      }
      syncPenaltyAutoSourceParameters();
    }

    function syncPenaltyEventDefaults() {
      if (!penaltyTypeEventSelect) return;
      var option = penaltyTypeEventSelect.options[penaltyTypeEventSelect.selectedIndex];
      var isCustom = option && String(option.value || '') === '-1';
      if (penaltyCustomNameWrap) {
        penaltyCustomNameWrap.style.display = isCustom ? '' : 'none';
      }
      if (penaltyCustomNameInput) {
        penaltyCustomNameInput.required = !!isCustom;
        if (!isCustom) {
          penaltyCustomNameInput.value = '';
        }
      }
      if (option) {
        if (penaltyPointsInput && penaltyPointsInput.value === '') {
          penaltyPointsInput.value = option.dataset.points || '';
        }
        if (penaltyAmountInput) {
          penaltyAmountInput.value = 'Otomatis dari kebijakan bonus';
        }
        if (penaltyScopeSelect && option.dataset.scope === 'PERSONAL') {
          penaltyScopeSelect.value = 'PERSONAL';
        } else if (penaltyScopeSelect && option.dataset.scope === 'TEAM') {
          penaltyScopeSelect.value = 'TEAM';
        }
      }
      syncPenaltyEventScope();
    }

    function syncPenaltyEventScope() {
      if (!penaltyScopeSelect) return;
      var isTeam = penaltyScopeSelect.value === 'TEAM';
      if (penaltyEmployeeSelect) {
        penaltyEmployeeSelect.disabled = isTeam;
      }
      if (penaltyDivisionSelect) {
        penaltyDivisionSelect.disabled = !isTeam;
      }
    }

      if (penaltyTypeEventSelect) {
      penaltyTypeEventSelect.addEventListener('change', function () {
        if (penaltyPointsInput) penaltyPointsInput.value = '';
        if (penaltyAmountInput) penaltyAmountInput.value = 'Otomatis dari kebijakan bonus';
        syncPenaltyEventDefaults();
      });
      syncPenaltyEventDefaults();
    }
    if (penaltyScopeSelect) {
      penaltyScopeSelect.addEventListener('change', syncPenaltyEventScope);
      syncPenaltyEventScope();
    }
    if (penaltyBehaviorMode) {
      penaltyBehaviorMode.addEventListener('change', syncPenaltyBehaviorMode);
      syncPenaltyBehaviorMode();
    }
    if (penaltyAutoSource) {
      penaltyAutoSource.addEventListener('change', syncPenaltyAutoSourceParameters);
      syncPenaltyAutoSourceParameters();
    }
    document.querySelectorAll('#penaltySectionTab [data-bs-toggle="tab"]').forEach(function (btn) {
      btn.addEventListener('shown.bs.tab', function () {
        var target = String(btn.getAttribute('data-bs-target') || '');
        var view = target === '#penaltyEventPane' ? 'events' : (target === '#penaltyDetailPane' ? 'detail' : 'master');
        document.querySelectorAll('input[name="penalty_view"]').forEach(function (field) {
          field.value = view;
        });
      });
    });
    resetWeightForm();
    resetPenaltyForm();
    resetPenaltyEventForm();
  })();
</script>


