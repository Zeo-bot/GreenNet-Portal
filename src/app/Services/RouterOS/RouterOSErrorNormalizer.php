<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use Throwable;

final class RouterOSErrorNormalizer
{
    public function normalize(Throwable|string $error): array
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $lower = strtolower($message);
        $code = match (true) {
            str_contains($lower, 'login failed'), str_contains($lower, 'authentication') => 'AUTHENTICATION_FAILED',
            str_contains($lower, 'timeout') => 'CONNECTION_TIMEOUT',
            str_contains($lower, 'not allowed'), str_contains($lower, 'unsupported') => 'UNSUPPORTED_COMMAND',
            str_contains($lower, 'already have'), str_contains($lower, 'duplicate') => 'DUPLICATE_RECORD',
            str_contains($lower, 'profile') && str_contains($lower, 'not found') => 'PROFILE_NOT_FOUND',
            str_contains($lower, 'limitation') && str_contains($lower, 'not found') => 'LIMITATION_NOT_FOUND',
            str_contains($lower, 'session') && str_contains($lower, 'not found') => 'ACTIVE_SESSION_NOT_FOUND',
            str_contains($lower, 'not configured') => 'BACKEND_NOT_CONFIGURED',
            str_contains($lower, 'not found'), str_contains($lower, 'missing') => 'RECORD_NOT_FOUND',
            str_contains($lower, 'partial') => 'PARTIAL_OPERATION',
            str_contains($lower, 'cleanup') => 'CLEANUP_FAILURE',
            str_contains($lower, 'reconcil') => 'RECONCILIATION_REQUIRED',
            str_contains($lower, 'trap'), str_contains($lower, 'validation') => 'ROUTEROS_VALIDATION_ERROR',
            str_contains($lower, 'unreachable'), str_contains($lower, 'connection') => 'ROUTER_UNAVAILABLE',
            default => 'ROUTEROS_OPERATION_FAILED',
        };

        return [
            'code' => $code,
            'message' => $this->safeMessage($code),
            'detail' => $message,
            'reconciliation_required' => in_array($code, [
                'PARTIAL_OPERATION', 'CLEANUP_FAILURE', 'RECONCILIATION_REQUIRED',
            ], true),
        ];
    }

    private function safeMessage(string $code): string
    {
        return match ($code) {
            'AUTHENTICATION_FAILED' => 'RouterOS authentication failed.',
            'CONNECTION_TIMEOUT' => 'RouterOS connection timed out.',
            'UNSUPPORTED_COMMAND' => 'The RouterOS command is not supported or allowed.',
            'RECORD_NOT_FOUND' => 'The RouterOS record was not found.',
            'DUPLICATE_RECORD' => 'A duplicate RouterOS record exists.',
            'PROFILE_NOT_FOUND' => 'The RouterOS profile was not found.',
            'LIMITATION_NOT_FOUND' => 'The User Manager limitation was not found.',
            'ACTIVE_SESSION_NOT_FOUND' => 'The active RouterOS session was not found.',
            'BACKEND_NOT_CONFIGURED' => 'The selected RouterOS backend is not configured.',
            'ROUTEROS_VALIDATION_ERROR' => 'RouterOS rejected the requested values.',
            'PARTIAL_OPERATION' => 'The RouterOS operation completed only partially.',
            'CLEANUP_FAILURE' => 'Temporary RouterOS record cleanup failed.',
            'RECONCILIATION_REQUIRED' => 'Local and RouterOS state require reconciliation.',
            'ROUTER_UNAVAILABLE' => 'The assigned RouterOS device is unavailable.',
            default => 'The RouterOS operation failed.',
        };
    }
}
