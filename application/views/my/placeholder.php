<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$message = $message ?? 'Halaman ini sedang disiapkan.';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Portal Pegawai'); ?></h4>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo current_url(); ?>" class="d-flex gap-2">
    <select name="employee_id" class="form-select form-select-sm" style="min-width:260px">
      <option value="">Pilih Pegawai (Preview Superadmin)</option>
      <?php foreach ($employeeOptions as $o): ?>
        <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>>
          <?php echo html_escape($o['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-primary">Buka</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">Data pegawai belum terhubung ke akun ini.</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body py-4">
    <div class="mb-2">
      <strong><?php echo html_escape((string)$employee['employee_name']); ?></strong>
      <span class="text-muted"> - <?php echo html_escape((string)$employee['employee_code']); ?></span>
    </div>
    <p class="text-muted mb-0"><?php echo html_escape($message); ?></p>
  </div>
</div>
<?php endif; ?>
