<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use Closure;
use GreenNet\Services\SubscriberSecurityService;
use GreenNet\Services\UnifiedSubscriberService;
use Throwable;

final class SubscriberApiController
{
    private ?Closure $headerEmitter;

    public function __construct(
        private ?UnifiedSubscriberService $subscribers = null,
        ?callable $headerEmitter = null
    ) {
        $this->headerEmitter = $headerEmitter !== null ? Closure::fromCallable($headerEmitter) : null;
    }

    public function session(): string
    {
        $username = SubscriberSecurityService::username();

        return $this->success([
            'authenticated' => $username !== '',
            'username' => $username !== '' ? $username : null,
            'csrf_token' => $username !== '' ? SubscriberSecurityService::csrfToken() : null,
        ]);
    }

    public function summary(): string
    {
        return $this->owned(fn (string $username): array => $this->service()->summary($username));
    }

    public function package(): string
    {
        return $this->owned(fn (string $username): array => $this->service()->summary($username)['package'] ?? []);
    }

    public function usage(): string
    {
        return $this->owned(fn (string $username): array => $this->service()->summary($username)['usage'] ?? []);
    }

    public function activeSession(): string
    {
        return $this->owned(function (string $username): array {
            $summary = $this->service()->summary($username);
            return [
                'online' => (bool) ($summary['online'] ?? false),
                'session' => $summary['active_session'] ?? null,
            ];
        });
    }

    public function renewals(): string
    {
        return $this->owned(fn (string $username): array => $this->service()->renewalHistory($username));
    }

    public function createRenewal(): string
    {
        $username = SubscriberSecurityService::username();
        if ($username === '') {
            return $this->error('AUTH_REQUIRED', 'يجب تسجيل الدخول أولاً.', 401);
        }
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
        if (!SubscriberSecurityService::validateCsrf($token)) {
            return $this->error('CSRF_INVALID', 'تعذر التحقق من الطلب.', 419);
        }

        try {
            $result = $this->service()->createRenewal(
                $username,
                trim((string) ($_POST['phone'] ?? '')),
                trim((string) ($_POST['message'] ?? 'أرغب في تجديد اشتراكي.'))
            );
            if (!empty($result['duplicate'])) {
                return $this->error('RENEWAL_PENDING', 'يوجد طلب تجديد قيد المراجعة.', 409);
            }
            if (empty($result['created'])) {
                return $this->error('RENEWAL_FAILED', 'تعذر إنشاء طلب التجديد.', 422);
            }
            return $this->success($result, 201);
        } catch (Throwable) {
            return $this->error('RENEWAL_FAILED', 'تعذر إنشاء طلب التجديد.', 500);
        }
    }

    public function payments(): string
    {
        return $this->owned(fn (string $username): array => $this->service()->payments($username));
    }

    public function notifications(): string
    {
        return $this->owned(fn (string $username): array => $this->service()->notifications($username));
    }

    public function support(): string
    {
        return $this->owned(fn (): array => $this->service()->support());
    }

    private function owned(callable $callback): string
    {
        $username = SubscriberSecurityService::username();
        if ($username === '') {
            return $this->error('AUTH_REQUIRED', 'يجب تسجيل الدخول أولاً.', 401);
        }

        try {
            return $this->success($callback($username));
        } catch (Throwable) {
            return $this->error('REQUEST_FAILED', 'تعذر تحميل البيانات حالياً.', 500);
        }
    }

    private function success(mixed $data, int $status = 200): string
    {
        http_response_code($status);
        $this->emitHeader('Content-Type: application/json; charset=utf-8');
        $this->emitHeader('Cache-Control: no-store, private');
        return json_encode([
            'ok' => true,
            'data' => $data,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false,"data":null,"error":{"code":"ENCODING_ERROR","message":"تعذر تجهيز الاستجابة."}}';
    }

    private function error(string $code, string $message, int $status): string
    {
        http_response_code($status);
        $this->emitHeader('Content-Type: application/json; charset=utf-8');
        $this->emitHeader('Cache-Control: no-store, private');
        return json_encode([
            'ok' => false,
            'data' => null,
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false,"data":null,"error":{"code":"ENCODING_ERROR","message":"تعذر تجهيز الاستجابة."}}';
    }

    private function service(): UnifiedSubscriberService
    {
        return $this->subscribers ??= new UnifiedSubscriberService();
    }

    private function emitHeader(string $header): void
    {
        if ($this->headerEmitter !== null) {
            ($this->headerEmitter)($header);
            return;
        }

        header($header);
    }
}
