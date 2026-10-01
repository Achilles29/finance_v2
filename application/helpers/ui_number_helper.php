<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('ui_num')) {
    function ui_num($value, int $decimals = 2, string $decimalPoint = ',', string $thousandsSep = '.'): string
    {
        return number_format((float)$value, $decimals, $decimalPoint, $thousandsSep);
    }
}
