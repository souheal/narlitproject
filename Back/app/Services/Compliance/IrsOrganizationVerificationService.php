<?php

namespace App\Services\Compliance;

use App\Models\IrsExemptOrganization;
use Illuminate\Support\Str;

class IrsOrganizationVerificationService
{
    public function verify(string $organizationName, string $taxId): array
    {
        $normalizedTaxId = $this->normalizeTaxId($taxId);
        $mode = (string) config('services.irs.verification_mode', 'local');

        if ($mode === 'local') {
            if (app()->isProduction()) {
                return [
                    'matched' => false,
                    'source' => 'local',
                    'ein' => $normalizedTaxId,
                    'legal_name' => null,
                    'match_score' => 0,
                    'reason' => 'Local IRS verification cannot be used in production.',
                ];
            }

            return $this->verifyLocally($organizationName, $normalizedTaxId);
        }

        return $this->verifyFromImportedRecords($organizationName, $normalizedTaxId);
    }

    protected function verifyLocally(string $organizationName, string $taxId): array
    {
        $fixtures = (array) config('services.irs.local_organizations', []);
        $fixtureName = $fixtures[$taxId] ?? null;

        if ($fixtureName === null && (bool) config('services.irs.allow_local_match', false)) {
            return [
                'matched' => true,
                'source' => 'local',
                'ein' => $taxId,
                'legal_name' => $organizationName,
                'match_score' => 100,
            ];
        }

        if ($fixtureName !== null && $this->namesMatch($organizationName, $fixtureName)) {
            return [
                'matched' => true,
                'source' => 'local',
                'ein' => $taxId,
                'legal_name' => $fixtureName,
                'match_score' => 100,
            ];
        }

        return [
            'matched' => false,
            'source' => 'local',
            'ein' => $taxId,
            'legal_name' => $fixtureName,
            'match_score' => $fixtureName ? $this->similarityScore($organizationName, $fixtureName) : 0,
            'reason' => 'No matching IRS organization record was found for this tax ID and name.',
        ];
    }

    protected function verifyFromImportedRecords(string $organizationName, string $taxId): array
    {
        if (! IrsExemptOrganization::query()->exists()) {
            return [
                'matched' => false,
                'source' => 'irs_eo_bmf',
                'ein' => $taxId,
                'legal_name' => null,
                'match_score' => 0,
                'reason' => 'IRS exempt organization records have not been imported yet.',
            ];
        }

        $record = IrsExemptOrganization::query()->where('ein', $taxId)->first();

        if ($record === null) {
            return [
                'matched' => false,
                'source' => 'irs_eo_bmf',
                'ein' => $taxId,
                'legal_name' => null,
                'match_score' => 0,
                'reason' => 'No matching IRS exempt organization record was found for this EIN.',
            ];
        }

        $score = $this->similarityScore($organizationName, $record->organization_name);
        $matched = $score >= (int) config('services.irs.name_match_threshold', 90);

        return [
            'matched' => $matched,
            'source' => $record->source,
            'ein' => $record->ein,
            'legal_name' => $record->organization_name,
            'match_score' => $score,
            'imported_at' => $record->imported_at?->toIso8601String(),
            'reason' => $matched ? null : 'The EIN was found in IRS records, but the organization name did not match.',
        ];
    }

    protected function verifyFromDataset(string $organizationName, string $taxId): array
    {
        $path = (string) config('services.irs.dataset_path', '');

        if ($path === '' || ! is_readable($path)) {
            return [
                'matched' => false,
                'source' => 'dataset',
                'ein' => $taxId,
                'legal_name' => null,
                'match_score' => 0,
                'reason' => 'IRS organization dataset is not configured.',
            ];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [
                'matched' => false,
                'source' => 'dataset',
                'ein' => $taxId,
                'legal_name' => null,
                'match_score' => 0,
                'reason' => 'IRS organization dataset could not be opened.',
            ];
        }

        $headers = fgetcsv($handle) ?: [];
        $einIndex = $this->findHeaderIndex($headers, ['ein', 'tax_id']);
        $nameIndex = $this->findHeaderIndex($headers, ['name', 'organization_name', 'organization']);

        if ($einIndex === null || $nameIndex === null) {
            fclose($handle);

            return [
                'matched' => false,
                'source' => 'dataset',
                'ein' => $taxId,
                'legal_name' => null,
                'match_score' => 0,
                'reason' => 'IRS organization dataset does not contain EIN and organization name columns.',
            ];
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->normalizeTaxId((string) ($row[$einIndex] ?? '')) !== $taxId) {
                continue;
            }

            $legalName = (string) ($row[$nameIndex] ?? '');
            $matched = $this->namesMatch($organizationName, $legalName);

            fclose($handle);

            return [
                'matched' => $matched,
                'source' => 'dataset',
                'ein' => $taxId,
                'legal_name' => $legalName,
                'match_score' => $this->similarityScore($organizationName, $legalName),
                'reason' => $matched ? null : 'The tax ID was found, but the organization name did not match the IRS record.',
            ];
        }

        fclose($handle);

        return [
            'matched' => false,
            'source' => 'dataset',
            'ein' => $taxId,
            'legal_name' => null,
            'match_score' => 0,
            'reason' => 'No matching IRS organization record was found for this tax ID.',
        ];
    }

    protected function findHeaderIndex(array $headers, array $candidates): ?int
    {
        foreach ($headers as $index => $header) {
            $normalized = Str::of((string) $header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();

            if (in_array($normalized, $candidates, true)) {
                return (int) $index;
            }
        }

        return null;
    }

    protected function namesMatch(string $submittedName, string $irsName): bool
    {
        return $this->similarityScore($submittedName, $irsName) >= (int) config('services.irs.name_match_threshold', 90);
    }

    protected function similarityScore(string $left, string $right): int
    {
        similar_text($this->normalizeName($left), $this->normalizeName($right), $percent);

        return (int) round($percent);
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

    protected function normalizeTaxId(string $taxId): string
    {
        return preg_replace('/[^0-9]/', '', $taxId) ?? '';
    }
}
