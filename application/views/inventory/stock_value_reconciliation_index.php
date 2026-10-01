<?php
$contextInput = is_array($context_input ?? null) ? $context_input : [];
$context = is_array($context ?? null) ? $context : [];
$records = is_array($records ?? null) ? $records : [];
$valueCandidates = is_array($value_candidates ?? null) ? $value_candidates : [];
$hppSuggestions = is_array($hpp_suggestions ?? null) ? $hpp_suggestions : [];
$candidateFilters = is_array($candidate_filters ?? null) ? $candidate_filters : [];
$schemaReady = !empty($schema_ready);
$canPost = !empty($can_post);
$stateOk = !empty($context['ok']);
$selected = !empty($contextInput['monthly_stock_id']);
$alreadyAligned = $stateOk
    && abs((float)($context['qty_gap'] ?? 0)) <= 0.0001
    && abs((float)($context['value_gap'] ?? 0)) <= 1;
$stock = is_array($context['stock'] ?? null) ? $context['stock'] : [];
$lots = is_array($context['lots'] ?? null) ? $context['lots'] : [];
$fmtQty = static fn($value): string => number_format((float)$value, 4, ',', '.');
$fmtMoney = static fn($value): string => 'Rp ' . number_format((float)$value, 2, ',', '.');
$fmtCost = static fn($value): string => 'Rp ' . number_format((float)$value, 6, ',', '.');
$contextFields = [
    'month', 'stock_domain', 'location_scope', 'location_type', 'division_id',
    'item_id', 'material_id', 'component_id', 'buy_uom_id', 'uom_id',
    'profile_key', 'monthly_stock_id',
];
$domainLabel = strtoupper((string)($contextInput['stock_domain'] ?? '')) === 'COMPONENT' ? 'Component' : 'Bahan baku';
$uomCode = (string)($stock['uom_code'] ?? '');
$mutationCsrfName = (string)($inventory_control_mutation_csrf_name ?? '');
$mutationCsrfValue = (string)($inventory_control_mutation_csrf_value ?? '');
$hasMutationCsrf = $mutationCsrfName !== '' && $mutationCsrfValue !== '';
$canVoid = $canPost && $hasMutationCsrf;
$healthUrl = (string)($health_url ?? site_url('inventory/stock/health'));
$candidateMonth = (string)($candidateFilters['month'] ?? $contextInput['month'] ?? date('Y-m-01'));
$candidateUrl = static function (array $row) use ($candidateMonth): string {
    return site_url('inventory/stock/value-reconciliation') . '?' . http_build_query([
        'month' => $candidateMonth,
        'stock_domain' => strtoupper((string)($row['stock_domain'] ?? '')),
        'location_scope' => strtoupper((string)($row['location_scope'] ?? '')),
        'location_type' => strtoupper((string)($row['location_type'] ?? '')),
        'division_id' => (int)($row['division_id'] ?? 0) ?: '',
        'item_id' => (int)($row['item_id'] ?? 0) ?: '',
        'material_id' => (int)($row['material_id'] ?? 0) ?: '',
        'component_id' => (int)($row['component_id'] ?? 0) ?: '',
        'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0) ?: '',
        'uom_id' => (int)($row['uom_id'] ?? 0) ?: '',
        'profile_key' => (string)($row['profile_key'] ?? ''),
        'monthly_stock_id' => (int)($row['monthly_stock_id'] ?? 0),
    ]);
};
?>

<style>
.value-recon{--vr-ink:#3f2925;--vr-muted:#896a61;--vr-line:#edd9d0;--vr-red:#ab1024;--vr-dark:#850b1a;--vr-cream:#fffaf7;--vr-green:#157447}.value-recon .vr-intro{border:1px solid var(--vr-line);border-radius:18px;background:linear-gradient(135deg,#fffefa,#fff1ea);padding:1rem 1.1rem}.value-recon .vr-intro p{color:var(--vr-muted);font-size:.82rem;max-width:970px;margin:.35rem 0 0}.value-recon .vr-alert{border-radius:14px;padding:.8rem .95rem;font-size:.8rem}.value-recon .vr-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem}.value-recon .vr-card{border:1px solid var(--vr-line);border-radius:14px;background:#fff;padding:.78rem .85rem}.value-recon .vr-card span{display:block;font-size:.67rem;font-weight:850;text-transform:uppercase;letter-spacing:.04em;color:var(--vr-muted)}.value-recon .vr-card strong{display:block;font-size:1rem;color:var(--vr-ink);margin-top:.22rem}.value-recon .vr-card small{display:block;margin-top:.16rem;color:var(--vr-muted);font-size:.72rem}.value-recon .vr-card.is-alert{background:#fff5f4;border-color:#f3c6bf}.value-recon .vr-card.is-alert strong{color:#b42318}.value-recon .vr-panel{border:1px solid var(--vr-line);border-radius:18px;background:#fff;overflow:hidden}.value-recon .vr-panel-head{padding:.9rem 1rem;border-bottom:1px solid var(--vr-line);background:var(--vr-cream)}.value-recon .vr-panel-head h5{margin:0;color:var(--vr-ink);font-size:1rem}.value-recon .vr-panel-head small{color:var(--vr-muted)}.value-recon .vr-panel-body{padding:1rem}.value-recon .vr-table-wrap{max-height:42vh;overflow:auto}.value-recon .vr-table{min-width:840px;margin:0}.value-recon .vr-table th{position:sticky;top:0;z-index:2;background:linear-gradient(180deg,var(--vr-red),var(--vr-dark));color:#fff;font-size:.68rem;letter-spacing:.04em;white-space:nowrap}.value-recon .vr-table td{font-size:.78rem;vertical-align:middle;border-color:#f0dfd7}.value-recon .vr-table tbody tr:nth-child(even) td{background:#fffaf7}.value-recon .vr-history{margin-top:1rem}.value-recon .vr-chip{display:inline-block;border-radius:999px;padding:.22rem .55rem;font-size:.66rem;font-weight:800;background:#efe7e5;color:#6d3b31}.value-recon .vr-mode-help{border:1px solid #ebddd7;border-radius:13px;background:#fffaf8;padding:.7rem .8rem;color:var(--vr-muted);font-size:.76rem;line-height:1.45}.value-recon .vr-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.value-recon .vr-form-grid .wide{grid-column:span 3}.value-recon .vr-empty{padding:1.25rem;border:1px dashed #e8cfc4;border-radius:14px;background:#fffdfb;color:var(--vr-muted);font-size:.84rem}@media(max-width:900px){.value-recon .vr-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.value-recon .vr-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.value-recon .vr-form-grid .wide{grid-column:span 2}}@media(max-width:600px){.value-recon .vr-summary,.value-recon .vr-form-grid{grid-template-columns:1fr}.value-recon .vr-form-grid .wide{grid-column:span 1}}
</style>

<div class="value-recon">
  <section class="vr-intro d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div>
      <h4 class="mb-1"><i class="ri ri-money-dollar-circle-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Koreksi Nilai Stok')); ?></h4>
      <p>Halaman ini hanya dipakai saat jumlah stok sistem dan lot aktif sudah sama, tetapi nilai uangnya berbeda. Koreksi tidak menambah atau mengurangi barang. Lot yang sudah <strong>CLOSED</strong> tetap menjadi histori dan tidak pernah disentuh.</p>
    </div>
    <a class="btn btn-outline-danger btn-sm" href="<?php echo html_escape($healthUrl); ?>"><i class="ri ri-heart-pulse-line"></i> Kembali ke Stock Health</a>
  </section>

  <?php if (!$schemaReady): ?>
    <div class="alert alert-danger vr-alert mb-3"><strong>Fondasi koreksi nilai belum tersedia.</strong> Jalankan SQL <code>2026-08-19e_inventory_stock_value_reconciliation_foundation.sql</code>, lalu muat ulang halaman ini.</div>
  <?php endif; ?>

  <?php if (!$selected): ?>
    <section class="vr-panel mb-3">
      <div class="vr-panel-head"><h5>Temuan nilai yang siap diperiksa</h5><small>Hanya tampil bila jumlah stok dan lot sudah sama. Pilih satu baris untuk menentukan nilai mana yang benar.</small></div>
      <div class="vr-panel-body">
        <form method="get" action="<?php echo site_url('inventory/stock/value-reconciliation'); ?>" class="row g-2 align-items-end mb-3">
          <div class="col-md-3"><label class="form-label small fw-bold">Bulan aktif</label><input class="form-control form-control-sm" type="month" name="month" value="<?php echo html_escape(substr($candidateMonth, 0, 7)); ?>"></div>
          <div class="col-md-5"><label class="form-label small fw-bold">Cari barang atau profil</label><input class="form-control form-control-sm" name="q" value="<?php echo html_escape((string)($candidateFilters['q'] ?? '')); ?>" placeholder="Contoh: BEEF BURGER atau kode profil"></div>
          <div class="col-md-2"><label class="form-label small fw-bold">Jenis stok</label><select class="form-select form-select-sm" name="stock_domain"><option value="">Semua</option><option value="MATERIAL" <?php echo (($candidateFilters['stock_domain'] ?? '') === 'MATERIAL') ? 'selected' : ''; ?>>Bahan baku</option><option value="COMPONENT" <?php echo (($candidateFilters['stock_domain'] ?? '') === 'COMPONENT') ? 'selected' : ''; ?>>Component</option></select></div>
          <div class="col-md-2 d-grid"><button class="btn btn-outline-danger btn-sm" type="submit">Tampilkan</button></div>
        </form>
        <div class="vr-table-wrap"><table class="table table-sm vr-table"><thead><tr><th>Barang / profil</th><th>Area</th><th class="text-end">Qty stok</th><th class="text-end">Qty lot</th><th class="text-end">Nilai stok</th><th class="text-end">Nilai lot</th><th class="text-end">Selisih nilai</th><th class="text-center">Aksi</th></tr></thead><tbody>
        <?php if (empty($valueCandidates)): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada selisih nilai murni pada filter ini. Jika jumlah stok dan lot juga berbeda, selesaikan melalui Recon Stok Fisik dari Stock Health.</td></tr><?php else: foreach ($valueCandidates as $candidate): ?>
          <?php $candidateProfile = trim((string)($candidate['profile_key'] ?? '')); ?>
          <tr><td><strong><?php echo html_escape((string)($candidate['inventory_name'] ?? '-')); ?></strong><?php if ($candidateProfile !== ''): ?><div class="small text-muted">Profil <?php echo html_escape(substr($candidateProfile, 0, 16)); ?>...</div><?php endif; ?></td><td><?php echo html_escape((string)($candidate['division_name'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($candidate['location_type'] ?? '-')); ?></div></td><td class="text-end"><?php echo $fmtQty($candidate['stock_qty'] ?? 0); ?> <?php echo html_escape((string)($candidate['uom_code'] ?? '')); ?></td><td class="text-end"><?php echo $fmtQty($candidate['lot_qty'] ?? 0); ?> <?php echo html_escape((string)($candidate['uom_code'] ?? '')); ?></td><td class="text-end"><?php echo $fmtMoney($candidate['stock_value'] ?? 0); ?></td><td class="text-end"><?php echo $fmtMoney($candidate['lot_value'] ?? 0); ?></td><td class="text-end text-danger fw-bold"><?php echo $fmtMoney($candidate['value_gap'] ?? 0); ?></td><td class="text-center"><a class="btn btn-outline-danger btn-sm" href="<?php echo html_escape($candidateUrl($candidate)); ?>">Periksa Nilai</a></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
      </div>
    </section>
  <?php elseif (!$stateOk): ?>
    <div class="alert alert-danger vr-alert mb-3"><?php echo html_escape((string)($context['message'] ?? 'Konteks koreksi nilai tidak dapat dibaca.')); ?></div>
  <?php else: ?>
    <section class="vr-panel mb-3">
      <div class="vr-panel-head"><h5><?php echo html_escape((string)($stock['inventory_name'] ?? '-')); ?></h5><small><?php echo html_escape($domainLabel); ?> | <?php echo html_escape((string)($contextInput['location_scope'] ?? '-')); ?> | <?php echo html_escape((string)($contextInput['location_type'] ?? '-')); ?><?php if (!empty($contextInput['profile_key'])): ?> | Profil <?php echo html_escape(substr((string)$contextInput['profile_key'], 0, 16)); ?>...<?php endif; ?></small></div>
      <div class="vr-panel-body">
        <div class="vr-summary mb-3">
          <div class="vr-card"><span>Jumlah stok sistem</span><strong><?php echo $fmtQty($context['stock_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?></strong><small>Saldo bulan aktif</small></div>
          <div class="vr-card"><span>Jumlah lot OPEN</span><strong><?php echo $fmtQty($context['lot_qty'] ?? 0); ?> <?php echo html_escape($uomCode); ?></strong><small><?php echo number_format(count($lots), 0, ',', '.'); ?> lot aktif</small></div>
          <div class="vr-card"><span>Nilai stok sistem</span><strong><?php echo $fmtMoney($context['stock_value'] ?? 0); ?></strong><small>Rata-rata <?php echo $fmtCost($stock['stock_avg_cost'] ?? 0); ?> / <?php echo html_escape($uomCode); ?></small></div>
          <div class="vr-card is-alert"><span>Selisih nilai</span><strong><?php echo $fmtMoney($context['value_gap'] ?? 0); ?></strong><small>Stok sistem dikurangi nilai lot OPEN</small></div>
        </div>

        <?php if (strtoupper((string)($contextInput['stock_domain'] ?? '')) === 'COMPONENT' && !empty($hppSuggestions['sources'])): ?>
          <div class="border rounded-3 p-3 mb-3" style="border-color:#e8cfc4 !important;background:#fffaf7">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
              <div><strong style="color:#45261d">Saran HPP/unit untuk verifikasi</strong><div class="small text-muted">Saran ini hanya membantu memilih angka. Tidak ada yang diposting otomatis.</div></div>
              <?php if ((float)($hppSuggestions['recommended_hpp'] ?? 0) > 0): ?><span class="badge rounded-pill text-bg-success">Rekomendasi: <?php echo $fmtCost($hppSuggestions['recommended_hpp']); ?></span><?php endif; ?>
            </div>
            <?php if (!empty($hppSuggestions['warning'])): ?><div class="alert alert-warning py-2 px-3 mt-2 mb-2 small"><?php echo html_escape((string)$hppSuggestions['warning']); ?></div><?php endif; ?>
            <div class="table-responsive mt-2"><table class="table table-sm mb-0 align-middle" style="font-size:.76rem">
              <thead><tr><th>Sumber</th><th class="text-end">HPP/unit</th><th>Keterangan</th><?php if (!empty($context['can_post']) && $schemaReady && $canPost && $hasMutationCsrf): ?><th class="text-center">Pakai</th><?php endif; ?></tr></thead>
              <tbody>
                <tr><td>HPP stok bulanan saat ini</td><td class="text-end fw-bold"><?php echo $fmtCost($hppSuggestions['stock_hpp'] ?? 0); ?></td><td class="text-muted">Angka diagnostik dari saldo; jangan dipakai bila nol/negatif.</td><?php if (!empty($context['can_post']) && $schemaReady && $canPost && $hasMutationCsrf): ?><td></td><?php endif; ?></tr>
                <?php foreach ($hppSuggestions['sources'] as $source): ?>
                  <tr><td><?php echo html_escape((string)($source['label'] ?? '-')); ?></td><td class="text-end fw-bold"><?php echo $fmtCost($source['value'] ?? 0); ?></td><td class="text-muted"><?php echo html_escape((string)($source['note'] ?? '')); ?></td><?php if (!empty($context['can_post']) && $schemaReady && $canPost && $hasMutationCsrf): ?><td class="text-center"><?php if (!empty($source['usable'])): ?><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 hpp-suggestion-btn" data-hpp-value="<?php echo html_escape((string)$source['value']); ?>" data-resolution-mode="<?php echo html_escape((string)($source['resolution_mode'] ?? 'MANUAL_TOTAL_VALUE')); ?>">Pakai</button><?php endif; ?></td><?php endif; ?></tr>
                <?php endforeach; ?>
              </tbody>
            </table></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($context['can_post']) && $schemaReady && $canPost && $hasMutationCsrf): ?>
          <form method="post" action="<?php echo site_url('inventory/stock/value-reconciliation/post'); ?>">
            <input type="hidden" name="<?php echo html_escape($mutationCsrfName); ?>" value="<?php echo html_escape($mutationCsrfValue); ?>">
            <?php foreach ($contextFields as $field): ?><input type="hidden" name="<?php echo html_escape($field); ?>" value="<?php echo html_escape((string)($contextInput[$field] ?? '')); ?>"><?php endforeach; ?>
            <div class="vr-form-grid">
              <div class="wide"><label class="form-label small fw-bold">Nilai mana yang sudah Anda verifikasi benar?</label><select class="form-select" name="resolution_mode" id="resolutionMode" required><option value="LOT_TO_STOCK">Nilai lot OPEN benar: samakan nilai stok sistem dengan lot</option><option value="STOCK_TO_LOT">Nilai stok sistem benar: samakan biaya lot OPEN dengan stok</option><option value="MANUAL_TOTAL_VALUE">Saya punya total nilai hasil verifikasi: samakan keduanya ke angka itu</option></select></div>
              <div class="wide"><div class="vr-mode-help" id="resolutionHelp">Pilihan pertama hanya mengubah nilai stok bulan aktif. Nilai dan biaya lot OPEN tetap seperti sekarang.</div></div>
              <div id="manualValueWrap" class="wide" style="display:none"><div class="row g-2"><div class="col-md-5"><label class="form-label small fw-bold">HPP/unit hasil verifikasi</label><input class="form-control" type="number" min="0" step="0.000001" name="manual_unit_cost" id="manualUnitCost" placeholder="Contoh: 12500"><div class="form-text">Jika diisi, total otomatis = qty stok × HPP/unit.</div></div><div class="col-md-7"><label class="form-label small fw-bold">Total nilai hasil verifikasi</label><input class="form-control" type="number" min="0" step="0.01" name="manual_total_value" id="manualTotalValue" placeholder="Contoh: 44000"><div class="form-text" id="manualCalcHint">Boleh isi total langsung bila HPP/unit belum diketahui.</div></div></div></div>
              <div><label class="form-label small fw-bold">Alasan</label><select class="form-select" name="reason" required><option value="">Pilih alasan</option><option value="LOT_VALUE_MISSING">Nilai lot belum terbawa</option><option value="MONTHLY_VALUE_MISSING">Nilai stok bulanan belum terbawa</option><option value="OPEN_LOT_VALUE_MISMATCH">Nilai lot aktif dan stok berbeda</option><option value="CUT_OFF_VERIFIED">Diverifikasi saat cut-off</option><option value="OTHER">Lainnya</option></select></div>
              <div><label class="form-label small fw-bold">Ketik konfirmasi</label><input class="form-control" name="confirmation" required placeholder="NILAI" autocomplete="off"></div>
              <div class="wide"><label class="form-label small fw-bold">Catatan pemeriksaan</label><textarea class="form-control" rows="2" name="notes" placeholder="Sebutkan dasar verifikasi, misalnya dokumen receipt, hasil cek lot, atau persetujuan penanggung jawab."></textarea></div>
            </div>
            <div class="alert alert-warning vr-alert mt-3 mb-0"><strong>Periksa sebelum simpan:</strong> tindakan ini tidak mengubah jumlah stok. Jika angka jumlah di atas tidak sama, kembali ke Stock Health dan lakukan Recon Stok Fisik, bukan koreksi nilai.</div>
            <div class="text-end mt-3"><button class="btn btn-danger" type="submit"><i class="ri ri-check-double-line"></i> Posting Koreksi Nilai</button></div>
          </form>
        <?php else: ?>
          <?php if ($alreadyAligned): ?>
            <div class="alert alert-success vr-alert mb-0"><strong>Nilai stok dan lot sudah selaras.</strong> Tidak ada koreksi nilai baru yang perlu diposting. Jika Anda baru saja menyimpan koreksi, ini menandakan hasilnya sudah berhasil diterapkan.</div>
          <?php else: ?>
            <div class="alert alert-warning vr-alert mb-0"><strong>Belum bisa diposting.</strong> <?php echo html_escape((string)($context['block_message'] ?? (!$canPost ? 'Hanya Superadmin yang dapat memposting koreksi nilai.' : 'Periksa kembali konteks stok ini.'))); ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="vr-panel mb-3"><div class="vr-panel-head"><h5>Lot OPEN yang dipakai dalam perhitungan</h5><small>Lot CLOSED tidak ditampilkan karena tidak termasuk saldo aktif.</small></div><div class="vr-table-wrap"><table class="table table-sm vr-table"><thead><tr><th>Lot</th><th class="text-end">Qty tersisa</th><th class="text-end">Biaya saat ini</th><th class="text-end">Nilai saat ini</th></tr></thead><tbody><?php if (empty($lots)): ?><tr><td colspan="4" class="text-center text-muted py-4">Tidak ada lot OPEN bersaldo.</td></tr><?php else: foreach ($lots as $lot): ?><?php $lotNo = trim((string)($lot['lot_no'] ?? '')); ?><tr><td><strong><?php echo html_escape($lotNo !== '' ? $lotNo : ('Lot #' . (int)($lot['id'] ?? 0))); ?></strong><div class="small text-muted">ID lot <?php echo (int)($lot['id'] ?? 0); ?></div></td><td class="text-end"><?php echo $fmtQty($lot['qty_balance'] ?? 0); ?> <?php echo html_escape($uomCode); ?></td><td class="text-end"><?php echo $fmtCost($lot['unit_cost'] ?? 0); ?></td><td class="text-end"><?php echo $fmtMoney((float)($lot['qty_balance'] ?? 0) * (float)($lot['unit_cost'] ?? 0)); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
  <?php endif; ?>

  <section class="vr-panel vr-history"><div class="vr-panel-head"><h5>Riwayat koreksi nilai bulan ini</h5><small>Jika salah input, Superadmin dapat melakukan VOID selama belum ada perubahan lanjutan. Setelah VOID, nilai sebelum dokumen dipulihkan dan koreksi baru dapat dibuat.</small></div><div class="vr-table-wrap"><table class="table table-sm vr-table"><thead><tr><th>No. dokumen</th><th>Barang</th><th>Area</th><th class="text-end">Nilai sebelum</th><th class="text-end">Nilai sesudah</th><th>Cara koreksi</th><th>Alasan</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr></thead><tbody><?php if (empty($records)): ?><tr><td colspan="10" class="text-center text-muted py-4">Belum ada koreksi nilai pada bulan ini.</td></tr><?php else: foreach ($records as $record): ?><?php $recordStatus = strtoupper(trim((string)($record['status'] ?? ''))); ?><tr><td><strong><?php echo html_escape((string)($record['revaluation_no'] ?? '-')); ?></strong></td><td><?php echo html_escape((string)($record['inventory_name'] ?? '-')); ?></td><td><?php echo html_escape((string)($record['stock_scope'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($record['location_type'] ?? '-')); ?></div></td><td class="text-end"><?php echo $fmtMoney($record['stock_value_before'] ?? 0); ?></td><td class="text-end"><?php echo $fmtMoney($record['stock_value_after'] ?? 0); ?></td><td><span class="vr-chip"><?php echo html_escape(str_replace('_', ' ', (string)($record['resolution_mode'] ?? '-'))); ?></span></td><td><?php echo html_escape((string)($record['reason'] ?? '-')); ?></td><td><span class="vr-chip"><?php echo html_escape($recordStatus !== '' ? $recordStatus : '-'); ?></span><?php if ($recordStatus === 'VOID' && !empty($record['void_notes'])): ?><div class="small text-muted"><?php echo html_escape((string)$record['void_notes']); ?></div><?php endif; ?></td><td><?php echo html_escape((string)($record['voided_at'] ?? $record['posted_at'] ?? $record['created_at'] ?? '-')); ?></td><td><?php if ($recordStatus === 'POSTED' && $canVoid): ?><form method="post" action="<?php echo site_url('inventory/stock/value-reconciliation/void'); ?>" onsubmit="var n=window.prompt('Alasan VOID koreksi ini:','Salah input HPP, akan dikoreksi ulang'); if(n===null || n.trim()===''){return false;} this.elements.void_notes.value=n; return window.confirm('VOID dokumen ini dan pulihkan nilai sebelum koreksi?');"><input type="hidden" name="<?php echo html_escape($mutationCsrfName); ?>" value="<?php echo html_escape($mutationCsrfValue); ?>"><input type="hidden" name="revaluation_id" value="<?php echo (int)($record['id'] ?? 0); ?>"><input type="hidden" name="month" value="<?php echo html_escape($candidateMonth); ?>"><input type="hidden" name="void_notes" value=""><button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2">Void</button></form><?php else: ?><span class="small text-muted">-</span><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
</div>

<script>
(function () {
  var mode = document.getElementById('resolutionMode');
  var help = document.getElementById('resolutionHelp');
  var wrap = document.getElementById('manualValueWrap');
  var input = document.getElementById('manualTotalValue');
  if (!mode || !help || !wrap || !input) return;
  var unitInput = document.getElementById('manualUnitCost');
  var calcHint = document.getElementById('manualCalcHint');
  var stockQty = <?php echo json_encode((float)($context['stock_qty'] ?? 0)); ?>;
  var messages = {
    LOT_TO_STOCK: 'Nilai lot OPEN dianggap benar. Sistem hanya menyamakan nilai saldo stok bulan aktif dengan total nilai lot OPEN.',
    STOCK_TO_LOT: 'Nilai stok sistem dianggap benar. Sistem membagi ulang biaya lot OPEN secara proporsional agar totalnya sama dengan nilai stok.',
    MANUAL_TOTAL_VALUE: 'Gunakan hanya bila total nilai sudah diverifikasi dari dokumen yang benar. Sistem menyamakan saldo stok dan lot OPEN ke total tersebut.'
  };
  function syncMode() {
    var value = mode.value || 'LOT_TO_STOCK';
    help.textContent = messages[value] || messages.LOT_TO_STOCK;
    var manual = value === 'MANUAL_TOTAL_VALUE';
    wrap.style.display = manual ? '' : 'none';
    input.required = manual && (!unitInput || unitInput.value === '');
    if (unitInput) unitInput.required = false;
    if (!manual) {
      input.value = '';
      if (unitInput) unitInput.value = '';
    }
  }
  function syncUnitCost() {
    if (!unitInput || !input || mode.value !== 'MANUAL_TOTAL_VALUE') return;
    var unit = parseFloat(unitInput.value);
    if (!isNaN(unit) && unit >= 0 && stockQty > 0) {
      input.value = (stockQty * unit).toFixed(2);
      input.readOnly = true;
      input.required = false;
      if (calcHint) calcHint.textContent = 'Total otomatis dari ' + stockQty.toFixed(4) + ' unit × HPP/unit.';
    } else {
      input.readOnly = false;
      input.required = true;
      if (calcHint) calcHint.textContent = 'Boleh isi total langsung bila HPP/unit belum diketahui.';
    }
  }
  if (unitInput) unitInput.addEventListener('input', syncUnitCost);
  input.addEventListener('input', function () {
    if (unitInput && input.value !== '') unitInput.value = '';
    input.readOnly = false;
    input.required = mode.value === 'MANUAL_TOTAL_VALUE';
  });
  document.querySelectorAll('.hpp-suggestion-btn').forEach(function (button) {
    button.addEventListener('click', function () {
      mode.value = button.getAttribute('data-resolution-mode') || 'MANUAL_TOTAL_VALUE';
      syncMode();
      if (unitInput && mode.value === 'MANUAL_TOTAL_VALUE') {
        unitInput.value = button.getAttribute('data-hpp-value') || '';
        syncUnitCost();
        unitInput.focus();
      }
    });
  });
  mode.addEventListener('change', syncMode);
  syncMode();
}());
</script>
