<?php
$name = strtolower(trim((string)($name ?? 'info')));
$label = (string)($label ?? '');
$paths = [
    'arrow-left' => '<path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path>',
    'save' => '<path d="M5 3h12l2 2v16H5z"></path><path d="M8 3v6h8V3"></path><path d="M8 21v-7h8v7"></path>',
    'print' => '<path d="M6 9V3h12v6"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v7H6z"></path><path d="M9 18h6"></path>',
    'plus' => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
    'edit' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path>',
    'power' => '<path d="M12 2v10"></path><path d="M18.4 5.6a8 8 0 1 1-12.8 0"></path>',
    'search' => '<circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path>',
    'clear' => '<path d="M3 3l18 18"></path><path d="M16 3h5v5"></path><path d="M21 3l-5.5 5.5"></path><path d="M3 8h5"></path><path d="M3 13h4"></path><path d="M3 18h9"></path>',
    'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path><circle cx="12" cy="12" r="2.5"></circle>',
];
?>
<svg class="print-config-inline-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $paths[$name] ?? $paths['eye'] ?></svg><?php if ($label !== ''): ?><span class="visually-hidden"><?= html_escape($label) ?></span><?php endif; ?>
