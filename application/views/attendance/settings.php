<?php
$policy = $policy ?? [];
$positionOptions = $position_options ?? [];
$submitterIds = (array)($pending_submitter_position_ids ?? []);
$verifierL1Ids = (array)($pending_verifier_l1_position_ids ?? []);
$verifierL2Ids = (array)($pending_verifier_l2_position_ids ?? []);
$verifierL3Ids = (array)($pending_verifier_l3_position_ids ?? []);
$scheduleOverridePositionIds = (array)($schedule_monthly_override_position_ids ?? []);
$scheduleOverrideUserIds = (array)($schedule_monthly_override_user_ids ?? []);
$userOptions = (array)($user_options ?? []);
$overtimeStandardOptions = (array)($overtime_standard_options ?? []);

$val = static function ($key, $default = '') use ($policy) {
    $posted = set_value($key);
    if ($posted !== '') {
        return $posted;
    }
    return $policy[$key] ?? $default;
};

$selectedFromPostOrDefault = static function (string $name, array $defaultIds): array {
    $posted = $_POST[$name] ?? null;
    if (is_array($posted)) {
        return array_map('intval', $posted);
    }
    return array_map('intval', $defaultIds);
};

$selSubmitter = array_flip($selectedFromPostOrDefault('pending_submitter_position_ids', $submitterIds));
$selL1 = array_flip($selectedFromPostOrDefault('pending_verifier_l1_position_ids', $verifierL1Ids));
$selL2 = array_flip($selectedFromPostOrDefault('pending_verifier_l2_position_ids', $verifierL2Ids));
$selL3 = array_flip($selectedFromPostOrDefault('pending_verifier_l3_position_ids', $verifierL3Ids));
$selScheduleOverridePositions = array_flip($selectedFromPostOrDefault('schedule_monthly_override_position_ids', $scheduleOverridePositionIds));
$selScheduleOverrideUsers = array_flip($selectedFromPostOrDefault('schedule_monthly_override_user_ids', $scheduleOverrideUserIds));

$scope = strtoupper((string)$val('pending_request_scope', 'SELF_ONLY'));
$approvalLevels = (int)$val('pending_approval_levels', 3);
if ($approvalLevels < 1 || $approvalLevels > 3) {
    $approvalLevels = 3;
}
$phMode = strtoupper((string)$val('ph_attendance_mode', 'AUTO_PRESENT'));
if (!in_array($phMode, ['AUTO_PRESENT', 'MANUAL_CLOCK'], true)) {
    $phMode = 'AUTO_PRESENT';
}
$phGrantMode = strtoupper((string)$val('ph_grant_mode', 'SHIFT_ONLY'));
if (!in_array($phGrantMode, ['SHIFT_ONLY', 'HOLIDAY_ONLY', 'SHIFT_OR_HOLIDAY'], true)) {
    $phGrantMode = 'SHIFT_ONLY';
}
$phGrantHolidayType = strtoupper((string)$val('ph_grant_holiday_type', 'ANY'));
if (!in_array($phGrantHolidayType, ['ANY', 'NATIONAL', 'COMPANY', 'SPECIAL'], true)) {
    $phGrantHolidayType = 'ANY';
}
$revisionWindowMode = strtoupper((string)$val('attendance_revision_window_mode', 'ON'));
if (!in_array($revisionWindowMode, ['OFF', 'ON', 'BY_DAYS'], true)) {
    $revisionWindowMode = 'ON';
}
$revisionWindowDays = (int)$val('attendance_revision_window_days', 7);
if ($revisionWindowDays <= 0) {
    $revisionWindowDays = 7;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo html_escape($title ?? 'Pengaturan Absensi'); ?></h4>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <form method="post" action="<?php echo site_url('attendance/settings'); ?>">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Kode Policy</label>
          <input type="text" name="policy_code" class="form-control" value="<?php echo html_escape((string)$val('policy_code', 'FINANCE_DEFAULT')); ?>">
        </div>
        <div class="col-md-9">
          <label class="form-label">Nama Policy</label>
          <input type="text" name="policy_name" class="form-control" value="<?php echo html_escape((string)$val('policy_name', 'Finance Default Policy')); ?>">
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12"><h6 class="mb-0">A. Skema Absen & Potongan</h6></div>

        <div class="col-md-3">
          <label class="form-label">Mode Absen</label>
          <select name="attendance_calc_mode" class="form-select">
            <?php foreach (['DAILY' => 'Harian', 'MONTHLY' => 'Bulanan'] as $opt => $label): ?>
              <option value="<?php echo $opt; ?>" <?php echo ((string)$val('attendance_calc_mode', 'DAILY') === $opt) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Hari Kerja / Bulan</label>
          <input type="number" min="1" name="default_work_days_per_month" class="form-control" value="<?php echo html_escape((string)$val('default_work_days_per_month', 26)); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Scope Prorata / Potongan</label>
          <select name="prorate_deduction_scope" class="form-select">
            <?php foreach (['BASIC_ONLY' => 'Gaji Pokok Saja', 'THP_TOTAL' => 'Gaji + Tunjangan'] as $opt => $label): ?>
              <option value="<?php echo $opt; ?>" <?php echo ((string)$val('prorate_deduction_scope', 'BASIC_ONLY') === $opt) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Perlakuan Tunjangan Saat Telat</label>
          <select name="allowance_late_treatment" class="form-select">
            <?php foreach (['FULL_IF_PRESENT' => 'Tetap Penuh', 'DEDUCT_IF_LATE' => 'Ikut Dipotong'] as $opt => $label): ?>
              <option value="<?php echo $opt; ?>" <?php echo ((string)$val('allowance_late_treatment', 'FULL_IF_PRESENT') === $opt) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Potongan Telat / Menit</label>
          <input type="number" min="0" step="0.01" name="late_deduction_per_minute" class="form-control" value="<?php echo html_escape((string)$val('late_deduction_per_minute', 0)); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Potongan Alpha / Hari</label>
          <input type="number" min="0" step="0.01" name="alpha_deduction_per_day" class="form-control" value="<?php echo html_escape((string)$val('alpha_deduction_per_day', 0)); ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="enable_late_deduction" id="enable_late_deduction" value="1" <?php echo ((int)$val('enable_late_deduction', 1) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="enable_late_deduction">Aktifkan Potongan Telat</label>
          </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="enable_alpha_deduction" id="enable_alpha_deduction" value="1" <?php echo ((int)$val('enable_alpha_deduction', 1) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="enable_alpha_deduction">Aktifkan Potongan Alpha</label>
          </div>
        </div>
        <div class="col-12">
          <div class="form-text">Jika potongan telat/alpha nonaktif, sistem tetap hitung prorata memakai scope di atas.</div>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12"><h6 class="mb-0">B. Window Check-in / Check-out</h6></div>

        <div class="col-md-3">
          <label class="form-label">Buka Check-in (menit)</label>
          <input type="number" min="0" name="checkin_open_minutes_before" class="form-control" value="<?php echo html_escape((string)$val('checkin_open_minutes_before', 30)); ?>">
          <div class="form-text">Dihitung dari jam mulai shift masing-masing pegawai.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Tutup Check-out (menit setelah shift)</label>
          <input type="number" min="0" name="checkout_close_minutes_after" class="form-control" value="<?php echo html_escape((string)$val('checkout_close_minutes_after', 180)); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Jam Operasional Mulai</label>
          <input type="time" name="operation_start_time" class="form-control" value="<?php echo html_escape(substr((string)$val('operation_start_time', '08:00:00'), 0, 5)); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Jam Operasional Selesai</label>
          <input type="time" name="operation_end_time" class="form-control" value="<?php echo html_escape(substr((string)$val('operation_end_time', '23:00:00'), 0, 5)); ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Kredit Pulang Shift Malam Setelah Jam</label>
          <input type="time" name="night_shift_checkout_credit_after" class="form-control" value="<?php echo html_escape(substr((string)$val('night_shift_checkout_credit_after', '22:00:00'), 0, 5)); ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="night_shift_checkout_credit_to_operation_end" id="night_shift_checkout_credit_to_operation_end" value="1" <?php echo ((int)$val('night_shift_checkout_credit_to_operation_end', 1) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="night_shift_checkout_credit_to_operation_end">Hitung pulang = jam operasional selesai</label>
          </div>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="enforce_geofence" id="enforce_geofence" value="1" <?php echo ((int)$val('enforce_geofence', 1) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="enforce_geofence">Wajib Geofence</label>
          </div>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="require_photo" id="require_photo" value="1" <?php echo ((int)$val('require_photo', 0) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="require_photo">Wajib Foto</label>
          </div>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12"><h6 class="mb-0">C. PH & Uang Makan</h6></div>

        <div class="col-md-3">
          <label class="form-label">Mode Hitung Lembur</label>
          <select name="overtime_calc_mode" class="form-select">
            <?php foreach (['AUTO' => 'Otomatis dari Checkout', 'MANUAL' => 'Manual dari Pengajuan'] as $opt => $label): ?>
              <option value="<?php echo $opt; ?>" <?php echo ((string)$val('overtime_calc_mode', 'AUTO') === $opt) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Mode MANUAL: lembur otomatis dari checkout dimatikan (overtime_minutes = 0). Nilai lembur diambil dari entri lembur manual yang approved.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Standar Lembur Default</label>
          <select name="default_overtime_standard_id" class="form-select">
            <option value="">Pilih standar master</option>
            <?php foreach ($overtimeStandardOptions as $standard): ?>
              <?php $standardId = (int)($standard['value'] ?? 0); ?>
              <option value="<?php echo $standardId; ?>" <?php echo ((int)$val('default_overtime_standard_id', 0) === $standardId) ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($standard['standard_code'] ?? '') . ' - ' . (string)($standard['standard_name'] ?? '') . ' (Rp ' . number_format((float)($standard['hourly_rate'] ?? 0), 2, ',', '.') . '/jam)'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Dipakai hanya oleh mode AUTO. Lembur manual selalu memilih standar pada setiap entri.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Kehadiran Shift PH</label>
          <div class="form-control bg-light text-wrap py-2">Otomatis hadir saat pegawai membuka halaman Absensi Saya.</div>
          <input type="hidden" name="ph_attendance_mode" value="AUTO_PRESENT">
          <div class="form-text">PH adalah hak libur berbayar; shift PH memakai satu saldo PH, bukan presensi kerja biasa.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Aturan Mendapat PH</label>
          <div class="form-control bg-light text-wrap py-2">Otomatis: pegawai eligible hadir pada hari libur nasional dengan shift kerja reguler.</div>
          <input type="hidden" name="ph_grant_mode" value="HOLIDAY_ONLY">
          <input type="hidden" name="ph_grant_holiday_type" value="NATIONAL">
          <div class="form-text">Shift PH memakai saldo yang sudah ada, bukan menambah jatah baru.</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">Jatah PH / Hari</label>
          <input type="number" min="0.25" step="0.25" name="ph_grant_qty_per_day" class="form-control" value="<?php echo html_escape((string)$val('ph_grant_qty_per_day', 1)); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Hari yang Diakui</label>
          <div class="form-control bg-light py-2">Libur nasional aktif</div>
          <div class="form-text">Libur perusahaan atau special tidak menambah PH kecuali kebijakan resmi diubah melalui pengembangan sistem.</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">Expired PH (bulan)</label>
          <input type="number" min="0" name="ph_expiry_months" class="form-control" value="<?php echo html_escape((string)$val('ph_expiry_months', 3)); ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="ph_grant_requires_checkout" id="ph_grant_requires_checkout" value="1" <?php echo ((int)$val('ph_grant_requires_checkout', 1) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="ph_grant_requires_checkout">Grant PH wajib check-out juga</label>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Mode Uang Makan</label>
          <select name="meal_calc_mode" class="form-select">
            <?php foreach (['MONTHLY' => 'Bulanan', 'CUSTOM' => 'Custom'] as $opt => $label): ?>
              <option value="<?php echo $opt; ?>" <?php echo ((string)$val('meal_calc_mode', 'MONTHLY') === $opt) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text"><strong>Bulanan:</strong> hak uang makan per hari masuk ke payroll periode. <strong>Custom:</strong> hak tetap dicatat per hari, tetapi dibayar melalui batch Pencairan Uang Makan (bisa harian, mingguan, atau rentang tanggal lain).</div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check mb-2 me-3">
            <input type="checkbox" class="form-check-input" name="ph_gets_meal_allowance" id="ph_gets_meal_allowance" value="1" <?php echo ((int)$val('ph_gets_meal_allowance', 0) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="ph_gets_meal_allowance">PH dapat uang makan</label>
          </div>
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="ph_gets_bonus" id="ph_gets_bonus" value="1" <?php echo ((int)$val('ph_gets_bonus', 0) === 1) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="ph_gets_bonus">PH dapat bonus</label>
          </div>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12"><h6 class="mb-0">D. Pengajuan & Approval Absen</h6></div>

        <div class="col-md-4">
          <label class="form-label">Scope Pengajuan Koreksi</label>
          <select name="pending_request_scope" class="form-select">
            <option value="SELF_ONLY" <?php echo $scope === 'SELF_ONLY' ? 'selected' : ''; ?>>Self saja</option>
            <option value="POSITION_ONLY" <?php echo $scope === 'POSITION_ONLY' ? 'selected' : ''; ?>>Jabatan tertentu saja</option>
            <option value="SELF_AND_POSITION" <?php echo $scope === 'SELF_AND_POSITION' ? 'selected' : ''; ?>>Self + jabatan tertentu</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Jumlah Level Approval</label>
          <select name="pending_approval_levels" class="form-select">
            <?php foreach ([1, 2, 3] as $lv): ?>
              <option value="<?php echo $lv; ?>" <?php echo ($approvalLevels === $lv) ? 'selected' : ''; ?>><?php echo $lv; ?> Level</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Batas Pengajuan Revisi</label>
          <select name="attendance_revision_window_mode" class="form-select" id="attendance_revision_window_mode">
            <option value="OFF" <?php echo $revisionWindowMode === 'OFF' ? 'selected' : ''; ?>>Off</option>
            <option value="ON" <?php echo $revisionWindowMode === 'ON' ? 'selected' : ''; ?>>On (tetap 7 hari)</option>
            <option value="BY_DAYS" <?php echo $revisionWindowMode === 'BY_DAYS' ? 'selected' : ''; ?>>Pakai jumlah hari</option>
          </select>
        </div>
        <div class="col-md-3" id="attendanceRevisionDaysWrap">
          <label class="form-label">Jumlah Hari Revisi</label>
          <input type="number" min="1" name="attendance_revision_window_days" class="form-control" value="<?php echo html_escape((string)$revisionWindowDays); ?>">
          <div class="form-text">Dipakai hanya jika mode `Pakai jumlah hari` dipilih.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Jabatan Pengusul (multi)</label>
          <select name="pending_submitter_position_ids[]" class="form-select" multiple size="5">
            <?php foreach ($positionOptions as $o): $pid=(int)$o['value']; ?>
              <option value="<?php echo $pid; ?>" <?php echo isset($selSubmitter[$pid]) ? 'selected' : ''; ?>>
                <?php echo html_escape($o['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Verifier Level 1 (multi)</label>
          <select name="pending_verifier_l1_position_ids[]" class="form-select" multiple size="5">
            <?php foreach ($positionOptions as $o): $pid=(int)$o['value']; ?>
              <option value="<?php echo $pid; ?>" <?php echo isset($selL1[$pid]) ? 'selected' : ''; ?>>
                <?php echo html_escape($o['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Verifier Level 2 (multi)</label>
          <select name="pending_verifier_l2_position_ids[]" class="form-select" multiple size="5">
            <?php foreach ($positionOptions as $o): $pid=(int)$o['value']; ?>
              <option value="<?php echo $pid; ?>" <?php echo isset($selL2[$pid]) ? 'selected' : ''; ?>>
                <?php echo html_escape($o['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Verifier Level 3 (multi)</label>
          <select name="pending_verifier_l3_position_ids[]" class="form-select" multiple size="5">
            <?php foreach ($positionOptions as $o): $pid=(int)$o['value']; ?>
              <option value="<?php echo $pid; ?>" <?php echo isset($selL3[$pid]) ? 'selected' : ''; ?>>
                <?php echo html_escape($o['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-12"><h6 class="mb-0">E. Otoritas Override Jadwal Bulanan</h6></div>
        <div class="col-md-6">
          <label class="form-label">Jabatan yang Boleh Override (multi)</label>
          <select name="schedule_monthly_override_position_ids[]" class="form-select" multiple size="5">
            <?php foreach ($positionOptions as $o): $pid=(int)$o['value']; ?>
              <option value="<?php echo $pid; ?>" <?php echo isset($selScheduleOverridePositions[$pid]) ? 'selected' : ''; ?>>
                <?php echo html_escape($o['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Seluruh user aktif dengan jabatan terpilih dapat menyetujui jadwal yang melewati batas hari kerja bulanan.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">User Spesifik yang Boleh Override (multi)</label>
          <select name="schedule_monthly_override_user_ids[]" class="form-select" multiple size="5">
            <?php foreach ($userOptions as $o): $uid=(int)$o['value']; ?>
              <option value="<?php echo $uid; ?>" <?php echo isset($selScheduleOverrideUsers[$uid]) ? 'selected' : ''; ?>>
                <?php echo html_escape($o['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Gunakan untuk pengecualian individual. Superadmin tetap selalu dapat override sebagai jalur pemulihan sistem.</div>
        </div>
        <div class="col-12">
          <div class="form-text">Otoritas ini hanya membuka persetujuan saat total jadwal melebihi batas. User tetap wajib memiliki izin edit pada halaman Jadwal Shift.</div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        <a href="<?php echo site_url('attendance/settings'); ?>" class="btn btn-outline-secondary">Muat Ulang</a>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var modeEl = document.getElementById('attendance_revision_window_mode');
  var daysWrap = document.getElementById('attendanceRevisionDaysWrap');
  if (!modeEl || !daysWrap) { return; }

  function syncRevisionModeUi() {
    daysWrap.style.display = (modeEl.value === 'BY_DAYS') ? '' : 'none';
  }

  modeEl.addEventListener('change', syncRevisionModeUi);
  syncRevisionModeUi();
})();
</script>
