<?php
$detail = is_array($detail ?? null) ? $detail : [];
$component = is_array($detail['component'] ?? null) ? $detail['component'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
$lines = is_array($detail['lines'] ?? null) ? $detail['lines'] : [];
$versions = is_array($versions ?? null) ? $versions : [];
$canRestore = !empty($can_restore);
$formulaMutationCsrf = trim((string)($production_component_formula_mutation_csrf ?? ''));
$currentFormulaRevision = trim((string)($component_formula_revision ?? ''));
$componentId = (int)($component['id'] ?? 0);

$directStd = (float)($summary['direct_cost_standard'] ?? 0);
$directLive = (float)($summary['direct_cost_live'] ?? 0);
$variableStd = (float)($summary['variable_cost_std'] ?? 0);
$variableLive = (float)($summary['variable_cost_live'] ?? 0);
$totalStd = (float)($summary['total_cogs_std'] ?? 0);
$totalLive = (float)($summary['total_cogs_live'] ?? 0);
?>
<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($component['component_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Hasil 1x produksi: <?php echo number_format((float)($summary['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?> |
        Baris <?php echo (int)($summary['line_count'] ?? 0); ?> | Formula default | Ini menjadi patokan 1 batch resep produksi
      </p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-formulas'); ?>">Kembali</a>
      <a class="btn btn-outline-primary action-icon-btn component-action-btn" href="<?php echo site_url('production/component-formulas/edit/' . (int)($component['id'] ?? 0)); ?>" title="Edit Formula" aria-label="Edit Formula"><i class="ri ri-edit-line"></i></a>
    </div>
  </div>

  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'formula']); ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="small text-muted">Direct Cost Standar</div>
        <div class="fw-semibold">Rp <?php echo number_format($directStd, 2, ',', '.'); ?></div>
        <div class="small text-muted">HPP tanpa variabel Rp <?php echo number_format($directStd, 2, ',', '.'); ?></div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Direct Cost Live</div>
        <div class="fw-semibold">Rp <?php echo number_format($directLive, 2, ',', '.'); ?></div>
        <div class="small text-muted">HPP tanpa variabel Rp <?php echo number_format($directLive, 2, ',', '.'); ?></div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Biaya Variabel</div>
        <div class="fw-semibold"><?php echo html_escape((string)($summary['variable_cost_mode'] ?? 'DEFAULT')); ?> | <?php echo number_format((float)($summary['variable_cost_percent'] ?? 0), 2, ',', '.'); ?>%</div>
        <div class="small text-muted">Std Rp <?php echo number_format($variableStd, 2, ',', '.'); ?> | Live Rp <?php echo number_format($variableLive, 2, ',', '.'); ?></div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Hasil 1x Produksi</div>
        <div class="fw-semibold"><?php echo number_format((float)($summary['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></div>
        <div class="small text-muted mt-1">COGS per <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></div>
        <div class="fw-semibold">Std Rp <?php echo number_format($totalStd / max((float)($summary['output_qty'] ?? 1), 0.0001), 2, ',', '.'); ?></div>
        <div class="fw-semibold">Live Rp <?php echo number_format($totalLive / max((float)($summary['output_qty'] ?? 1), 0.0001), 2, ',', '.'); ?></div>
        <div class="small text-muted">Potensi produksi <?php echo number_format((float)($summary['potential_output_total'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></div>
        <div class="small text-muted">Bottleneck: <?php echo html_escape((string)($summary['bottleneck_source'] ?? '-')); ?></div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:70px;">No</th>
              <th>Sumber</th>
              <th style="width:130px;">Divisi Sumber</th>
              <th class="text-end" style="width:120px;">Qty</th>
              <th style="width:120px;">Satuan</th>
              <th class="text-end" style="width:140px;">Stok Tersedia</th>
              <th class="text-end" style="width:170px;">Potensi Output</th>
              <th class="text-end" style="width:130px;">Cost Std</th>
              <th class="text-end" style="width:180px;">Cost Live</th>
              <th class="text-end" style="width:150px;">Total Std</th>
              <th class="text-end" style="width:150px;">Total Live</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lines as $i => $line): ?>
              <?php
                $sourceName = strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL'
                  ? (string)($line['material_name'] ?? '-')
                  : (string)($line['sub_component_name'] ?? '-');
              ?>
              <tr>
                <td><?php echo (int)($i + 1); ?></td>
                <td><?php echo html_escape($sourceName); ?></td>
                <td><?php echo html_escape((string)($line['source_division_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['qty'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($line['uom_code'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['available_qty'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['potential_output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['standard_unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">
                  <div class="d-inline-flex align-items-center gap-1">
                    <span><?php echo number_format((float)($line['live_unit_cost'] ?? 0), 2, ',', '.'); ?></span>
                    <span class="badge bg-light text-dark border" style="min-width:96px;" title="Sumber cost live"><?php echo html_escape((string)($line['live_cost_source_label'] ?? '-')); ?></span>
                  </div>
                </td>
                <td class="text-end"><?php echo number_format((float)($line['line_standard_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['line_live_total'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td colspan="9" class="text-end fw-semibold">Subtotal Direct Cost</td>
              <td class="text-end fw-semibold"><?php echo number_format($directStd, 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format($directLive, 2, ',', '.'); ?></td>
            </tr>
            <tr>
              <td colspan="9" class="text-end fw-semibold">Biaya Variabel - <?php echo html_escape((string)($summary['variable_cost_mode'] ?? 'DEFAULT')); ?> (<?php echo number_format((float)($summary['variable_cost_percent'] ?? 0), 2, ',', '.'); ?>%)</td>
              <td class="text-end fw-semibold"><?php echo number_format($variableStd, 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format($variableLive, 2, ',', '.'); ?></td>
            </tr>
            <tr>
              <td colspan="9" class="text-end fw-bold">Total COGS</td>
              <td class="text-end fw-bold"><?php echo number_format($totalStd, 2, ',', '.'); ?></td>
              <td class="text-end fw-bold"><?php echo number_format($totalLive, 2, ',', '.'); ?></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-center gap-2 py-3 border-top">
        <a class="btn btn-outline-primary action-icon-btn component-action-btn" href="<?php echo site_url('production/component-formulas/edit/' . (int)($component['id'] ?? 0)); ?>" title="Edit Formula" aria-label="Edit Formula"><i class="ri ri-edit-line"></i></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-formulas'); ?>">Kembali</a>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
        <div>
          <h6 class="mb-1">Riwayat versi formula</h6>
          <p class="small text-muted mb-0">Snapshot tersimpan setiap formula diubah. Riwayat ini hanya untuk pelacakan; pemulihan versi lama belum dilakukan dari halaman ini.</p>
        </div>
        <span class="badge bg-light text-dark border"><?php echo (int)count($versions); ?> versi</span>
      </div>
      <?php if ($versions === []): ?>
        <div class="small text-muted">Belum ada versi tersimpan. Versi pertama akan dibuat saat formula ini disimpan melalui editor ini.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Versi</th>
                <th>Jenis snapshot</th>
                <th>Baris</th>
                <th>Diubah oleh</th>
                <th>Waktu</th>
                <?php if ($canRestore): ?><th class="text-end">Aksi</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($versions as $version): ?>
                <?php $isCurrentSnapshot = hash_equals($currentFormulaRevision, (string)($version['formula_revision'] ?? '')); ?>
                <tr>
                  <td class="fw-semibold">v<?php echo (int)($version['version_no'] ?? 0); ?></td>
                  <td>
                    <?php
                      $versionAction = (string)($version['change_action'] ?? '');
                      $isBaseline = $versionAction === 'BASELINE';
                      $isRestore = $versionAction === 'RESTORE';
                    ?>
                    <span class="badge <?php echo $isBaseline ? 'bg-secondary' : ($isRestore ? 'bg-warning text-dark' : 'bg-primary'); ?>"><?php echo $isBaseline ? 'Baseline awal' : ($isRestore ? 'Pemulihan versi' : 'Perubahan formula'); ?></span>
                  </td>
                  <td><?php echo (int)($version['line_count'] ?? 0); ?> baris</td>
                  <td><?php echo html_escape((string)($version['actor_username'] ?? 'Sistem / histori lama')); ?></td>
                  <td><?php echo html_escape((string)($version['created_at'] ?? '-')); ?></td>
                  <?php if ($canRestore): ?>
                    <td class="text-end">
                      <?php if ($isCurrentSnapshot): ?>
                        <span class="badge bg-success">Versi aktif</span>
                      <?php else: ?>
                        <button
                          type="button"
                          class="btn btn-outline-warning btn-sm js-component-formula-restore"
                          data-version-id="<?php echo (int)($version['id'] ?? 0); ?>"
                          data-version-no="<?php echo (int)($version['version_no'] ?? 0); ?>"
                        >Pulihkan</button>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($canRestore && $componentId > 0 && $formulaMutationCsrf !== '' && $currentFormulaRevision !== ''): ?>
  <div class="modal fade" id="componentFormulaRestoreModal" tabindex="-1" aria-labelledby="componentFormulaRestoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="componentFormulaRestoreModalLabel">Pulihkan versi formula</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Anda akan mengganti formula aktif dengan <strong id="componentFormulaRestoreVersionLabel">versi pilihan</strong>.</p>
          <p class="small text-danger">Tindakan ini membuat riwayat dan audit baru. Formula aktif terbaru tidak dihapus dari riwayat.</p>
          <label class="form-label" for="componentFormulaRestorePassword">Konfirmasi password Anda</label>
          <input type="password" class="form-control" id="componentFormulaRestorePassword" autocomplete="current-password" maxlength="72">
          <div class="invalid-feedback" id="componentFormulaRestoreError"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-warning" id="componentFormulaRestoreSubmit">Pulihkan versi</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  (() => {
    const componentId = <?php echo $componentId; ?>;
    const currentRevision = <?php echo json_encode($currentFormulaRevision, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    const csrfToken = <?php echo json_encode($formulaMutationCsrf, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    const verifyUrl = <?php echo json_encode(site_url('production/component-formulas/restore-step-up/verify'), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    const restoreUrl = <?php echo json_encode(site_url('production/component-formulas/restore'), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    const modalElement = document.getElementById('componentFormulaRestoreModal');
    const passwordInput = document.getElementById('componentFormulaRestorePassword');
    const errorElement = document.getElementById('componentFormulaRestoreError');
    const submitButton = document.getElementById('componentFormulaRestoreSubmit');
    const versionLabel = document.getElementById('componentFormulaRestoreVersionLabel');
    let versionId = 0;
    const modal = window.bootstrap && modalElement ? new window.bootstrap.Modal(modalElement) : null;

    const showError = (message) => {
      errorElement.textContent = String(message || 'Permintaan tidak dapat diproses.');
      passwordInput.classList.add('is-invalid');
    };
    const postJson = async (url, payload) => {
      const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-Production-Component-Formula-Csrf': csrfToken
        },
        body: JSON.stringify(payload)
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok || !body.ok) throw new Error(body.message || 'Permintaan tidak dapat diproses.');
      return body;
    };

    document.querySelectorAll('.js-component-formula-restore').forEach((button) => {
      button.addEventListener('click', () => {
        versionId = Number(button.dataset.versionId || 0);
        versionLabel.textContent = 'versi v' + String(button.dataset.versionNo || '-');
        passwordInput.value = '';
        passwordInput.classList.remove('is-invalid');
        errorElement.textContent = '';
        if (modal) modal.show();
      });
    });
    submitButton.addEventListener('click', async () => {
      const password = String(passwordInput.value || '');
      if (!Number.isInteger(versionId) || versionId <= 0 || password === '') {
        showError('Password dan versi formula wajib dipilih.');
        return;
      }
      submitButton.disabled = true;
      try {
        const verification = await postJson(verifyUrl, {
          component_id: componentId,
          formula_version_id: versionId,
          password: password
        });
        passwordInput.value = '';
        await postJson(restoreUrl, {
          component_id: componentId,
          formula_version_id: versionId,
          component_formula_revision: currentRevision,
          step_up_proof: verification.step_up_proof
        });
        window.location.reload();
      } catch (error) {
        passwordInput.value = '';
        showError(error && error.message ? error.message : 'Pemulihan formula gagal.');
      } finally {
        submitButton.disabled = false;
      }
    });
  })();
  </script>
<?php endif; ?>
