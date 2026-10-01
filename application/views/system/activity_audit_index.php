<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$escape = static function ($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$rows = is_array($rows ?? null) ? $rows : [];
$buildUrl = static function (array $extra = []) use ($filters): string {
    $params = array_filter(array_merge([
        'from' => (string)($filters['from'] ?? ''),
        'to' => (string)($filters['to'] ?? ''),
        'user_id' => (int)($filters['user_id'] ?? 0),
        'kind' => (string)($filters['kind'] ?? ''),
        'q' => (string)($filters['q'] ?? ''),
    ], $extra), static function ($value): bool { return $value !== '' && $value !== 0 && $value !== null; });
    return site_url('system/activity-audit') . ($params ? '?' . http_build_query($params) : '');
};
$kindLabel = static function (string $kind): string {
    return ['PAGE_VIEW' => 'Akses halaman', 'LOGIN' => 'Login', 'TRANSACTION' => 'Transaksi'][$kind] ?? $kind;
};
?>
<style>
  .activity-card { border: 0; border-radius: 16px; box-shadow: 0 5px 18px rgba(58, 38, 30, .07); }
  .activity-stat { border-left: 4px solid #1a6450; }
  .activity-stat--login { border-left-color: #5b6db6; }
  .activity-stat--txn { border-left-color: #bc7a22; }
  .activity-kind { font-size: .72rem; font-weight: 700; border-radius: 999px; padding: .18rem .52rem; display: inline-block; }
  .activity-kind--PAGE_VIEW { background: #e8f3ef; color: #17634e; }
  .activity-kind--LOGIN { background: #edf0fd; color: #3f55a0; }
  .activity-kind--TRANSACTION { background: #fff1dc; color: #98601b; }
  .activity-meta { color: #7b706a; font-size: .78rem; }
  .activity-route { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; word-break: break-word; }
</style>

<div class="fin-page-header mb-3">
  <div>
    <h4 class="fin-page-title"><i class="ri-shield-user-line me-1 text-primary"></i>Log Aktivitas</h4>
    <p class="fin-page-subtitle mb-0">Jejak login, akses halaman, dan transaksi. Akses halaman mulai tercatat sejak fitur ini aktif.</p>
  </div>
</div>

<div class="alert alert-info py-2 small" role="alert">
  <i class="ri-information-line me-1"></i>
  Tidak menyimpan password, token, isi formulir, atau query URL. Transaksi lama dapat menampilkan perangkat bila cocok dengan sesi login pada waktu dan IP yang sama.
</div>

<form class="card activity-card mb-3" method="get" action="<?= $escape(site_url('system/activity-audit')) ?>">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-sm-6 col-lg-2"><label class="form-label small mb-1">Dari</label><input class="form-control" type="date" name="from" value="<?= $escape($filters['from'] ?? '') ?>"></div>
      <div class="col-sm-6 col-lg-2"><label class="form-label small mb-1">Sampai</label><input class="form-control" type="date" name="to" value="<?= $escape($filters['to'] ?? '') ?>"></div>
      <div class="col-sm-6 col-lg-2"><label class="form-label small mb-1">Pengguna</label><select class="form-select" name="user_id"><option value="">Semua pengguna</option><?php foreach (($users ?? []) as $user): ?><option value="<?= (int)$user['id'] ?>" <?= (int)($filters['user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= $escape($user['username']) ?><?= (int)$user['is_active'] !== 1 ? ' (nonaktif)' : '' ?></option><?php endforeach; ?></select></div>
      <div class="col-sm-6 col-lg-2"><label class="form-label small mb-1">Jenis</label><select class="form-select" name="kind"><option value="">Semua aktivitas</option><?php foreach (['PAGE_VIEW' => 'Akses halaman', 'LOGIN' => 'Login', 'TRANSACTION' => 'Transaksi'] as $value => $label): ?><option value="<?= $value ?>" <?= ($filters['kind'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
      <div class="col-lg-3"><label class="form-label small mb-1">Cari halaman, transaksi, referensi</label><input class="form-control" name="q" maxlength="100" value="<?= $escape($filters['q'] ?? '') ?>" placeholder="Contoh: purchase, PO-001"></div>
      <div class="col-lg-1 d-flex gap-1"><button class="btn btn-primary flex-fill" type="submit" title="Terapkan filter"><i class="ri-search-line"></i></button><a class="btn btn-outline-secondary" href="<?= $escape(site_url('system/activity-audit')) ?>" title="Reset filter"><i class="ri-refresh-line"></i></a></div>
    </div>
    <div class="form-text mt-2">Rentang maksimal 31 hari per pencarian agar log tetap cepat dibuka.</div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card activity-card activity-stat h-100"><div class="card-body py-3"><div class="activity-meta">Total aktivitas</div><div class="h4 mb-0"><?= number_format((int)($summary['total'] ?? 0), 0, ',', '.') ?></div></div></div></div>
  <div class="col-md-3"><div class="card activity-card activity-stat h-100"><div class="card-body py-3"><div class="activity-meta">Akses halaman</div><div class="h4 mb-0"><?= number_format((int)($summary['page_views'] ?? 0), 0, ',', '.') ?></div></div></div></div>
  <div class="col-md-3"><div class="card activity-card activity-stat activity-stat--login h-100"><div class="card-body py-3"><div class="activity-meta">Login</div><div class="h4 mb-0"><?= number_format((int)($summary['logins'] ?? 0), 0, ',', '.') ?></div></div></div></div>
  <div class="col-md-3"><div class="card activity-card activity-stat activity-stat--txn h-100"><div class="card-body py-3"><div class="activity-meta">Transaksi/perubahan</div><div class="h4 mb-0"><?= number_format((int)($summary['transactions'] ?? 0), 0, ',', '.') ?></div></div></div></div>
</div>

<div class="card activity-card">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold">Daftar aktivitas</span>
    <small class="text-muted"><?= number_format((int)($total ?? 0), 0, ',', '.') ?> data · terbaru di atas</small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Waktu</th><th>Aktivitas</th><th>Pengguna</th><th>Alamat/IP</th><th>Perangkat</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-5"><i class="ri-inbox-line d-block fs-3 mb-2"></i>Tidak ada aktivitas pada filter ini.</td></tr><?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="text-nowrap small"><strong><?= $escape($row['event_at'] ?? '-') ?></strong></td>
          <td>
            <span class="activity-kind activity-kind--<?= $escape($row['event_kind'] ?? '') ?>"><?= $escape($kindLabel((string)($row['event_kind'] ?? ''))) ?></span>
            <div class="fw-semibold mt-1"><?= $escape($row['action_label'] ?? '-') ?></div>
            <?php if (!empty($row['route_path'])): ?><div class="activity-route text-muted mt-1"><?= $escape($row['request_method'] ?? 'GET') ?> /<?= $escape($row['route_path']) ?><?= !empty($row['page_code']) ? ' · ' . $escape($row['page_code']) : '' ?></div><?php endif; ?>
            <?php if (!empty($row['entity_table'])): ?><div class="activity-meta mt-1">Data: <?= $escape($row['entity_table']) ?><?= !empty($row['entity_id']) ? ' #' . (int)$row['entity_id'] : '' ?><?= !empty($row['transaction_no']) ? ' · ' . $escape($row['transaction_no']) : '' ?><?= !empty($row['ref_label']) ? ' · Ref ' . $escape($row['ref_label']) : '' ?></div><?php endif; ?>
            <?php if (!empty($row['notes_preview'])): ?><div class="activity-meta mt-1"><?= $escape($row['notes_preview']) ?></div><?php endif; ?>
          </td>
          <td><strong><?= $escape($row['username'] ?? '-') ?></strong><div class="activity-meta"><?= $escape($row['module_code'] ?? '-') ?></div></td>
          <td class="small text-nowrap"><?= $escape($row['ip_address'] ?: 'Tidak tercatat') ?></td>
          <td><div class="small"><?= $escape($row['device_label'] ?? 'Tidak tercatat') ?></div><?php if (!empty($row['user_agent'])): ?><details class="activity-meta mt-1"><summary>Detail browser</summary><span class="activity-route"><?= $escape($row['user_agent']) ?></span></details><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (($page_count ?? 1) > 1): ?><div class="card-footer bg-white d-flex justify-content-between align-items-center"><span class="small text-muted">Halaman <?= (int)$page ?> dari <?= (int)$page_count ?></span><div class="btn-group"><a class="btn btn-sm btn-outline-secondary <?= (int)$page <= 1 ? 'disabled' : '' ?>" href="<?= $escape($buildUrl(['page' => max(1, (int)$page - 1)])) ?>">Sebelumnya</a><a class="btn btn-sm btn-outline-secondary <?= (int)$page >= (int)$page_count ? 'disabled' : '' ?>" href="<?= $escape($buildUrl(['page' => min((int)$page_count, (int)$page + 1)])) ?>">Berikutnya</a></div></div><?php endif; ?>
</div>
