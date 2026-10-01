<?php
$title = (string)($title ?? 'Cetak Pengajuan Divisi');
$lineRows = (array)($line_rows ?? []);
$filters = (array)($filters ?? []);
$backUrl = (string)($back_url ?? site_url('procurement/division-po-sr'));
$printedAt = (string)($printed_at ?? date('Y-m-d H:i:s'));
$showPrintControls = !isset($show_print_controls) || !empty($show_print_controls);
$pdfMode = !empty($pdf_mode);
$selectedDate = (string)($filters['date_start'] ?? '');
if ($selectedDate === '' && !empty($lineRows)) {
    $selectedDate = (string)($lineRows[0]['needed_date'] ?? '');
}
if ($selectedDate === '') {
    $selectedDate = date('Y-m-d');
}

if (!function_exists('finance_dreq_print_route_text')) {
    function finance_dreq_print_route_text($qtyToSr, $qtyToPo)
    {
        $qtyToSr = (float)$qtyToSr;
        $qtyToPo = (float)$qtyToPo;
        if ($qtyToSr > 0 && $qtyToPo > 0) {
            return 'SR + PO';
        }
        if ($qtyToSr > 0) {
            return 'SR';
        }
        return 'PO';
    }
}

$groupedRows = [];
foreach ($lineRows as $line) {
    $requestId = (int)($line['request_id'] ?? 0);
    $requestNo = trim((string)($line['request_no'] ?? '-'));
    $divisionName = trim((string)($line['division_name'] ?? '-'));
    $destinationType = strtoupper(trim((string)($line['destination_type'] ?? '')));
    $destinationLabel = $destinationType !== '' ? str_replace('_', ' ', $destinationType) : '-';
    $groupKey = $requestId . '|' . $divisionName . '|' . $destinationType;
    if (!isset($groupedRows[$groupKey])) {
        $groupedRows[$groupKey] = [
            'request_no' => $requestNo !== '' ? $requestNo : '-',
            'header' => trim($divisionName . ' - ' . $destinationLabel, ' -'),
            'items' => [],
        ];
    }

    $requestUomMode = strtoupper(trim((string)($line['request_uom_mode'] ?? 'BUY')));
    if ($requestUomMode === 'CONTENT') {
        $qty = (float)($line['qty_content_requested'] ?? 0);
        $uom = trim((string)($line['profile_content_uom_code'] ?? ''));
    } else {
      $qty = (float)($line['qty_buy_requested'] ?? 0);
      $uom = trim((string)($line['profile_buy_uom_code'] ?? ''));
    }
    if ($qty <= 0 || $uom === '') {
      $qty = (float)($line['qty_content_requested'] ?? 0);
      $uom = trim((string)($line['profile_content_uom_code'] ?? ''));
    }

    $description = trim((string)($line['line_notes'] ?? ''));
    if ($description === '') {
        $description = trim((string)($line['profile_description'] ?? ''));
    }
    if ($description === '') {
        $description = '-';
    }

    $groupedRows[$groupKey]['items'][] = [
        'barang' => (string)($line['profile_name'] ?? '-'),
        'stock_line' => $line,
        'qty' => $qty,
        'uom' => $uom !== '' ? $uom : '-',
        'keterangan' => $description,
        'route' => finance_dreq_print_route_text((float)($line['qty_content_to_sr'] ?? 0), (float)($line['qty_content_to_po'] ?? 0)),
    ];
}
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Trebuchet MS", "Segoe UI", sans-serif; color: #2e211a; background: #f6f1ec; }
    .print-bar { position: sticky; top: 0; z-index: 10; display: flex; gap: 10px; justify-content: center; padding: 10px 14px; background: rgba(246,241,236,.96); border-bottom: 1px solid #e3d5c8; }
    .btn { border: 1px solid transparent; border-radius: 999px; padding: 9px 15px; text-decoration: none; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; color: inherit; }
    .btn-primary { background: #9f172a; color: #fff; }
    .btn-light { background: #fff; color: #5b4335; border-color: #ddcec0; }
    .paper { max-width: 210mm; margin: 18px auto; background: #fff; border-radius: 18px; box-shadow: 0 18px 42px rgba(90,64,48,.12); overflow: hidden; }
    .doc-head { padding: 24px 26px 18px; border-bottom: 2px solid #9f172a; }
    .doc-title { margin: 0 0 6px; font-size: 28px; line-height: 1.1; color: #7f1020; }
    .doc-subtitle { margin: 0; font-size: 14px; color: #6d5446; }
    .doc-meta { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 10px 18px; font-size: 12px; color: #5a4539; }
    .doc-date { margin-top: 18px; padding: 14px 16px; background: #fff6ef; border: 1px solid #f1ddd0; border-radius: 14px; }
    .doc-date small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #9a6e58; }
    .doc-date strong { display: block; margin-top: 4px; font-size: 24px; color: #2f1c13; }
    .content { padding: 18px 26px 26px; }
    .group { margin-bottom: 18px; border: 1px solid #eaded4; border-radius: 16px; overflow: hidden; }
    .group-head { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; padding: 14px 16px; background: #fff9f5; border-bottom: 1px solid #efe1d6; }
    .group-head small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #9a6e58; }
    .group-head strong { display: block; margin-top: 4px; font-size: 16px; color: #2f1c13; }
    .group-head .right { text-align: right; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { overflow-wrap: anywhere; word-wrap: break-word; }
    thead { display: table-header-group; }
    tr { break-inside: avoid; page-break-inside: avoid; }
    thead th { padding: 8px 6px; background: #9f172a; color: #fff; font-size: 10px; text-align: left; }
    tbody td { padding: 8px 6px; border-bottom: 1px solid #f1e6de; font-size: 11px; vertical-align: top; }
    tbody tr:last-child td { border-bottom: 0; }
    .text-end { text-align: right; white-space: nowrap; }
    .muted { color: #7a6154; }
    .empty { padding: 28px 18px; text-align: center; color: #8a7264; }
    .foot { padding: 0 26px 24px; font-size: 11px; color: #84695b; }
    @page { size: A4 portrait; margin: 10mm; }
    @media print {
      body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .print-bar { display: none !important; }
      .paper { margin: 0 auto; box-shadow: none; border-radius: 0; max-width: none; }
    }
  </style>
</head>
<body>
  <?php if ($showPrintControls): ?>
    <div class="print-bar">
      <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
      <a class="btn btn-light" href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>">Kembali ke List</a>
    </div>
  <?php endif; ?>

  <div class="paper">
    <div class="doc-head">
      <h1 class="doc-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="doc-subtitle">Ringkasan pengajuan divisi per tanggal butuh, dikelompokkan per request dan lokasi stok.</p>
      <div class="doc-meta">
        <span>Patokan cetak: <strong>Tanggal Butuh</strong></span>
        <span>Dicetak: <?php echo htmlspecialchars($printedAt, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <div class="doc-date">
        <small>Tanggal Butuh</small>
        <strong><?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?></strong>
      </div>
    </div>

    <div class="content">
      <?php if (empty($groupedRows)): ?>
        <div class="group">
          <div class="empty">Tidak ada pengajuan divisi untuk tanggal butuh yang dipilih.</div>
        </div>
      <?php else: ?>
        <?php foreach ($groupedRows as $group): ?>
          <div class="group">
            <div class="group-head">
              <div>
                <small>Kode Request</small>
                <strong><?php echo htmlspecialchars((string)($group['request_no'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
              <div class="right">
                <small>Divisi / Lokasi</small>
                <strong><?php echo htmlspecialchars((string)($group['header'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
            </div>
            <table>
              <thead>
                <tr>
                  <th style="width:22%">Barang</th>
                  <th style="width:11%" class="text-end">Diajukan</th>
                  <th style="width:7%">UOM</th>
                  <th style="width:32%">Stok sekarang (satuan isi)</th>
                  <th style="width:20%">Keterangan</th>
                  <th style="width:8%">Route</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ((array)($group['items'] ?? []) as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)($item['barang'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-end"><?php echo number_format((float)($item['qty'] ?? 0), 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars((string)($item['uom'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php $this->load->view('procurement/_current_stock', ['stock_line'=>$item['stock_line']]); ?></td>
                    <td class="muted"><?php echo htmlspecialchars((string)($item['keterangan'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($item['route'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="foot">
      Stok sekarang dibaca saat dokumen dicetak, bukan saldo pada tanggal pengajuan. Total bahan baku divisi/lokasi dan gudang dalam satuan isi; bukan hitung fisik atau reservasi. “Belum diketahui” tidak berarti nol.<br>
      <?php echo $pdfMode
        ? 'File PDF ini dibuat otomatis dari server dan disusun per tanggal butuh.'
        : 'Preview ini menampilkan susunan final PDF per tanggal butuh.'; ?>
    </div>
  </div>
</body>
</html>
