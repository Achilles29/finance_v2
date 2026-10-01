<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read the two internal roadmap control tables without rendering Markdown.
 *
 * The public API deliberately accepts no path or document input. This keeps
 * the web surface tied to the reviewed files below and prevents path/query
 * selection from becoming a local-file disclosure primitive.
 */
class AuditRoadmapReader
{
    private const MAX_DOCUMENT_BYTES = 1048576;
    private const AUDIT_DOCUMENT = 'docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md';
    private const COMMERCIAL_DOCUMENT = 'docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md';
    private const UNAVAILABLE_MESSAGE = 'Ringkasan roadmap belum dapat ditampilkan saat ini.';

    /**
     * @return array<string,mixed>
     */
    public function read(): array
    {
        try {
            $audit = $this->readFixedDocument(self::AUDIT_DOCUMENT);
            $commercial = $this->readFixedDocument(self::COMMERCIAL_DOCUMENT);

            $phases = $this->parseTable(
                $this->extractSection($audit, '### 0.2 Status fase A0–A5'),
                ['Fase', 'Implementasi', 'Validasi tertinggi', 'Release/data', 'Status fase', 'Alasan/gerbang berikutnya']
            );
            $findings = $this->parseTable(
                $this->extractSection($audit, '### 0.3 Register temuan audit'),
                ['ID', 'Sumber', 'Prioritas/fase', 'Masalah', 'Solusi/acceptance', 'Implementasi', 'Validasi', 'Release/data', 'Bukti atau langkah berikutnya']
            );
            $uiWaves = $this->parseTable(
                $this->extractSection($audit, '### 0.4 Checklist rollout UI 8.3'),
                ['ID', 'Gelombang', 'Scope/acceptance', 'Implementasi', 'Validasi', 'Status nyata']
            );
            $sqlRegister = $this->parseTable(
                $this->extractSection($audit, '### 0.5 Register SQL staging dan server utama'),
                ['File SQL', 'Klasifikasi', 'Status staging', 'Bukti staging', 'Status server utama', 'Tindakan berikutnya']
            );
            $commercialPhases = $this->parseTable(
                $this->extractSection($commercial, '### 0.1 Status kanonis fase C0–C5'),
                ['Fase', 'Implementasi', 'Validasi tertinggi', 'Release/data', 'Status fase', 'Alasan/gerbang berikutnya']
            );

            $this->assertPhaseSequence($phases, 'A');
            $this->assertPhaseSequence($commercialPhases, 'C');
            $this->assertIdSequence($uiWaves, 'AUD-A3-UI-', 9);
            if (count($findings) < 1 || count($sqlRegister) < 1) {
                throw new RuntimeException('Canonical register is empty.');
            }

            $readiness = $this->deriveReadiness($phases, $commercialPhases);
            $readiness['total_findings'] = count($findings);

            return [
                'ok' => true,
                'message' => '',
                'readiness' => $readiness,
                'phases' => $phases,
                'findings' => $findings,
                'ui_waves' => $uiWaves,
                'sql_register' => $sqlRegister,
                'commercial_phases' => $commercialPhases,
            ];
        } catch (Throwable $exception) {
            return $this->unavailableResult();
        }
    }

    private function readFixedDocument(string $relativePath): string
    {
        $root = realpath(FCPATH);
        $absolute = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if ($root === false || is_link($absolute) || !is_file($absolute) || !is_readable($absolute)) {
            throw new RuntimeException('Canonical document unavailable.');
        }

        $real = realpath($absolute);
        $size = @filesize($absolute);
        if ($real === false
            || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0
            || $size === false
            || $size < 1
            || $size > self::MAX_DOCUMENT_BYTES
        ) {
            throw new RuntimeException('Canonical document rejected.');
        }

        $contents = @file_get_contents($absolute);
        if (!is_string($contents)
            || strlen($contents) !== $size
            || preg_match('//u', $contents) !== 1
        ) {
            throw new RuntimeException('Canonical document unreadable.');
        }

        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    private function extractSection(string $document, string $heading): string
    {
        $pattern = '/^' . preg_quote($heading, '/') . '\h*$\n(.*?)(?=^###\h|\z)/msu';
        if (preg_match($pattern, $document, $match) !== 1) {
            throw new RuntimeException('Canonical section unavailable.');
        }
        return (string)$match[1];
    }

    /**
     * @param string[] $expectedHeaders
     * @return array<int,array<string,string>>
     */
    private function parseTable(string $section, array $expectedHeaders): array
    {
        $lines = explode("\n", $section);
        $headerIndex = null;
        foreach ($lines as $index => $line) {
            if ($this->splitTableRow($line) === $expectedHeaders) {
                $headerIndex = $index;
                break;
            }
        }
        if ($headerIndex === null || !isset($lines[$headerIndex + 1])) {
            throw new RuntimeException('Canonical table header unavailable.');
        }

        $separator = $this->splitTableRow($lines[$headerIndex + 1]);
        if (count($separator) !== count($expectedHeaders)) {
            throw new RuntimeException('Canonical table separator invalid.');
        }
        foreach ($separator as $cell) {
            if (preg_match('/\A:?-{3,}:?\z/D', $cell) !== 1) {
                throw new RuntimeException('Canonical table separator invalid.');
            }
        }

        $rows = [];
        for ($index = $headerIndex + 2; isset($lines[$index]); $index++) {
            $line = trim($lines[$index]);
            if ($line === '') {
                break;
            }
            if ($line[0] !== '|' || substr($line, -1) !== '|') {
                throw new RuntimeException('Canonical table row invalid.');
            }
            $cells = $this->splitTableRow($line);
            if (count($cells) !== count($expectedHeaders)) {
                throw new RuntimeException('Canonical table column count invalid.');
            }
            $row = [];
            foreach ($expectedHeaders as $cellIndex => $header) {
                $row[$header] = $this->plainText($cells[$cellIndex]);
            }
            $rows[] = $row;
        }
        if ($rows === []) {
            throw new RuntimeException('Canonical table is empty.');
        }
        return $rows;
    }

    /** @return string[] */
    private function splitTableRow(string $line): array
    {
        $line = trim($line);
        if ($line === '' || $line[0] !== '|' || substr($line, -1) !== '|') {
            return [];
        }
        $inner = substr($line, 1, -1);
        $cells = preg_split('/(?<!\\\\)\|/', $inner);
        if (!is_array($cells)) {
            return [];
        }
        return array_map(static function (string $cell): string {
            return trim(str_replace('\\|', '|', $cell));
        }, $cells);
    }

    private function plainText(string $value): string
    {
        $value = trim($value);
        $value = (string)preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $value);
        $value = (string)preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $value);
        $value = str_replace(['`', '**', '__'], '', $value);
        $value = (string)preg_replace('/(?<!\\\\)[*_~]/u', '', $value);
        $value = str_replace(['\\*', '\\_', '\\~'], ['*', '_', '~'], $value);
        $value = trim((string)preg_replace('/\s+/u', ' ', $value));
        if ($value === ''
            || strlen($value) > 4096
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1
        ) {
            throw new RuntimeException('Canonical table value invalid.');
        }
        return $value;
    }

    /** @param array<int,array<string,string>> $rows */
    private function assertPhaseSequence(array $rows, string $prefix): void
    {
        if (count($rows) !== 6) {
            throw new RuntimeException('Canonical phase count invalid.');
        }
        foreach ($rows as $index => $row) {
            if (preg_match('/\A' . preg_quote($prefix, '/') . $index . '\b/u', $row['Fase'] ?? '') !== 1) {
                throw new RuntimeException('Canonical phase sequence invalid.');
            }
        }
    }

    /** @param array<int,array<string,string>> $rows */
    private function assertIdSequence(array $rows, string $prefix, int $count): void
    {
        if (count($rows) !== $count) {
            throw new RuntimeException('Canonical item count invalid.');
        }
        foreach ($rows as $index => $row) {
            $expected = $prefix . str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT);
            if (($row['ID'] ?? '') !== $expected) {
                throw new RuntimeException('Canonical item sequence invalid.');
            }
        }
    }

    /**
     * Readiness is derived only from the canonical phase status column. Finding
     * rows are evidence and must never be interpreted as phase completion.
     *
     * @param array<int,array<string,string>> $technicalPhases
     * @param array<int,array<string,string>> $commercialPhases
     * @return array<string,mixed>
     */
    private function deriveReadiness(array $technicalPhases, array $commercialPhases): array
    {
        $technicalReasons = $this->incompletePhaseReasons($technicalPhases, 'A');
        $commercialReasons = $this->incompletePhaseReasons($commercialPhases, 'C');
        $ready = $technicalReasons === [] && $commercialReasons === [];

        return [
            'label' => $ready ? 'Siap jual' : 'Belum siap jual',
            'status' => $ready ? 'READY' : 'BLOCKED',
            'technical_incomplete_count' => count($technicalReasons),
            'commercial_incomplete_count' => count($commercialReasons),
            'reasons' => array_merge($technicalReasons, $commercialReasons),
        ];
    }

    /**
     * @param array<int,array<string,string>> $phases
     * @return string[]
     */
    private function incompletePhaseReasons(array $phases, string $prefix): array
    {
        $reasons = [];
        for ($index = 0; $index < 6; $index++) {
            $expected = $prefix . $index;
            $phase = $phases[$index] ?? [];
            $phaseLabel = (string)($phase['Fase'] ?? $expected);
            $status = (string)($phase['Status fase'] ?? 'MISSING');
            if (preg_match('/\A' . preg_quote($expected, '/') . '\b/u', $phaseLabel) !== 1) {
                $status = 'MISSING';
                $phaseLabel = $expected;
            }
            if ($status === 'DONE') {
                continue;
            }
            $reason = (string)($phase['Alasan/gerbang berikutnya'] ?? 'Status fase kanonis tidak tersedia.');
            $reasons[] = $phaseLabel . ' [' . $status . '] — ' . $reason;
        }
        return $reasons;
    }

    /** @return array<string,mixed> */
    private function unavailableResult(): array
    {
        return [
            'ok' => false,
            'message' => self::UNAVAILABLE_MESSAGE,
            'readiness' => [
                'label' => 'Belum siap jual',
                'status' => 'BLOCKED',
                'technical_incomplete_count' => 6,
                'commercial_incomplete_count' => 6,
                'total_findings' => 0,
                'reasons' => ['Status fase kanonis tidak tersedia.'],
            ],
            'phases' => [],
            'findings' => [],
            'ui_waves' => [],
            'sql_register' => [],
            'commercial_phases' => [],
        ];
    }
}
