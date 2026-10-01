<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/finance_control_operations_cases.php';
require __DIR__.'/finance_control_workspace_cases.php';
$argv[]='--mysql-fixture';
require __DIR__.'/finance_mutation_reporting_smoke.php';
