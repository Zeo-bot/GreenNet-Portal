<?php

declare(strict_types=1);

if (!function_exists('gn_subscriber_h')) {
    function gn_subscriber_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('gn_subscriber_bytes')) {
    function gn_subscriber_bytes(mixed $bytes): string
    {
        if ($bytes === null || $bytes === '') {
            return 'غير متاح';
        }

        $value = max(0, (int) $bytes);
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                $precision = $unit === 'B' ? 0 : 1;
                return number_format($value, $precision) . ' ' . $unit;
            }
            $value /= 1024;
        }

        return 'غير متاح';
    }
}

if (!function_exists('gn_subscriber_date')) {
    function gn_subscriber_date(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 'غير متاح';
        }

        $timestamp = strtotime($raw);
        return $timestamp === false ? $raw : date('Y-m-d', $timestamp);
    }
}

if (!function_exists('gn_subscriber_status')) {
    function gn_subscriber_status(mixed $status): array
    {
        return match (strtolower(trim((string) $status))) {
            'active' => ['نشط', 'success'],
            'expired' => ['منتهي', 'danger'],
            'disabled' => ['موقوف', 'danger'],
            'pending' => ['قيد المراجعة', 'warning'],
            'approved', 'completed', 'paid' => ['تمت الموافقة', 'success'],
            'denied', 'rejected', 'cancelled' => ['لم تتم الموافقة', 'danger'],
            default => ['غير متاح', 'neutral'],
        };
    }
}
