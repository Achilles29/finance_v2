<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Pos.php');
$model = (string)file_get_contents($root . '/application/models/Pos_print_model.php');
$view = (string)file_get_contents($root . '/application/views/pos/printer_general_index.php');
$previewService = (string)file_get_contents($root . '/application/libraries/PosPrinterPreviewService.php');
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$methodStart = strpos($controller, 'public function printer_general()');
$methodEnd = $methodStart === false ? false : strpos($controller, "\n    public function ", $methodStart + 1);
$printerGeneral = $methodStart === false ? '' : substr($controller, $methodStart, $methodEnd === false ? null : $methodEnd - $methodStart);

$check($controller !== '' && $model !== '' && $view !== '' && $previewService !== '', 'printer logo source files are readable');
$check($printerGeneral !== '' && strpos($printerGeneral, "require_printer_config_permission('pos.printer.general', 'edit')") !== false, 'printer general save retains exact edit permission');
$check(
    $printerGeneral !== ''
    && strpos($printerGeneral, 'require_printer_general_csrf()') !== false
    && strpos($printerGeneral, 'store_printer_general_logo_upload()') !== false
    && strpos($printerGeneral, 'require_printer_general_csrf()') < strpos($printerGeneral, 'store_printer_general_logo_upload()'),
    'printer general validates scoped form CSRF before accepting an upload'
);
$check(
    strpos($controller, "private const POS_PRINTER_GENERAL_CSRF_FORM_FIELD = 'pos_printer_general_csrf';") !== false
    && strpos($controller, 'private function require_printer_general_csrf(): bool') !== false
    && strpos($controller, "input->post(self::POS_PRINTER_GENERAL_CSRF_FORM_FIELD") !== false,
    'printer general uses a session-scoped form token independent of POS transaction CSRF'
);
$check(
    strpos($controller, "'allowed_types' => 'png|jpg|jpeg'") !== false
    && strpos($controller, "'max_size' => 1024") !== false
    && strpos($controller, "'max_width' => 2048") !== false
    && strpos($controller, "'max_height' => 2048") !== false
    && strpos($controller, 'getimagesize($fullPath)') !== false,
    'upload accepts only bounded PNG/JPG files and verifies image content'
);
$check(
    strpos($controller, "assets/uploads/pos-printer-logo/") !== false
    && strpos($controller, "base_url('assets/uploads/pos-printer-logo/'") !== false,
    'uploaded printer logo is stored in an application-managed location'
);
$check(
    strpos($view, 'enctype="multipart/form-data"') !== false
    && strpos($view, 'name="pos_printer_general_csrf"') !== false
    && strpos($view, 'name="logo_file"') !== false
    && strpos($view, 'accept="image/png,image/jpeg"') !== false
    && strpos($view, 'id="printer-general-logo-preview"') !== false,
    'General Printer UI renders multipart file input, scoped form token, and logo preview'
);
$check(
    strpos($view, 'name="logo_url"') === false
    && strpos($view, 'Bila tidak memilih file, logo saat ini tetap dipakai.') !== false
    && strpos($view, 'window.URL.createObjectURL(file)') !== false
    && strpos($view, 'window.URL.revokeObjectURL(previewUrl)') !== false,
    'General Printer UI removes free-form URL, previews a selected file, and preserves current logo without a new file'
);
$check(
    strpos($model, 'public function default_logo_url(): string') !== false
    && strpos($model, 'private function is_managed_logo_url(string $logoUrl): bool') !== false
    && strpos($model, '(?:pos-printer-logo|business-profile-logo)/[a-f0-9]{32}') !== false
    && strpos($model, 'return $this->is_managed_logo_url($logoUrl) ? $logoUrl : $this->default_logo_url();') !== false,
    'stored logo configuration accepts only verified application-managed paths'
);
$check(
    strpos($previewService, 'Direct-print agents must not fetch an arbitrary remote URL') !== false
    && strpos($previewService, 'return base_url(\'assets/img/logo.png\');') !== false,
    'printer preview service has a safe fallback for legacy or remote logo URLs'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " printer General logo upload check(s) failed.\n");
    exit(1);
}

echo 'All ' . $checks . " printer General logo upload checks passed.\n";
