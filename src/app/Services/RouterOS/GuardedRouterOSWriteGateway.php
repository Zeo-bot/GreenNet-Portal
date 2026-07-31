<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\GuardedRouterOSWriteGatewayInterface;
use GreenNet\Contracts\AuthorizedRouterOSWriterInterface;
use GreenNet\Contracts\RouterOSClientInterface;
use GreenNet\Contracts\RouterOSWriteCommandPolicyInterface;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\DTO\RouterOS\WriteCommandCall;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\DTO\RouterOS\WriteExecutionResult;
use GreenNet\Exceptions\GuardedWriteExecutionException;
use GreenNet\Services\WriteSafetyGuard;
use RuntimeException;
use Throwable;

final class GuardedRouterOSWriteGateway implements GuardedRouterOSWriteGatewayInterface
{
    private bool $executing = false;

    public function __construct(
        private readonly RouterOSClientInterface $client,
        private readonly WriteSafetyGuard $guard,
        private readonly RouterOSWriteCommandPolicyInterface $policy,
        private readonly RouterOSWriteRedactor $redactor
    ) {
    }

    public function execute(WriteExecutionRequest $request, callable $operation): WriteExecutionResult
    {
        if ($this->executing) {
            $result = new WriteExecutionResult(
                false,
                null,
                [],
                false,
                0,
                0,
                false,
                0,
                'Nested guarded RouterOS write execution is not allowed.'
            );

            throw new GuardedWriteExecutionException($result->safeError, $result);
        }

        $this->guard->assertRealWriteAllowed([
            'confirmed' => $request->confirmed,
            'allow_safe_mode' => $request->allowSafeMode,
        ]);

        $this->executing = true;
        $writer = new class(
            $this->client,
            $this->policy,
            $this->redactor
        ) implements AuthorizedRouterOSWriterInterface {
            private bool $active = true;

            /** @var list<WriteCommandCall> */
            private array $calls = [];

            private int $attemptedCallCount = 0;
            private int $successfulCallCount = 0;
            private string $safeError = '';
            private array $sensitiveValues = [];

            public function __construct(
                private readonly RouterOSClientInterface $client,
                private readonly RouterOSWriteCommandPolicyInterface $policy,
                private readonly RouterOSWriteRedactor $redactor
            ) {
            }

            public function execute(RouterOSWriteCommand $command): array
            {
                if (!$this->active) {
                    throw new RuntimeException('Authorized RouterOS writer is no longer active.');
                }

                $this->policy->assertAllowed($command);
                $this->attemptedCallCount++;
                $sensitiveValues = $this->redactor->sensitiveValues($command->params);
                $this->sensitiveValues = array_values(array_unique(array_merge(
                    $this->sensitiveValues,
                    $sensitiveValues
                )));

                try {
                    $response = $this->client->comm($command->command, $command->params);
                    $this->successfulCallCount++;
                    $this->calls[] = new WriteCommandCall(
                        $command->action(),
                        $command->command,
                        $this->redactor->redact($command->params),
                        $this->redactor->redactWithValues($response, $sensitiveValues),
                        true
                    );

                    return $response;
                } catch (Throwable $error) {
                    $this->safeError = $this->redactor->redactMessage($error->getMessage(), $sensitiveValues);
                    $this->calls[] = new WriteCommandCall(
                        $command->action(),
                        $command->command,
                        $this->redactor->redact($command->params),
                        [],
                        false,
                        $this->safeError
                    );

                    throw new RuntimeException($this->safeError);
                }
            }

            public function invalidate(): void
            {
                $this->active = false;
            }

            /** @return list<WriteCommandCall> */
            public function calls(): array
            {
                return $this->calls;
            }

            public function attemptedCallCount(): int
            {
                return $this->attemptedCallCount;
            }

            public function successfulCallCount(): int
            {
                return $this->successfulCallCount;
            }

            public function safeError(): string
            {
                return $this->safeError;
            }

            public function sensitiveValues(): array
            {
                return $this->sensitiveValues;
            }
        };
        $operationOk = true;
        $value = null;
        $safeError = '';
        $auditRecorded = false;
        $auditId = 0;
        $auditWarning = '';

        try {
            try {
                $value = $operation($writer);

                if ($writer->attemptedCallCount() !== $writer->successfulCallCount()) {
                    $operationOk = false;
                    $safeError = $writer->safeError() !== ''
                        ? $writer->safeError()
                        : 'RouterOS write operation failed.';
                }
            } catch (Throwable $error) {
                $operationOk = false;
                $sensitiveValues = array_merge(
                    $writer->sensitiveValues(),
                    $this->redactor->sensitiveValues($request->auditParams)
                );
                $safeError = $this->redactor->redactMessage($error->getMessage(), $sensitiveValues);
            } finally {
                $writer->invalidate();
            }

            $successfulCallCount = $writer->successfulCallCount();
            $attemptedCallCount = $writer->attemptedCallCount();
            $partialFailure = !$operationOk && $successfulCallCount > 0;
            $sensitiveValues = array_merge(
                $writer->sensitiveValues(),
                $this->redactor->sensitiveValues($request->auditParams)
            );
            $safeValue = $this->redactor->redactWithValues($value, $sensitiveValues);
            $calls = $writer->calls();

            $auditPayload = [
                'ok' => $operationOk,
                'partial_failure' => $partialFailure,
                'successful_call_count' => $successfulCallCount,
                'attempted_call_count' => $attemptedCallCount,
                'calls' => array_map(
                    static fn ($call): array => $call->toArray(),
                    $calls
                ),
                'error' => $safeError,
            ];

            try {
                $auditId = $this->guard->recordRealAttempt([
                    'action' => $request->action,
                    'dataset' => $request->dataset,
                    'username' => $request->username,
                    'command' => $request->auditCommand,
                    'params' => array_merge(
                        $this->redactor->redactWithValues($request->auditParams, $sensitiveValues),
                        [
                            'dry_run_audit_id' => $request->dryRunAuditId,
                            'confirmed' => $request->confirmed,
                        ]
                    ),
                    'executed' => $attemptedCallCount > 0 ? 1 : 0,
                    'success' => $operationOk ? 1 : 0,
                    'router_response' => $this->redactor->json($auditPayload),
                    'after_state' => $safeValue,
                    'error_details' => $safeError,
                    'reconciliation_status' => $partialFailure ? 'required' : ($operationOk ? 'verified' : 'not_required'),
                ]);
                $auditRecorded = true;
            } catch (Throwable) {
                $auditWarning = 'RouterOS operation audit could not be recorded.';
            }

            $result = new WriteExecutionResult(
                $operationOk,
                $safeValue,
                $calls,
                $partialFailure,
                $successfulCallCount,
                $attemptedCallCount,
                $auditRecorded,
                $auditId,
                $safeError,
                $auditWarning
            );

            if (!$operationOk) {
                $message = $safeError !== '' ? $safeError : 'RouterOS write operation failed.';
                if (!$auditRecorded) {
                    $message .= ' The operation audit could not be recorded.';
                }

                throw new GuardedWriteExecutionException($message, $result);
            }

            return $result;
        } finally {
            $writer->invalidate();
            $this->executing = false;
        }
    }
}
