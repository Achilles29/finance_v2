<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$escape = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$actions = ['can_view' => 'Lihat', 'can_create' => 'Tambah', 'can_edit' => 'Ubah', 'can_delete' => 'Hapus', 'can_export' => 'Ekspor'];
$userId = (int)($report['user']['id'] ?? 0);
$path = 'users/access-audit' . ($userId > 0 ? '/' . $userId : '');
$params = ['tab' => $tab, 'q' => $search, 'module' => $module];
if (!empty($report['is_preview'])) {
    $params['preview'] = '1';
    $params['role_ids'] = $report['selected_ids'];
}
$url = static function (array $extra = []) use ($path, $params): string {
    return base_url($path) . '?' . http_build_query(array_merge($params, $extra));
};
$scopeLabel = static function (array $scope, array $roles, bool $superadmin = false): string {
    if ($superadmin) return 'Semua divisi (Superadmin)';
    if (($scope['state'] ?? '') === 'GLOBAL') return 'Lintas divisi';
    if (($scope['state'] ?? '') === 'SINGLE') {
        foreach ($roles as $role) {
            if ((int)($role['division_scope_id'] ?? 0) === (int)$scope['division_id']) return (string)$role['division_scope_name'];
        }
        return 'Divisi #' . (int)$scope['division_id'];
    }
    return ($scope['state'] ?? '') === 'AMBIGUOUS' ? 'Konflik antar divisi' : 'Belum memiliki scope';
};
?>
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div><h1 class="h4 mb-1">Simulasi Akses Pengguna</h1><p class="text-muted mb-0">Lihat izin saat ini dan coba kombinasi role. Simulasi tidak menyimpan perubahan.</p></div>
    <a class="btn btn-outline-secondary" href="<?= base_url('users') ?>">Manajemen User</a>
  </div>
  <form method="get" action="<?= base_url('users/access-audit') ?>" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-9"><label for="audit-user" class="form-label">Pengguna yang diperiksa</label>
        <select id="audit-user" name="user_id" class="form-select" required>
          <option value="">Pilih pengguna</option>
          <?php foreach ($audit_users as $account): ?>
          <option value="<?= (int)$account['id'] ?>" <?= (int)$account['id'] === $userId ? 'selected' : '' ?>><?= $escape($account['username']) ?><?= (int)$account['is_active'] !== 1 ? ' (nonaktif)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div><div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Periksa Akses</button></div>
    </div>
  </form>
  <?php if (!$report): ?>
    <div class="card card-body text-center py-5"><h2 class="h5">Mulai dengan memilih pengguna</h2><p class="text-muted mb-0">Anda akan melihat hak per halaman, menu yang tampil, scope divisi, dan pengecualian izin pengguna.</p></div>
  <?php else: ?>
    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="card card-body h-100"><span class="text-muted small">Pengguna</span><strong><?= $escape($report['user']['username']) ?></strong><span><?= $report['is_preview'] ? 'Hasil simulasi role' : 'Konfigurasi saat ini' ?></span></div></div>
      <div class="col-md-4"><div class="card card-body h-100"><span class="text-muted small">Scope divisi</span><strong><?= $escape($scopeLabel($report['scope'], $report['roles'], $report['is_superadmin'])) ?></strong><span><?= $report['usable'] ? 'Konfigurasi akun dan scope valid' : 'Akses terblokir oleh status akun atau scope' ?></span></div></div>
      <div class="col-md-4"><div class="card card-body h-100"><span class="text-muted small">Ringkasan</span><strong><?= count($report['menus']) ?> menu dapat tampil</strong><span><?= count($report['overrides']) ?> aksi berbeda dari izin role</span></div></div>
    </div>
    <?php if (!$report['usable']): ?>
    <div class="alert alert-danger" role="alert">Akses terblokir: akun nonaktif, belum memiliki role aktif, atau scope divisinya konflik. Kolom izin tetap menampilkan hasil perhitungan role untuk membantu pemeriksaan.</div>
    <?php endif; ?>
    <p class="small text-muted">Hasil dihitung dari konfigurasi terbaru. Izin halaman tetap tunduk pada pemeriksaan dokumen, outlet/terminal perangkat, sesi kasir, dan persetujuan transaksi. Tabel ini tidak menjalankan transaksi atau login sebagai pengguna tersebut.</p>
    <?php
    $workspace_tabs = [];
    foreach (['access' => 'Akses Efektif', 'changes' => 'Perbandingan', 'roles' => 'Role & Scope', 'baseline' => 'Baseline Paket'] as $key => $label) {
      $workspace_tabs[] = [
        'label' => $label,
        'url' => $url(['tab' => $key, 'page' => 1, 'module' => $key === 'baseline' ? '' : $module]),
        'active' => $tab === $key,
      ];
    }
    $workspace_tab_label = 'Audit Akses';
    $workspace_tab_aria_label = 'Bagian simulasi akses';
    include APPPATH . 'views/layout/_workspace_tabs.php';
    ?>
    <?php if ($tab === 'roles'): ?>
      <form method="get" action="<?= base_url($path) ?>" class="card card-body">
        <input type="hidden" name="preview" value="1"><input type="hidden" name="tab" value="changes">
        <h2 class="h5">Coba kombinasi role</h2>
        <p>Izin dari role aktif digabung. Pengecualian izin pengguna tetap diterapkan: tambahan izin dahulu, kemudian pencabutan izin. Superadmin aktif memiliki akses penuh.</p>
        <p class="small text-muted">STAFF bersama BARISTA tetap dapat dipakai: role lintas divisi tidak menghapus batas divisi BARISTA. Dua divisi berbeda menghasilkan konflik. Role nonaktif tidak menyumbang izin.</p>
        <div class="row g-2 mb-3">
        <?php foreach ($report['roles'] as $role): ?>
          <div class="col-md-6"><label class="border rounded p-3 d-flex gap-2 h-100">
            <input class="form-check-input flex-shrink-0" type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>" <?= in_array((int)$role['id'], $report['selected_ids'], true) ? 'checked' : '' ?>>
            <span><strong><?= $escape($role['role_name']) ?></strong> <small><?= $escape($role['role_code']) ?></small><br>
              <small><?= (int)$role['is_active'] === 1 ? 'Aktif' : 'Nonaktif — tidak dihitung' ?> · <?= $escape($role['division_scope_id'] === null ? 'Lintas divisi' : $role['division_scope_name']) ?><?= in_array((int)$role['id'], $report['assigned_ids'], true) ? ' · Terpasang saat ini' : '' ?></small>
            </span>
          </label></div>
        <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap"><button type="submit" class="btn btn-primary">Hitung Simulasi</button><a class="btn btn-outline-secondary" href="<?= base_url($path) ?>">Kembali ke Akses Saat Ini</a></div>
      </form>
    <?php else:
        $records = $report['rows'];
        if ($tab === 'changes') {
            $records = array_merge(
                array_map(static function ($row) { return $row + ['kind' => 'Dampak simulasi']; }, $report['changes']),
                array_map(static function ($row) { return $row + ['kind' => 'Pengecualian pengguna']; }, $report['overrides'])
            );
        } elseif ($tab === 'baseline') {
            $records = $report['baseline']['rows'];
        }
        $records = array_values(array_filter($records, static function ($row) use ($search, $module): bool {
            return ($module === '' || ($row['module'] ?? '') === $module)
                && ($search === '' || mb_stripos(implode(' ', array_filter([$row['page_name'] ?? '', $row['page_code'] ?? '', $row['page'] ?? '', $row['role'] ?? '', $row['note'] ?? ''])), $search) !== false);
        }));
        $total = count($records); $pageCount = max(1, (int)ceil($total / 25)); $page = min($page, $pageCount);
        $records = array_slice($records, ($page - 1) * 25, 25);
        $menus = [];
        foreach ($report['menus'] as $menu) $menus[$menu['page_code']][] = $menu;
    ?>
      <?php if ($tab === 'changes'): ?>
        <div class="alert alert-info">Dampak simulasi membandingkan akses saat ini dengan pilihan role Anda. Pengecualian pengguna membandingkan gabungan role dengan izin akhir setelah tambahan/pencabutan izin. Selisih tidak otomatis berarti konfigurasi salah.</div>
        <?php if ($report['is_preview']): ?>
        <p><strong>Perbandingan scope:</strong> <?= $escape($scopeLabel($report['current_scope'], $report['roles'], $report['current_is_superadmin'])) ?> → <?= $escape($scopeLabel($report['scope'], $report['roles'], $report['is_superadmin'])) ?>.</p>
        <?php endif; ?>
      <?php elseif ($tab === 'baseline'): ?>
        <div class="alert alert-info">
          <?php if ($report['baseline']['status'] === 'UNCONFIGURED'): ?>
            Baseline paket belum ditetapkan. Perbandingan standar paket belum dinilai; hak akses yang Anda atur tetap berlaku. Setelah daftar izin standar disetujui, baseline dipasang oleh pengelola aplikasi.
          <?php elseif ($report['baseline']['status'] === 'INVALID'): ?>
            Baseline paket tidak valid. Perbandingan belum dapat dihitung; hubungi pengelola aplikasi.
          <?php else: ?>
            Acuan: <?= $escape($report['baseline']['label']) ?>. Membandingkan role di database dengan standar paket, bukan pilihan role simulasi. Role di luar baseline belum dinilai: <?= $escape(implode(', ', $report['baseline']['unmanaged_roles']) ?: 'tidak ada') ?>.
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="card">
        <form method="get" action="<?= base_url($path) ?>" class="card-body row g-2 align-items-end">
          <input type="hidden" name="tab" value="<?= $escape($tab) ?>">
          <?php if ($report['is_preview']): ?><input type="hidden" name="preview" value="1"><?php foreach ($report['selected_ids'] as $roleId): ?><input type="hidden" name="role_ids[]" value="<?= (int)$roleId ?>"><?php endforeach; endif; ?>
          <div class="col-md-6"><label for="audit-q" class="form-label">Cari halaman<?= $tab === 'baseline' ? ' atau role' : '' ?></label><input id="audit-q" class="form-control" name="q" maxlength="100" value="<?= $escape($search) ?>" placeholder="Misalnya: kasir, aset, resep"></div>
          <?php if ($tab !== 'baseline'): ?><div class="col-md-4"><label class="form-label" for="audit-module">Modul</label><select class="form-select" name="module" id="audit-module"><option value="">Semua modul</option><?php foreach (array_unique(array_column($report['rows'], 'module')) as $moduleCode): ?><option value="<?= $escape($moduleCode) ?>" <?= $module === $moduleCode ? 'selected' : '' ?>><?= $escape($moduleCode) ?></option><?php endforeach; ?></select></div><?php endif; ?>
          <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
        </form>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <caption class="px-3"><?= $total ?> hasil · Halaman <?= $page ?> dari <?= $pageCount ?></caption>
            <thead class="table-light"><tr>
              <?php if ($tab === 'access'): ?><th>Halaman / menu / URL</th><?php foreach ($actions as $label): ?><th class="text-center"><?= $escape($label) ?></th><?php endforeach; ?>
              <?php elseif ($tab === 'changes'): ?><th>Sumber selisih</th><th>Halaman</th><th>Aksi</th><th>Sebelum</th><th>Sesudah</th>
              <?php else: ?><th>Role</th><th>Halaman</th><th>Aksi</th><th>Standar paket</th><th>Database</th><th>Keterangan</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($records as $row): ?><tr>
              <?php if ($tab === 'access'): ?>
                <td><strong><?= $escape($row['page_name']) ?></strong><div class="small text-muted"><?= $escape($row['module']) ?> · <?= $escape($row['page_code']) ?></div>
                  <?php foreach ($menus[$row['page_code']] ?? [] as $menu): ?><div class="small mt-1"><?= $escape($menu['label']) ?><br><code class="text-break"><?= $escape($menu['url']) ?></code></div><?php endforeach; ?>
                </td>
                <?php foreach ($actions as $action => $label): ?><td class="text-center"><span class="<?= $row['flags'][$action] && $report['usable'] ? 'text-success' : 'text-muted' ?>"><?= $row['flags'][$action] ? ($report['usable'] ? 'Diizinkan' : 'Terblokir') : 'Tidak' ?></span></td><?php endforeach; ?>
              <?php elseif ($tab === 'changes'): ?>
                <td><?= $escape($row['kind']) ?></td><td><?= $escape($row['page_name']) ?><div class="small text-muted"><?= $escape($row['page_code']) ?></div></td><td><?= $escape($actions[$row['action']]) ?></td><td><?= $row['before'] ? 'Diizinkan' : 'Tidak diizinkan' ?></td><td class="<?= $row['after'] ? 'text-success' : 'text-danger' ?>"><?= $row['after'] ? 'Diizinkan' : 'Tidak diizinkan' ?></td>
              <?php else: ?>
                <td><?= $escape($row['role']) ?></td><td class="text-break"><?= $escape($row['page']) ?></td><td><?= $escape($actions[$row['action']] ?? '—') ?></td><td><?= $row['expected'] ? 'Ya' : 'Tidak' ?></td><td><?= $row['actual'] ? 'Ya' : 'Tidak' ?></td><td><?= $escape($row['note']) ?></td>
              <?php endif; ?>
            </tr><?php endforeach; ?>
            <?php if (!$records): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= $tab === 'baseline' && $report['baseline']['status'] !== 'READY' ? 'Belum ada hasil penilaian baseline.' : 'Tidak ada hasil untuk filter ini.' ?></td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $escape($url(['page' => $page - 1])) ?>">Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
          <span class="small"><?= $page ?> / <?= $pageCount ?></span>
          <?php if ($page < $pageCount): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $escape($url(['page' => $page + 1])) ?>">Berikutnya</a><?php else: ?><span></span><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
