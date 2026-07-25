<?php

namespace App\Services\Support;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV exports straight to the client.
 *
 * Rows are pulled with chunkById() and written to php://output as they arrive,
 * so memory stays flat (~a few MB) whether the export is 100 rows or 5 million.
 * Nothing is ever collected into an array first.
 */
class ExportService
{
    /** Rows fetched per database round-trip. */
    private const CHUNK_SIZE = 1000;

    /**
     * @param  Builder  $query      the (already filtered) query to export
     * @param  array<int, string>  $headings  CSV header row
     * @param  Closure  $mapRow     receives a model, returns a flat array of cells
     */
    public function streamCsv(
        Builder $query,
        array $headings,
        Closure $mapRow,
        string $filename
    ): StreamedResponse {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            // Exports are generated per request and must never be replayed
            // from a browser or proxy cache.
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($query, $headings, $mapRow): void {
            $handle = fopen('php://output', 'wb');

            // BOM so Excel opens UTF-8 (₹, Devanagari names) correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings);

            // chunkById keeps a stable cursor on the primary key, so rows are
            // never skipped or duplicated even if the table is written to mid-export.
            $query->chunkById(self::CHUNK_SIZE, function ($rows) use ($handle, $mapRow): void {
                foreach ($rows as $row) {
                    fputcsv($handle, $this->sanitize($mapRow($row)));
                }
                // Push each chunk to the browser instead of buffering it.
                flush();
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Neutralise spreadsheet formula injection: a cell starting with = + - @
     * would execute when the CSV is opened in Excel or Sheets.
     */
    private function sanitize(array $cells): array
    {
        return array_map(function ($cell) {
            $value = (string) $cell;

            return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
        }, $cells);
    }
}
