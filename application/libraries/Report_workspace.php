<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Shared read-only report filters and aggregation. Money is summed in cents. */
class Report_workspace
{
    public static function month(string $month): array
    {
        if (!preg_match('/\A(20[0-9]{2})-(0[1-9]|1[0-2])\z/D', $month)) {
            throw new InvalidArgumentException('Bulan tidak valid. Gunakan format YYYY-MM.');
        }
        return [$month . '-01', date('Y-m-t', strtotime($month . '-01'))];
    }

    public static function filters(array $input): array
    {
        foreach ($input as $value) {
            if (!is_scalar($value) && $value !== null) { throw new InvalidArgumentException('Filter tidak valid.'); }
        }
        $month = trim((string)($input['month'] ?? date('Y-m')));
        [$from, $to] = self::month($month);
        $compare = trim((string)($input['compare'] ?? date('Y-m', strtotime($from . ' -1 month'))));
        self::month($compare);
        $day = trim((string)($input['day'] ?? ''));
        if ($day !== '' && (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $day) || $day < $from || $day > $to)) {
            throw new InvalidArgumentException('Tanggal rincian harus berada dalam bulan laporan.');
        }
        $f = ['month' => $month, 'compare' => $compare, 'from' => $from, 'to' => $to, 'day' => $day];
        foreach (['q', 'destination', 'status', 'source', 'category', 'direction', 'currency', 'basis', 'view', 'profile'] as $key) {
            $f[$key] = trim((string)($input[$key] ?? ''));
            if (strlen($f[$key]) > ($key === 'q' ? 150 : 80)) { throw new InvalidArgumentException('Filter terlalu panjang.'); }
        }
        $f['currency'] = $f['currency'] ?: 'IDR';
        if (!preg_match('/\A[A-Z]{3}\z/D', $f['currency'])) { throw new InvalidArgumentException('Mata uang tidak valid.'); }
        $f['view'] = in_array($f['view'], ['overview', 'matrix', 'detail'], true) ? $f['view'] : 'overview';
        $f['basis'] = $f['basis'] === 'request' ? 'request' : 'fulfillment';
        foreach (['division_id', 'account_id'] as $key) { $f[$key] = max(0, (int)($input[$key] ?? 0)); }
        $f['page'] = max(1, min(100000, (int)($input['page'] ?? 1)));
        $f['per_page'] = in_array((int)($input['per_page'] ?? 0), [25, 50, 100], true) ? (int)$input['per_page'] : 50;
        return $f;
    }

    public static function window(array $f): array
    {
        return [date('Y-m-01', strtotime($f['from'] . ' -5 months')), $f['to'], $f['compare'] . '-01', self::month($f['compare'])[1]];
    }

    public static function financialFilters(array $input): array
    {
        foreach ($input as $value) {
            if (!is_scalar($value) && $value !== null) { throw new InvalidArgumentException('Filter tidak valid.'); }
        }
        $end = trim((string)($input['month_to'] ?? $input['month'] ?? date('Y-m')));
        self::month($end);
        $start = trim((string)($input['month_from'] ?? date('Y-m', strtotime($end . '-01 -5 months'))));
        self::month($start);
        $span = ((int)substr($end,0,4) - (int)substr($start,0,4))*12 + (int)substr($end,5,2) - (int)substr($start,5,2);
        if ($span < 0 || $span > 11) { throw new InvalidArgumentException('Pilih rentang berurutan, maksimal 12 bulan.'); }
        $focus = trim((string)($input['month'] ?? $end));
        self::month($focus);
        if ($focus < $start || $focus > $end) { $focus = $end; unset($input['day']); }
        // Inventory snapshots and efficiency ratios always describe whole months.
        if (in_array($input['view'] ?? '', ['efficiency','waste'], true)) { unset($input['day']); }
        $f = self::filters(array_replace($input, ['month'=>$focus,'compare'=>date('Y-m',strtotime($focus.'-01 -1 month'))]));
        $f['month_from'] = $start;
        $f['month_to'] = $end;
        $f['flow'] = trim((string)($input['flow'] ?? ''));
        if (strlen($f['flow']) > 40) { throw new InvalidArgumentException('Filter arus terlalu panjang.'); }
        if (in_array($input['view'] ?? '', ['sales','reconcile','efficiency','waste','balances'], true)) { $f['view'] = $input['view']; }
        foreach (['location'=>['ALL','REGULAR','EVENT'],'inventory_kind'=>['ALL','MATERIAL','COMPONENT'],
            'loss_kind'=>['ALL','WASTE','SPOIL','PROCESS_LOSS','VARIANCE','ADJUSTMENT_MINUS']] as $key=>$allowed) {
            $value=strtoupper(trim((string)($input[$key]??'ALL')));
            if (!in_array($value,$allowed,true)) { throw new InvalidArgumentException('Filter analisis persediaan tidak valid.'); }
            $f[$key]=$value;
        }
        $f['asset_q']=trim((string)($input['asset_q']??''));
        if (strlen($f['asset_q'])>150) { throw new InvalidArgumentException('Pencarian bahan terlalu panjang.'); }
        return $f;
    }

    public static function monthLabel(string $month): string
    {
        $names=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        return $names[(int)substr($month,5,2)-1].' '.substr($month,0,4);
    }

    public static function monthOptions(array $f, ?string $first=null): array
    {
        $start=max('2000-01',min($first?:date('Y-m'),$f['month_from'],date('Y-m',strtotime(date('Y-m-01').' -11 months'))));
        $end=max(date('Y-m'),$f['month_to']); $result=[];
        for($month=$end;$month>=$start;$month=date('Y-m',strtotime($month.'-01 -1 month'))) { $result[$month]=self::monthLabel($month); }
        return $result;
    }

    public static function financialMonths(array $f): array
    {
        $months = [];
        for ($month=$f['month_from']; $month<=$f['month_to']; $month=date('Y-m',strtotime($month.'-01 +1 month'))) {
            $months[] = $month;
        }
        return $months;
    }

    public static function cents($value): int { return (int)round((float)$value * 100); }
    public static function money($cents): string { return number_format((float)$cents / 100, 2, ',', '.'); }
    public static function number($value): string { return number_format((float)$value, 2, ',', '.'); }

    public static function change(int $current, int $previous): string
    {
        if ($previous === 0) { return $current === 0 ? 'Tidak berubah' : 'Belum ada basis pembanding'; }
        $percent = ($current - $previous) / abs($previous) * 100;
        return ($percent > 0 ? 'Naik ' : ($percent < 0 ? 'Turun ' : 'Tetap ')) . number_format(abs($percent), 1, ',', '.') . '%';
    }

    public static function page(array $rows, array $f): array
    {
        $count = count($rows);
        $pages = max(1, (int)ceil($count / $f['per_page']));
        $page = min($pages, $f['page']);
        return ['count' => $count, 'pages' => $pages, 'page' => $page, 'per_page' => $f['per_page'],
            'rows' => array_slice($rows, ($page - 1) * $f['per_page'], $f['per_page'])];
    }

    public static function csvCell($value): string
    {
        $text = (string)$value;
        // Neutralize spreadsheet formulas, including whitespace-prefixed input.
        return preg_match('/\A[\x00-\x20]*[=+@-]/', $text) ? "'" . $text : $text;
    }

    public static function export(string $filename, array $columns, array $rows, array $numericFields = []): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: private, no-store');
        $stream = fopen('php://output', 'w');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_values($columns), ';', '"', '');
        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $field => $_) {
                $value = (string)($row[$field] ?? '');
                $numeric = in_array($field, array_merge(['qty_buy','qty_content','fulfilled_qty','pending_qty','unit_cost','value','amount','billing','tax','refund'], $numericFields), true);
                // Indonesian Excel uses a decimal comma with semicolon CSV.
                // Only allowlisted numeric fields bypass formula neutralization.
                $cells[] = $numeric && preg_match('/\A-?\d+(?:\.\d+)?\z/D', $value) ? str_replace('.', ',', $value) : self::csvCell($value);
            }
            fputcsv($stream, $cells, ';', '"', '');
        }
        fclose($stream);
    }
}
