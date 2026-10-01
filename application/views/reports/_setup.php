<?php
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$money = static fn($v) => Report_workspace::money($v);
$number = static fn($v) => Report_workspace::number($v);
$f = $filters;
$url = static function(array $change = []) use ($f,$base): string {
    $query = array_replace($f,$change);
    unset($query['from'],$query['to']);
    $query = array_filter($query,static fn($v)=>$v!=='' && $v!==null && $v!==0);
    return site_url($base).'?'.http_build_query($query);
};
$json = static fn($v) => json_encode($v,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
$selected = static fn($a,$b) => (string)$a===(string)$b?' selected':'';
?>
<link rel="stylesheet" href="<?= $esc(base_url('assets/css/report-workspace.css?v=20261001g')) ?>">
<script src="<?= $esc(base_url('assets/js/report-workspace.js?v=20261001g')) ?>" defer></script>
