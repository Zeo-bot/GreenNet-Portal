<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

final class RouterOSWriteRedactor
{
    public function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = '<hidden>';
                continue;
            }

            $redacted[$key] = $this->redact($item);
        }

        return $redacted;
    }

    public function redactWithValues(mixed $value, array $sensitiveValues): mixed
    {
        $value = $this->redact($value);

        if (is_string($value)) {
            foreach ($sensitiveValues as $sensitiveValue) {
                $sensitiveValue = (string) $sensitiveValue;
                if ($sensitiveValue !== '') {
                    $value = str_replace($sensitiveValue, '<hidden>', $value);
                }
            }

            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->redactWithValues($item, $sensitiveValues);
            }
        }

        return $value;
    }

    public function redactMessage(string $message, array $sensitiveValues = []): string
    {
        foreach ($sensitiveValues as $value) {
            $value = (string) $value;
            if ($value !== '') {
                $message = str_replace($value, '<hidden>', $message);
            }
        }

        $message = preg_replace(
            '/(?:tcp:\/\/)?[a-z0-9._-]+:\d{2,5}/i',
            '<hidden-host>:<hidden-port>',
            $message
        ) ?? 'RouterOS write failed.';
        $message = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '<hidden-host>', $message) ?? 'RouterOS write failed.';
        $message = preg_replace('/:\d{2,5}\b/', ':<hidden-port>', $message) ?? 'RouterOS write failed.';

        return substr($message, 0, 500);
    }

    public function json(mixed $value): string
    {
        return json_encode(
            $this->redact($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        ) ?: '{}';
    }

    public function sensitiveValues(array $params): array
    {
        $values = [];
        foreach ($params as $key => $value) {
            if ($this->isSensitiveKey((string) $key) && is_scalar($value)) {
                $values[] = (string) $value;
            }

            if (is_array($value)) {
                $values = array_merge($values, $this->sensitiveValues($value));
            }
        }

        return array_values(array_unique($values));
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        return $key === 'key'
            || str_contains($key, 'password')
            || str_contains($key, 'pass')
            || str_contains($key, 'secret')
            || str_contains($key, 'token')
            || str_contains($key, 'api-key');
    }
}
