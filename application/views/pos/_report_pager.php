<?php
$meta = is_array($meta ?? null) ? $meta : [];
$pagerPath = trim((string)($pager_path ?? ''));
$filters = is_array($filters ?? null) ? $filters : [];
$page = max(1, (int)($meta['page'] ?? 1));
$totalPages = max(1, (int)($meta['total_pages'] ?? 1));
$total = max(0, (int)($meta['total'] ?? 0));
$limit = max(1, (int)($meta['limit'] ?? 25));
$start = $total > 0 ? (($page - 1) * $limit) + 1 : 0;
$end = $total > 0 ? min($total, $page * $limit) : 0;
if ($pagerPath !== '' && $totalPages > 1):
    $baseQuery = $filters;
    unset($baseQuery['page']);
    $firstPage = max(1, $page - 2);
    $lastPage = min($totalPages, $page + 2);
?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <div class="pos-report-meta">Menampilkan <?php echo number_format($start); ?>-<?php echo number_format($end); ?> dari <?php echo number_format($total); ?> data</div>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php $prevQuery = $baseQuery; $prevQuery['page'] = max(1, $page - 1); ?>
        <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
          <a class="page-link" href="<?php echo $page <= 1 ? '#' : site_url($pagerPath) . '?' . http_build_query($prevQuery); ?>">Prev</a>
        </li>
        <?php for ($i = $firstPage; $i <= $lastPage; $i++): ?>
          <?php $pageQuery = $baseQuery; $pageQuery['page'] = $i; ?>
          <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
            <a class="page-link" href="<?php echo site_url($pagerPath) . '?' . http_build_query($pageQuery); ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>
        <?php $nextQuery = $baseQuery; $nextQuery['page'] = min($totalPages, $page + 1); ?>
        <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
          <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : site_url($pagerPath) . '?' . http_build_query($nextQuery); ?>">Next</a>
        </li>
      </ul>
    </nav>
  </div>
<?php elseif ($pagerPath !== ''): ?>
  <div class="pos-report-meta mt-3">Total data: <?php echo number_format($total); ?></div>
<?php endif; ?>