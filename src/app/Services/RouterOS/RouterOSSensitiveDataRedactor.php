<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

class RouterOSSensitiveDataRedactor
{
    private const HIDDEN = '<hidden>';

    public function redact(mixed $value): mixed
    {
        return $this->redactWithValues(
            $value,
            is_array($value) ? $this->sensitiveValues($value) : []
        );
    }

    public function redactWithValues(mixed $value, array $sensitiveValues): mixed
    {
        $sensitiveValues = array_values(array_unique(array_merge(
            $sensitiveValues,
            is_array($value) ? $this->sensitiveValues($value) : []
        )));

        return $this->redactRecursive($value, $sensitiveValues);
    }

    public function redactMessage(string $message, array $sensitiveValues = []): string
    {
        $message = $this->replaceSensitiveValues($message, $sensitiveValues);
        $message = preg_replace('/(?:tcp:\/\/)?[a-z0-9._-]+:\d{2,5}/i', '<hidden-host>:<hidden-port>', $message)
            ?? 'RouterOS operation failed.';
        $message = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '<hidden-host>', $message)
            ?? 'RouterOS operation failed.';
        $message = preg_replace('/:\d{2,5}\b/', ':<hidden-port>', $message)
            ?? 'RouterOS operation failed.';

        return substr($message, 0, 500);
    }

    public function json(mixed $value): string
    {
        return json_encode(
            $this->redact($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        ) ?: '{}';
    }

    public function sensitiveValues(array $value): array
    {
        $values = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                $this->collectScalarValues($item, $values);
            }

            if (is_array($item)) {
                $values = array_merge($values, $this->sensitiveValues($item));
            }
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $values),
            static fn (string $item): bool => $item !== ''
        )));
    }

    private function redactRecursive(mixed $value, array $sensitiveValues): mixed
    {
        if (is_string($value)) {
            return $this->replaceSensitiveValues($value, $sensitiveValues);
        }

        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = self::HIDDEN;
                continue;
            }

            $redacted[$key] = $this->redactRecursive($item, $sensitiveValues);
        }

        return $redacted;
    }

    private function replaceSensitiveValues(string $value, array $sensitiveValues): string
    {
        foreach ($sensitiveValues as $sensitiveValue) {
            $sensitiveValue = (string) $sensitiveValue;
            if ($sensitiveValue !== '' && $sensitiveValue !== self::HIDDEN) {
                $value = str_replace($sensitiveValue, self::HIDDEN, $value);
            }
        }

        return $value;
    }

    private function collectScalarValues(mixed $value, array &$values): void
    {
        if (is_scalar($value)) {
            $values[] = (string) $value;
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectScalarValues($item, $values);
            }
        }
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        $normalized = preg_replace('/[\s._-]+/', '', $normalized) ?? $normalized;

        return in_array($normalized, [
            'password', 'passwd', 'pass', 'secret', 'token', 'authorization',
            'apikey', 'privatekey', 'key',
        ], true)
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'passwd')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'authorization')
            || str_contains($normalized, 'apikey')
            || str_contains($normalized, 'privatekey');
    }
}
