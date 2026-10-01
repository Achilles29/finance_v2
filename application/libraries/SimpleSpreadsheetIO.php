<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class SimpleSpreadsheetIO
{
    public function parse_file(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return ['ok' => false, 'message' => 'File XLSX tidak tersedia.'];
        }
        return $this->parse_xlsx($path);
    }

    public function parse_uploaded_file(string $fieldName): array
    {
        if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
            return ['ok' => false, 'message' => 'File import belum dipilih.'];
        }

        $file = $_FILES[$fieldName];
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => $this->upload_error_message((int)($file['error'] ?? UPLOAD_ERR_NO_FILE))];
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return ['ok' => false, 'message' => 'File upload tidak ditemukan di server.'];
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            return ['ok' => false, 'message' => 'Format file harus XLSX.'];
        }

        return $this->parse_xlsx($tmpPath);
    }

    public function output_xlsx(string $filename, array $headers, array $rows, string $sheetName = 'Sheet1', bool $typedCells = false): void
    {
        if (!class_exists('ZipArchive')) {
            echo 'Ekstensi ZipArchive tidak tersedia untuk membuat XLSX.';
            return;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $binary = $this->build_xlsx_binary($headers, $rows, $sheetName, $typedCells);
        if ($binary === null) {
            echo 'Gagal membentuk file XLSX.';
            return;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . strlen($binary));

        echo $binary;
    }

    public function build_xlsx_binary(array $headers, array $rows, string $sheetName = 'Sheet1', bool $typedCells = false): ?string
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmpPath === false) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpPath);
            return null;
        }

        $sheetTitle = $this->xlsx_safe_sheet_name($sheetName);
        $zip->addFromString('[Content_Types].xml', $this->xlsx_content_types_xml());
        $zip->addFromString('_rels/.rels', $this->xlsx_root_rels_xml());
        $zip->addFromString('docProps/app.xml', $this->xlsx_app_xml($sheetTitle));
        $zip->addFromString('docProps/core.xml', $this->xlsx_core_xml());
        $zip->addFromString('xl/workbook.xml', $this->xlsx_workbook_xml($sheetTitle));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsx_workbook_rels_xml());
        $zip->addFromString('xl/styles.xml', $this->xlsx_styles_xml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsx_sheet_xml($headers, $rows, $typedCells));
        $zip->close();

        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);
        return $binary === false ? null : $binary;
    }

    private function parse_csv(string $tmpPath): array
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            return ['ok' => false, 'message' => 'File CSV tidak bisa dibaca.'];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return ['ok' => false, 'message' => 'File CSV kosong.'];
        }
        $delimiter = $this->detect_csv_delimiter($firstLine);
        rewind($handle);

        $headers = null;
        $rows = [];
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($headers === null) {
                $headers = $this->normalize_headers($line);
                continue;
            }
            if ($this->row_is_empty($line)) {
                continue;
            }
            $rows[] = $this->combine_row($headers, $line);
        }
        fclose($handle);

        if ($headers === null) {
            return ['ok' => false, 'message' => 'Header CSV tidak ditemukan.'];
        }

        return ['ok' => true, 'headers' => $headers, 'rows' => $rows];
    }

    private function parse_xlsx(string $tmpPath): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'Ekstensi ZipArchive tidak tersedia untuk membaca XLSX.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return ['ok' => false, 'message' => 'File XLSX tidak bisa dibuka.'];
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            $zip->close();
            return ['ok' => false, 'message' => 'Struktur XLSX tidak valid.'];
        }

        $sharedStrings = $this->read_shared_strings($zip);
        $sheetPath = $this->resolve_first_sheet_path($workbookXml, $relsXml);
        if ($sheetPath === null) {
            $zip->close();
            return ['ok' => false, 'message' => 'Sheet pertama XLSX tidak ditemukan.'];
        }

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            return ['ok' => false, 'message' => 'Konten sheet XLSX tidak ditemukan.'];
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return ['ok' => false, 'message' => 'Sheet XLSX tidak bisa dibaca.'];
        }

        $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $sheet->xpath('//a:sheetData/a:row');
        if (!$rowNodes) {
            return ['ok' => false, 'message' => 'Sheet XLSX kosong.'];
        }

        $headers = null;
        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $parsedRow = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string)($cell['r'] ?? '');
                $index = $this->column_index_from_ref($ref);
                $parsedRow[$index] = $this->read_xlsx_cell_value($cell, $sharedStrings);
            }
            if (empty($parsedRow)) {
                continue;
            }
            ksort($parsedRow);
            $flatRow = [];
            $maxIndex = max(array_keys($parsedRow));
            for ($i = 0; $i <= $maxIndex; $i++) {
                $flatRow[] = array_key_exists($i, $parsedRow) ? $parsedRow[$i] : '';
            }

            if ($headers === null) {
                $headers = $this->normalize_headers($flatRow);
                continue;
            }
            if ($this->row_is_empty($flatRow)) {
                continue;
            }
            $rows[] = $this->combine_row($headers, $flatRow);
        }

        if ($headers === null) {
            return ['ok' => false, 'message' => 'Header XLSX tidak ditemukan.'];
        }

        return ['ok' => true, 'headers' => $headers, 'rows' => $rows];
    }

    private function read_shared_strings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = @simplexml_load_string($xml);
        if ($shared === false) {
            return [];
        }

        $strings = [];
        foreach ($shared->si as $item) {
            if (isset($item->t)) {
                $strings[] = $this->clean_value((string)$item->t);
                continue;
            }
            $parts = [];
            foreach ($item->r as $run) {
                $parts[] = isset($run->t) ? (string)$run->t : '';
            }
            $strings[] = $this->clean_value(implode('', $parts));
        }
        return $strings;
    }

    private function resolve_first_sheet_path(string $workbookXml, string $relsXml): ?string
    {
        $workbook = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        if ($workbook === false || $rels === false) {
            return null;
        }

        $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $sheetNodes = $workbook->xpath('//a:sheets/a:sheet');
        if (!$sheetNodes || empty($sheetNodes[0])) {
            return null;
        }

        $relationId = (string)$sheetNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        if ($relationId === '') {
            return null;
        }

        foreach ($rels->Relationship as $relation) {
            if ((string)($relation['Id'] ?? '') !== $relationId) {
                continue;
            }
            $target = str_replace('\\', '/', (string)($relation['Target'] ?? ''));
            if ($target === '') {
                return null;
            }
            if (strpos($target, 'xl/') === 0) {
                return $target;
            }
            return 'xl/' . ltrim($target, '/');
        }

        return null;
    }

    private function read_xlsx_cell_value(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string)($cell['t'] ?? '');
        $value = isset($cell->v) ? (string)$cell->v : '';
        if ($type === 's') {
            $index = (int)$value;
            return $this->clean_value($sharedStrings[$index] ?? '');
        }
        if ($type === 'inlineStr' && isset($cell->is->t)) {
            return $this->clean_value((string)$cell->is->t);
        }
        return $this->clean_value($value);
    }

    private function column_index_from_ref(string $ref): int
    {
        if (!preg_match('/^[A-Z]+/i', $ref, $matches)) {
            return 0;
        }
        $letters = strtoupper($matches[0]);
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    private function detect_csv_delimiter(string $firstLine): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;
        foreach ($delimiters as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $delimiter;
            }
        }
        return $best;
    }

    private function normalize_headers(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $index => $header) {
            $value = strtolower(trim($this->clean_value((string)$header)));
            $value = preg_replace('/[^a-z0-9]+/', '_', $value ?? '');
            $value = trim((string)$value, '_');
            $normalized[] = $value !== '' ? $value : ('column_' . $index);
        }
        return $normalized;
    }

    private function combine_row(array $headers, array $row): array
    {
        $combined = [];
        foreach ($headers as $index => $header) {
            $combined[$header] = $this->clean_value((string)($row[$index] ?? ''));
        }
        return $combined;
    }

    private function row_is_empty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($this->clean_value((string)$value)) !== '') {
                return false;
            }
        }
        return true;
    }

    private function clean_value(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return trim($value);
    }

    private function upload_error_message(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Ukuran file upload terlalu besar.';
            case UPLOAD_ERR_PARTIAL:
                return 'File upload tidak terkirim penuh.';
            case UPLOAD_ERR_NO_FILE:
                return 'File import belum dipilih.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Folder temporary upload tidak tersedia.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'File upload gagal ditulis ke server.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload dihentikan oleh ekstensi PHP.';
            default:
                return 'Upload file gagal diproses.';
        }
    }

    private function xlsx_content_types_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function xlsx_root_rels_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function xlsx_app_xml(string $sheetTitle): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>GitHub Copilot</Application>'
            . '<DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $this->xml_escape($sheetTitle) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company></Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>1.0</AppVersion>'
            . '</Properties>';
    }

    private function xlsx_core_xml(): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>GitHub Copilot</dc:creator>'
            . '<cp:lastModifiedBy>GitHub Copilot</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function xlsx_workbook_xml(string $sheetTitle): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xml_escape($sheetTitle) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xlsx_workbook_rels_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsx_styles_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4E7D3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function xlsx_sheet_xml(array $headers, array $rows, bool $typedCells = false): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<sheetData>';

        $xml .= '<row r="1">';
        foreach (array_values($headers) as $index => $header) {
            $xml .= $this->xlsx_inline_string_cell($index, 1, (string)$header, 1);
        }
        $xml .= '</row>';

        $rowNumber = 2;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNumber . '">';
            foreach (array_values($headers) as $index => $header) {
                $value = array_key_exists($header, $row) ? $row[$header] : '';
                if ($typedCells && (is_int($value) || (is_float($value) && is_finite($value)))) {
                    $xml .= '<c r="' . $this->xlsx_column_name($index) . $rowNumber . '" t="n"><v>' . json_encode($value) . '</v></c>';
                } elseif ($typedCells && is_bool($value)) {
                    $xml .= '<c r="' . $this->xlsx_column_name($index) . $rowNumber . '" t="b"><v>' . ($value ? '1' : '0') . '</v></c>';
                } else {
                    $xml .= $this->xlsx_inline_string_cell($index, $rowNumber, (string)$value, 0);
                }
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function xlsx_inline_string_cell(int $columnIndex, int $rowNumber, string $value, int $styleIndex): string
    {
        return '<c r="' . $this->xlsx_column_name($columnIndex) . $rowNumber . '" t="inlineStr" s="' . $styleIndex . '"><is><t>'
            . $this->xml_escape($value)
            . '</t></is></c>';
    }

    private function xlsx_column_name(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = (int)(($index - $mod) / 26);
        }
        return $name;
    }

    private function xlsx_safe_sheet_name(string $sheetName): string
    {
        $clean = preg_replace('~[\\/*?:\[\]]+~', ' ', $sheetName) ?? $sheetName;
        $clean = trim($clean);
        if ($clean === '') {
            $clean = 'Sheet1';
        }
        return mb_substr($clean, 0, 31);
    }

    private function xml_escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
