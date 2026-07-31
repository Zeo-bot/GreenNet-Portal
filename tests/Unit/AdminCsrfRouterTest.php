<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Core\Router;
use PHPUnit\Framework\TestCase;

final class AdminCsrfRouterTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['admin_logged_in' => true, 'admin_csrf_token' => 'synthetic-csrf-token'];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        http_response_code(200);
    }

    public function testAdminPostRejectsMissingTokenBeforeController(): void
    {
        $called = false;
        $router = new Router(static function () use (&$called): object {
            $called = true;
            return new AdminCsrfSyntheticController();
        });
        $router->post('/admin/synthetic-write', [AdminCsrfSyntheticController::class, 'execute']);

        ob_start();
        $router->dispatch('/admin/synthetic-write', 'POST');
        $body = (string) ob_get_clean();

        self::assertSame(419, http_response_code());
        self::assertFalse($called);
        self::assertStringContainsString('session validation failed', $body);
    }

    public function testAdminPostAcceptsMatchingFormToken(): void
    {
        $_POST['_csrf'] = 'synthetic-csrf-token';
        $router = new Router(static fn (): object => new AdminCsrfSyntheticController());
        $router->post('/admin/synthetic-write', [AdminCsrfSyntheticController::class, 'execute']);

        ob_start();
        $router->dispatch('/admin/synthetic-write', 'POST');
        $body = (string) ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertSame('executed', $body);
    }

    public function testAdminLoginRemainsAvailableWithoutExistingSessionToken(): void
    {
        $_SESSION = [];
        $router = new Router(static fn (): object => new AdminCsrfSyntheticController());
        $router->post('/admin/login', [AdminCsrfSyntheticController::class, 'execute']);

        ob_start();
        $router->dispatch('/admin/login', 'POST');
        $body = (string) ob_get_clean();

        self::assertSame(200, http_response_code());
        self::assertSame('executed', $body);
    }
}

final class AdminCsrfSyntheticController
{
    public function execute(): string
    {
        return 'executed';
    }
}
