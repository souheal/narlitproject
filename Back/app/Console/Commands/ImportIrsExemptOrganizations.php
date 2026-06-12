<?php

namespace App\Console\Commands;

use App\Models\IrsExemptOrganization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportIrsExemptOrganizations extends Command
{
    protected $signature = 'irs:import-eo-bmf
        {--path=* : Local IRS EO BMF CSV file path. Can be passed multiple times.}
        {--url=* : IRS EO BMF CSV URL. Defaults to services.irs.eo_bmf_urls.}
        {--truncate : Remove existing IRS records before import.}';

    protected $description = 'Import official IRS Exempt Organizations Business Master File CSV records.';

    public function handle(): int
    {
        $sources = $this->resolveSources();

        if ($sources === []) {
            $this->error('No IRS EO BMF CSV source configured.');

            return self::FAILURE;
        }

        if ((bool) $this->option('truncate')) {
            IrsExemptOrganization::query()->delete();
        }

        $totalImported = 0;

        foreach ($sources as $source) {
            $path = $this->prepareSource($source);

            if ($path === null) {
                return self::FAILURE;
            }

            $imported = $this->importCsv($path, $source);
            $totalImported += $imported;

            $this->info("Imported {$imported} IRS records from {$source}.");

            if (str_starts_with($path, storage_path('app/tmp/irs_'))) {
                @unlink($path);
            }
        }

        $this->info("IRS EO BMF import completed. Total imported: {$totalImported}.");

        return self::SUCCESS;
    }

    protected function resolveSources(): array
    {
        $paths = array_values(array_filter((array) $this->option('path')));
        $urls = array_values(array_filter((array) $this->option('url')));

        if ($paths !== []) {
            return $paths;
        }

        if ($urls !== []) {
            return $urls;
        }

        return (array) config('services.irs.eo_bmf_urls', []);
    }

    protected function prepareSource(string $source): ?string
    {
        if (filter_var($source, FILTER_VALIDATE_URL) === false) {
            if (! is_readable($source)) {
                $this->error("IRS source file is not readable: {$source}");

                return null;
            }

            return $source;
        }

        $tmpDir = storage_path('app/tmp');

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $path = $tmpDir.'/irs_'.md5($source.'_'.microtime(true)).'.csv';
        $response = Http::timeout(120)->sink($path)->get($source);

        if (! $response->successful()) {
            $this->error("IRS source download failed ({$response->status()}): {$source}");

            return null;
        }

        return $path;
    }

    protected function importCsv(string $path, string $source): int
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->error("Could not open IRS CSV: {$path}");

            return 0;
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return 0;
        }

        $map = $this->headerMap($headers);
        $now = now();
        $rows = [];
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $ein = $this->field($row, $map, ['ein']);
            $name = $this->field($row, $map, ['name', 'organization_name']);

            if ($ein === null || $name === null) {
                continue;
            }

            $ein = preg_replace('/[^0-9]/', '', $ein) ?? '';

            if (strlen($ein) !== 9 || trim($name) === '') {
                continue;
            }

            $rows[] = [
                'ein' => $ein,
                'organization_name' => trim($name),
                'normalized_name' => $this->normalizeName($name),
                'city' => $this->field($row, $map, ['city']),
                'state' => $this->field($row, $map, ['state']),
                'country' => $this->field($row, $map, ['country']),
                'subsection' => $this->field($row, $map, ['subsection']),
                'classification' => $this->field($row, $map, ['classification']),
                'ruling_date' => $this->field($row, $map, ['ruling', 'ruling_date']),
                'deductibility' => $this->field($row, $map, ['deductibility']),
                'foundation_code' => $this->field($row, $map, ['foundation', 'foundation_code']),
                'activity_code' => $this->field($row, $map, ['activity', 'activity_code']),
                'organization_code' => $this->field($row, $map, ['organization', 'organization_code']),
                'source' => 'irs_eo_bmf',
                'imported_at' => $now,
                'raw' => json_encode($this->rawRow($headers, $row), JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 1000) {
                $imported += $this->upsert($rows);
                $rows = [];
            }
        }

        fclose($handle);

        if ($rows !== []) {
            $imported += $this->upsert($rows);
        }

        return $imported;
    }

    protected function upsert(array $rows): int
    {
        DB::table('irs_exempt_organizations')->upsert(
            $rows,
            ['ein'],
            [
                'organization_name',
                'normalized_name',
                'city',
                'state',
                'country',
                'subsection',
                'classification',
                'ruling_date',
                'deductibility',
                'foundation_code',
                'activity_code',
                'organization_code',
                'source',
                'imported_at',
                'raw',
                'updated_at',
            ],
        );

        return count($rows);
    }

    protected function headerMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $map[Str::of((string) $header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value()] = $index;
        }

        return $map;
    }

    protected function field(array $row, array $map, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $map)) {
                $value = trim((string) ($row[$map[$key]] ?? ''));

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    protected function rawRow(array $headers, array $row): array
    {
        $raw = [];

        foreach ($headers as $index => $header) {
            $raw[(string) $header] = $row[$index] ?? null;
        }

        return $raw;
    }

    protected function normalizeName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replaceMatches('/\b(the|inc|incorporated|corp|corporation|llc|ltd|foundation|organization|org)\b/', '')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }
}
