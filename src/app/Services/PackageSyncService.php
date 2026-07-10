<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Models\ServicePackage;
use GreenNet\Services\RouterOS\RouterOSProfilesService;

class PackageSyncService
{
    public function syncFromRouterOSProfiles(): array
    {
        $profilesService = new RouterOSProfilesService();
        $summary = $profilesService->getSummary();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (($summary['all_profiles'] ?? []) as $profile) {
            $name = trim((string) ($profile['name'] ?? ''));

            if ($name === '' || $name === '-') {
                $skipped++;
                continue;
            }

            $sourceType = trim((string) ($profile['type'] ?? 'RouterOS'));
            $sourceProfile = $name;
            $accessType = $this->accessTypeFromSource($sourceType);
            $rateLimit = trim((string) ($profile['rate_limit'] ?? '-'));

            $notes = $this->buildNotes($profile);

            $result = ServicePackage::upsertRouterProfile(
                $name,
                $sourceType,
                $sourceProfile,
                $accessType,
                $rateLimit,
                $notes
            );

            if ($result === 'created') {
                $created++;
                continue;
            }

            if ($result === 'updated') {
                $updated++;
                continue;
            }

            $skipped++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_profiles' => count($summary['all_profiles'] ?? []),
        ];
    }

    private function accessTypeFromSource(string $sourceType): string
    {
        $sourceType = strtolower($sourceType);

        if (str_contains($sourceType, 'hotspot')) {
            return 'hotspot';
        }

        if (str_contains($sourceType, 'ppp')) {
            return 'ppp';
        }

        return 'hybrid';
    }

    private function buildNotes(array $profile): string
    {
        $parts = [
            'مستورد من بروفايل MikroTik',
            'Source: ' . ($profile['type'] ?? '-'),
            'Profile: ' . ($profile['name'] ?? '-'),
        ];

        if (($profile['rate_limit'] ?? '-') !== '-') {
            $parts[] = 'Rate Limit: ' . $profile['rate_limit'];
        }

        if (($profile['comment'] ?? '-') !== '-') {
            $parts[] = 'Comment: ' . $profile['comment'];
        }

        return implode(' - ', $parts);
    }
}