<?php
$ci =& get_instance();
$ci->load->model('Module_notification_model');
$notifications = $ci->Module_notification_model;
$notification_ready = $notifications->ready();
$notification_rules = $notifications->rules($notification_channel);
$notification_targets = $notifications->available_targets($notification_channel, true);
$notification_rows = $notifications->recent($notification_channel);
$notification_last_worker = '';
foreach ($notification_rules as $notification_rule) {
    $notification_last_worker = max($notification_last_worker, (string)($notification_rule['last_worker_at'] ?? ''));
}
?>
<section class="card shadow-sm mb-4" id="module-notifications">
  <div class="card-header"><h5 class="mb-1"><i class="ri ri-notification-3-line me-1"></i>Notifikasi dari modul Finance</h5>
    <p class="text-muted small mb-0">Pilih kejadian dan penerimanya. Semua integrasi awalnya nonaktif. Pengaturan ini tidak mengganti jadwal laporan yang sudah ada.</p>
  </div>
  <div class="card-body">
    <?php if (!$notification_ready): ?>
      <div class="alert alert-warning mb-0">Integrasi belum terpasang. Administrator perlu menjalankan migrasi <code>2026-09-23a_module_notifications.sql</code>. Transaksi dan pengaturan bot lama tetap dapat digunakan.</div>
    <?php else: ?>
      <div class="alert alert-info small">Order masuk dikirim otomatis oleh jadwal bot (biasanya setiap menit), tanpa perlu membuka halaman kasir. Order lama sebelum integrasi diaktifkan tidak dikirim. Pengajuan divisi dikirim melalui tombol setelah disimpan, bukan otomatis.</div>
      <p class="small <?= $notification_last_worker === '' || strtotime($notification_last_worker) < time() - 300 ? 'text-warning' : 'text-success' ?>">
        Jadwal bot: <?= $notification_last_worker !== '' ? 'terakhir berjalan ' . html_escape($notification_last_worker) : 'belum terdeteksi sejak integrasi disiapkan. Setelah menyimpan, pastikan admin memeriksa jadwal bot.' ?>
      </p>
      <?php if ($notification_channel === 'WA'): ?>
      <div class="alert alert-info small"><strong>Grup untuk notifikasi berbeda dari pengaturan balasan bot.</strong> Semua grup WA terdaftar ditampilkan, baik aktif maupun nonaktif. Status grup di menu Grup WA hanya mengatur balasan chat bot. Untuk menerima notifikasi, centang grup tujuan dan aktifkan modulnya di bawah. Maksimal 10 tujuan per modul.</div>
      <div class="alert alert-warning small">WA pribadi masih dikunci oleh kebijakan perlindungan akun yang sudah ada. Untuk saat ini pilih grup WA terdaftar. Nomor dapat disimpan saat integrasi nonaktif, tetapi belum dapat diaktifkan untuk pengiriman pribadi. Daftar grup di <a href="<?= site_url('wa/group') ?>">Grup WA</a>.</div>
      <?php else: ?>
      <p class="small text-muted">Gunakan chat pribadi atau grup yang sudah didaftarkan di <a href="<?= site_url('telegram') ?>">Telegram → Tujuan</a>. Master switch Telegram juga harus aktif.</p>
      <?php endif; ?>
      <form method="post" action="<?= site_url($notification_action) ?>">
        <input type="hidden" name="<?= html_escape($notification_csrf_name) ?>" value="<?= html_escape($notification_csrf) ?>">
        <div class="row g-3">
        <?php foreach ($notification_rules as $event => $rule): ?>
          <div class="col-12 col-xl-4"><fieldset class="border rounded p-3 h-100" <?= !$notification_can_edit ? 'disabled' : '' ?>>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="notify-<?= html_escape($event) ?>" name="notifications[<?= html_escape($event) ?>][enabled]" value="1" <?= !empty($rule['is_enabled']) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="notify-<?= html_escape($event) ?>"><?= html_escape($rule['title']) ?></label>
            </div>
            <?php if ($event === 'DAILY_SALES'): ?><p class="small text-muted">Kirim manual lewat tombol <strong>Kirim WA</strong> di laporan Daily Sales. PDF mengikuti tanggal dan outlet yang ditampilkan, bukan jadwal otomatis.</p><?php endif; ?>
            <?php if ($notification_channel === 'WA'): ?>
            <div class="form-label small" id="targets-label-<?= html_escape($event) ?>">Grup penerima — centang satu atau lebih</div>
            <div class="border rounded p-2" role="group" aria-labelledby="targets-label-<?= html_escape($event) ?>" style="max-height:240px;overflow-y:auto;">
            <?php foreach ($notification_targets as $key => $target): ?>
              <?php $target_id = 'target-' . $event . '-' . str_replace(':', '-', $key); ?>
              <div class="form-check py-2 ms-1 mb-0">
                <input class="form-check-input" type="checkbox" id="<?= html_escape($target_id) ?>" name="notifications[<?= html_escape($event) ?>][targets][]" value="<?= html_escape($key) ?>" <?= !empty($target['unavailable']) ? 'disabled' : (isset($rule['targets'][$key]) ? 'checked' : '') ?>>
                <label class="form-check-label d-block text-break" for="<?= html_escape($target_id) ?>"><?= html_escape($target['label']) ?></label>
                <?php if (!empty($target['unavailable'])): ?><span class="small text-warning">ID grup belum valid. Perbaiki di menu Grup WA agar dapat dipilih.</span><?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if (!$notification_targets): ?><p class="small text-warning mb-0">Belum ada grup dengan ID valid. Daftarkan atau periksa ID grup di menu Grup WA; tidak perlu mengaktifkan balasan bot.</p><?php endif; ?>
            </div>
            <p class="form-text mb-0">Hanya grup yang dicentang menerima notifikasi modul ini. Hapus centang untuk menghentikan notifikasi ke grup tersebut.</p>
            <?php else: ?>
            <label class="form-label small" for="targets-<?= html_escape($event) ?>">Tujuan terdaftar (boleh lebih dari satu)</label>
            <select class="form-select" multiple size="4" id="targets-<?= html_escape($event) ?>" name="notifications[<?= html_escape($event) ?>][targets][]">
            <?php foreach ($notification_targets as $key => $target): ?>
              <option value="<?= html_escape($key) ?>" <?= isset($rule['targets'][$key]) ? 'selected' : '' ?>><?= html_escape($target['label']) ?></option>
            <?php endforeach; ?>
            </select>
            <?php if (!$notification_targets): ?><p class="small text-warning mt-1">Belum ada tujuan aktif. Daftarkan tujuan terlebih dahulu.</p><?php endif; ?>
            <?php endif; ?>
            <?php if ($notification_channel === 'WA'): ?>
              <label class="form-label small mt-2" for="phones-<?= html_escape($event) ?>">Nomor WA tersimpan — pengiriman pribadi terkunci</label>
              <textarea class="form-control" rows="2" id="phones-<?= html_escape($event) ?>" name="notifications[<?= html_escape($event) ?>][phones]" placeholder="6281234567890"><?php
                $phones = [];
                foreach ($rule['targets'] as $key => $target) if (strpos($key, 'phone:') === 0) $phones[] = $target['destination'];
                echo html_escape(implode("\n", $phones));
              ?></textarea>
            <?php endif; ?>
          </fieldset></div>
        <?php endforeach; ?>
        </div>
        <?php if ($notification_can_edit): ?><button class="btn btn-primary mt-3" type="submit"><i class="ri ri-save-line me-1"></i>Simpan integrasi notifikasi</button><?php endif; ?>
      </form>
      <details class="mt-4"><summary class="fw-semibold">Status 30 notifikasi terakhir</summary>
        <p class="small text-muted mt-2">Antrean belum berarti pesan diterima. Jika hasil belum pasti, periksa chat tujuan sebelum mengirim ulang agar tidak ganda. Pesan terkirim tidak dapat ditarik kembali dengan mematikan integrasi.</p>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Waktu</th><th>Kejadian / ID</th><th>Tujuan</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
        <?php foreach ($notification_rows as $row): ?>
          <tr><td class="text-nowrap"><?= html_escape($row['created_at']) ?></td><td><?= html_escape(Module_notification::events($notification_channel)[$row['event_code']] ?? $row['event_code']) ?> #<?= (int)$row['source_id'] ?></td><td><?= html_escape($row['target_label']) ?></td>
          <td><?= html_escape(['PENDING'=>'Menunggu jadwal bot','PROCESSING'=>'Sedang dikirim','SENT'=>'Terkirim','FAILED'=>'Gagal','UNKNOWN'=>'Belum pasti — periksa chat','CANCELLED'=>'Dibatalkan'][$row['status']] ?? $row['status']) ?><div class="small text-muted"><?= html_escape($row['last_error'] ?? '') ?></div></td>
          <td><?php if ($notification_can_edit && $row['status'] === 'FAILED'): ?><form method="post" action="<?= site_url($notification_action) ?>">
            <input type="hidden" name="<?= html_escape($notification_csrf_name) ?>" value="<?= html_escape($notification_csrf) ?>"><input type="hidden" name="action" value="retry"><input type="hidden" name="queue_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-primary" type="submit">Coba kembali</button>
          </form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$notification_rows): ?><tr><td colspan="5" class="text-center text-muted">Belum ada notifikasi.</td></tr><?php endif; ?>
        </tbody></table></div>
      </details>
      <p class="small text-muted mt-3 mb-0">Jika terus menunggu: admin dapat memeriksa jadwal bot pada panduan WA/Telegram. Kanal ini menggunakan worker yang sama dengan laporan terjadwal.</p>
    <?php endif; ?>
  </div>
</section>
