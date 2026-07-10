<?php

declare(strict_types=1);

namespace GreenNet\Services;

class AdminCustomerDirectoryService
{
    public function getFilteredRows(array $filters = []): array
    {
        $overview = new SubscriptionOverviewService();
        $summary = $overview->getSummary();

        $rows = $summary['rows'] ?? [];

        $query = trim((string) ($filters['q'] ?? ''));
        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));
        $subscriptionStatus = trim((string) ($filters['subscription_status'] ?? ''));
        $accessType = trim((string) ($filters['access_type'] ?? ''));

        $filtered = array_values(array_filter($rows, function (array $row) use ($query, $paymentStatus, $subscriptionStatus, $accessType): bool {
            if ($query !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $row['username'] ?? '',
                    $row['display_name'] ?? '',
                    $row['phone'] ?? '',
                    $row['package_name'] ?? '',
                    $row['payment_label'] ?? '',
                    $row['subscription_label'] ?? '',
                ]));

                if (!str_contains($haystack, mb_strtolower($query))) {
                    return false;
                }
            }

            if ($paymentStatus !== '' && $paymentStatus !== 'all') {
                if (($row['payment_status'] ?? '') !== $paymentStatus) {
                    return false;
                }
            }

            if ($subscriptionStatus !== '' && $subscriptionStatus !== 'all') {
                if (($row['subscription_status'] ?? '') !== $subscriptionStatus) {
                    return false;
                }
            }

            if ($accessType !== '' && $accessType !== 'all') {
                if (($row['access_type'] ?? '') !== $accessType) {
                    return false;
                }
            }

            return true;
        }));

        usort($filtered, function (array $a, array $b): int {
            $weightA = $this->statusWeight($a['subscription_status'] ?? '');
            $weightB = $this->statusWeight($b['subscription_status'] ?? '');

            if ($weightA !== $weightB) {
                return $weightA <=> $weightB;
            }

            return strcmp((string) ($a['username'] ?? ''), (string) ($b['username'] ?? ''));
        });

        return [
            'rows' => $filtered,
            'all_rows_count' => count($rows),
            'filtered_count' => count($filtered),
            'filters' => [
                'q' => $query,
                'payment_status' => $paymentStatus !== '' ? $paymentStatus : 'all',
                'subscription_status' => $subscriptionStatus !== '' ? $subscriptionStatus : 'all',
                'access_type' => $accessType !== '' ? $accessType : 'all',
            ],
            'summary' => $summary,
        ];
    }

    private function statusWeight(string $status): int
    {
        return match ($status) {
            'expired' => 1,
            'soon_3' => 2,
            'soon_7' => 3,
            'no_renewal' => 4,
            'no_package' => 5,
            'active' => 6,
            default => 9,
        };
    }
}