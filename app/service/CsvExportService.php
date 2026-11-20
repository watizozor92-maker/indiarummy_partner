<?php

declare(strict_types=1);

namespace app\service;

use think\Response;

final class CsvExportService
{
    /**
     * @param list<string> $headers
     * @param iterable<list<mixed>> $rows
     */
    public function download(string $filename, array $headers, iterable $rows): Response
    {
        $content = $this->build($headers, $rows);
        $asciiName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) ?: 'export.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf(
                "attachment; filename=\"%s\"; filename*=UTF-8''%s",
                $asciiName,
                rawurlencode($filename)
            ),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param list<string> $headers
     * @param iterable<list<mixed>> $rows
     */
    public function build(array $headers, iterable $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('无法创建导出文件');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map([$this, 'safeCell'], $row));
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content === false ? '' : $content;
    }

    private function safeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $cell = is_scalar($value)
            ? (string) $value
            : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cell = $cell === false ? '' : $cell;

        // Prevent spreadsheet applications from evaluating exported data as formulas.
        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $cell) === 1 ? "'" . $cell : $cell;
    }
}
