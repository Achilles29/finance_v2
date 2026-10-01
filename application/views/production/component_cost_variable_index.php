<?php
$rows = is_array($rows ?? null) ? $rows : [];
$byScope = [];
foreach ($rows as $r) {
  $byScope[strtoupper((string)($r['scope_code'] ?? ''))] = $r;
}
$component = $byScope['COMPONENT'] ?? ['scope_code' => 'COMPONENT', 'default_percent' => 20, 'notes' => '', 'is_active' => 1];
$product = $byScope['PRODUCT'] ?? ['scope_code' => 'PRODUCT', 'default_percent' => 20, 'notes' => '', 'is_active' => 1];
?>
<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Pengaturan Variable Cost</h4>
      <p class="fin-page-subtitle mb-0">Mode biaya: `DEFAULT` (pakai % di halaman ini), `NONE` (0%), `CUSTOM` (isi manual per component).</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-formulas'); ?>">Kembali ke Formula</a>
  </div>

  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'variable-cost']); ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Scope</th>
              <th style="width:220px;">Default %</th>
              <th>Catatan</th>
              <th style="width:150px;">Aktif</th>
              <th style="width:150px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr data-scope="COMPONENT">
              <td><strong>COMPONENT</strong><div class="small text-muted">Dipakai di HPP total formula component saat mode = DEFAULT.</div></td>
              <td><input class="form-control" type="number" step="0.01" min="0" value="<?php echo html_escape((string)$component['default_percent']); ?>" data-field="default_percent"></td>
              <td><input class="form-control" value="<?php echo html_escape((string)$component['notes']); ?>" data-field="notes"></td>
              <td>
                <select class="form-select" data-field="is_active">
                  <option value="1" <?php echo ((int)$component['is_active'] === 1 ? 'selected' : ''); ?>>Aktif</option>
                  <option value="0" <?php echo ((int)$component['is_active'] === 0 ? 'selected' : ''); ?>>Nonaktif</option>
                </select>
              </td>
              <td><button class="btn btn-primary btn-sm js-save">Simpan</button></td>
            </tr>
            <tr data-scope="PRODUCT">
              <td><strong>PRODUCT</strong><div class="small text-muted">Dipakai modul produk saat mode = DEFAULT.</div></td>
              <td><input class="form-control" type="number" step="0.01" min="0" value="<?php echo html_escape((string)$product['default_percent']); ?>" data-field="default_percent"></td>
              <td><input class="form-control" value="<?php echo html_escape((string)$product['notes']); ?>" data-field="notes"></td>
              <td>
                <select class="form-select" data-field="is_active">
                  <option value="1" <?php echo ((int)$product['is_active'] === 1 ? 'selected' : ''); ?>>Aktif</option>
                  <option value="0" <?php echo ((int)$product['is_active'] === 0 ? 'selected' : ''); ?>>Nonaktif</option>
                </select>
              </td>
              <td><button class="btn btn-primary btn-sm js-save">Simpan</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  async function postJson(url, payload) {
    const r = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify(payload)
    });
    const t = await r.text();
    let j;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Request gagal');
    return j;
  }

  function setButtonBusy(button, label) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.disabled = true;
  }

  function clearButtonBusy(button) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button);
      return;
    }
    button.disabled = false;
  }

  document.querySelectorAll('.js-save').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const tr = btn.closest('tr');
      const payload = {
        scope_code: tr.dataset.scope,
        default_percent: Number(tr.querySelector('[data-field="default_percent"]').value || 0),
        notes: tr.querySelector('[data-field="notes"]').value || '',
        is_active: Number(tr.querySelector('[data-field="is_active"]').value || 0)
      };
      setButtonBusy(btn, 'Menyimpan...');
      try {
        await postJson('<?php echo site_url('production/component-cost-variables/save'); ?>', payload);
        alert('Pengaturan tersimpan.');
      } catch (err) {
        alert(err.message || 'Gagal menyimpan pengaturan.');
      } finally {
        clearButtonBusy(btn);
      }
    });
  });
});
</script>
