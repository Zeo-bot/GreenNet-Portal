<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Controllers\AdminPackageAssignController;
use GreenNet\Controllers\AdminPackagePushController;
use GreenNet\Controllers\AdminUserDisconnectController;
use GreenNet\Controllers\AdminUserManagerControlController;
use GreenNet\Contracts\GuardedRouterOSWriteGatewayInterface;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\DTO\RouterOS\WriteExecutionResult;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\Exceptions\WriteCommandNotAllowedException;
use GreenNet\Services\RouterOS\WriteCommandPolicy;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class RouterOSOperationalBoundaryMigrationTest extends TestCase
{
    public function testEveryMigratedControllerHasNoLegacyClientOrAuditOwnership(): void
    {
        foreach ([
            AdminPackageAssignController::class,
            AdminPackagePushController::class,
            AdminUserDisconnectController::class,
            AdminUserManagerControlController::class,
        ] as $class) {
            $reflection = new ReflectionClass($class);
            $source = file_get_contents((string) $reflection->getFileName());
            self::assertIsString($source);
            self::assertStringNotContainsString('new RouterOSApiClient', $source);
            self::assertStringNotContainsString('RouterOSClientInterface', $source);
            self::assertStringNotContainsString('->comm(', $source);
            self::assertStringNotContainsString('assertRealWriteAllowed(', $source);
            self::assertStringNotContainsString('recordRealAttempt(', $source);
            self::assertStringNotContainsString('Tests\\', $source);
        }
    }

    public function testControllerConstructionIsLazy(): void
    {
        foreach ([
            new AdminPackageAssignController(),
            new AdminPackagePushController(),
            new AdminUserDisconnectController(),
            new AdminUserManagerControlController(),
        ] as $controller) {
            $reflection = new ReflectionClass($controller);
            self::assertNull($reflection->getProperty('readGateway')->getValue($controller));
            if ($reflection->hasProperty('writeGateway')) {
                self::assertNull($reflection->getProperty('writeGateway')->getValue($controller));
            }
        }
    }

    public function testControlCenterReadsEveryOperationalDatasetThroughGateway(): void
    {
        $read = new FakeRouterOSReadGateway();
        foreach (range(1, 6) as $_) {
            $read->queueResponse([]);
        }
        $controller = new AdminUserManagerControlController($read);
        $this->invoke($controller, 'readRouterState', ['synthetic-user']);
        self::assertSame([
            '/user-manager/user/print',
            '/user-manager/user-profile/print',
            '/ip/hotspot/active/print',
            '/ppp/active/print',
            '/user-manager/session/print',
            '/user-manager/session/print',
        ], array_column($read->calls, 'command'));
    }

    public function testDisconnectUsesSafeExactIdProjectionsForAllThreeProtocols(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([['.id' => '*h1', 'user' => 'synthetic-user', 'password' => 'discard-me']]);
        $read->queueResponse([['.id' => '*p1', 'name' => 'synthetic-user', 'secret' => 'discard-me']]);
        $read->queueResponse([['.id' => '*s1', 'user' => 'synthetic-user', 'token' => 'discard-me']]);
        $controller = new AdminUserDisconnectController($read);
        $state = $this->invoke($controller, 'readRouterSessions', ['synthetic-user']);
        self::assertSame('*h1', $state['hotspot_active']['id_rows'][0]['.id']);
        self::assertSame('*p1', $state['ppp_active']['id_rows'][0]['.id']);
        self::assertSame('*s1', $state['user_manager_sessions']['id_rows'][0]['.id']);
        self::assertStringNotContainsString('discard-me', serialize($state));
        self::assertArrayNotHasKey('raw_rows', $state['hotspot_active']);
        self::assertArrayNotHasKey('raw_rows', $state['ppp_active']);
        self::assertArrayNotHasKey('raw_rows', $state['user_manager_sessions']);
    }

    public function testOperationalWritePolicyAllowsOnlyExactCurrentSchemas(): void
    {
        $policy = new WriteCommandPolicy();
        foreach ([
            ['/user-manager/user-profile/remove', ['numbers' => '*r1']],
            ['/user-manager/user-profile/add', ['user' => 'synthetic-user', 'profile' => 'synthetic-profile']],
            ['/user-manager/limitation/add', ['name' => 'synthetic-limit']],
            ['/user-manager/limitation/set', ['numbers' => '*l1']],
            ['/user-manager/profile/add', ['name' => 'synthetic-profile']],
            ['/user-manager/profile/set', ['numbers' => '*p1']],
            ['/user-manager/profile-limitation/add', ['profile' => 'synthetic-profile', 'limitation' => 'synthetic-limit']],
            ['/user-manager/session/remove', ['numbers' => '*s1']],
            ['/ip/hotspot/active/remove', ['numbers' => '*h1']],
            ['/ppp/active/remove', ['numbers' => '*p1']],
        ] as [$command, $params]) {
            $policy->assertAllowed(new RouterOSWriteCommand($command, $params));
            self::addToAssertionCount(1);
        }
    }

    public function testOperationalWritePolicyRejectsUnknownAndExtraParameters(): void
    {
        $policy = new WriteCommandPolicy();
        foreach ([
            new RouterOSWriteCommand('/user-manager/user-profile/remove', ['numbers' => '*r1', 'user' => 'x']),
            new RouterOSWriteCommand('/user-manager/profile/remove', ['numbers' => '*p1']),
        ] as $command) {
            try {
                $policy->assertAllowed($command);
                self::fail('Expected fail-closed command rejection.');
            } catch (WriteCommandNotAllowedException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testGuardDenialOccursBeforeCurrentRouterReads(): void
    {
        $denied = new class implements GuardedRouterOSWriteGatewayInterface {
            public function execute(WriteExecutionRequest $request, callable $operation): WriteExecutionResult
            {
                throw new RuntimeException('Synthetic guard denial.');
            }
        };

        foreach ([
            [new AdminPackageAssignController($assignRead = new FakeRouterOSReadGateway(), $denied), 'executeAssignPlan', ['synthetic-user', 1, 'add', ['operations' => [['type' => 'x']], 'router_profile_name' => 'p']]],
            [new AdminPackagePushController($pushRead = new FakeRouterOSReadGateway(), $denied), 'executePushPlan', [1, ['operations' => [['type' => 'x']], 'package' => [], 'router_names' => []]]],
            [new AdminUserDisconnectController($disconnectRead = new FakeRouterOSReadGateway(), $denied), 'executeDisconnectPlan', ['synthetic-user', ['operations' => [['type' => 'x']]]]],
        ] as [$controller, $method, $arguments]) {
            try {
                $this->invoke($controller, $method, $arguments);
                self::fail('Expected synthetic guard denial.');
            } catch (RuntimeException $failure) {
                self::assertSame('Synthetic guard denial.', $failure->getMessage());
            }
        }

        self::assertSame([], $assignRead->calls);
        self::assertSame([], $pushRead->calls);
        self::assertSame([], $disconnectRead->calls);
    }

    public function testAssignmentPushAndDisconnectKeepVerificationInsideGuardedCallback(): void
    {
        foreach ([
            AdminPackageAssignController::class => 'User Manager package assignment verification failed.',
            AdminPackagePushController::class => 'RouterOS package push verification failed.',
            AdminUserDisconnectController::class => 'RouterOS session disconnect verification failed.',
        ] as $class => $verificationMessage) {
            $source = file_get_contents((string) (new ReflectionClass($class))->getFileName());
            self::assertIsString($source);
            self::assertStringContainsString('->execute(', $source);
            self::assertStringContainsString($verificationMessage, $source);
            self::assertStringContainsString('audit_id', $source);
        }
    }

    public function testProductionSourceDoesNotReferenceRouterOSFakes(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $source = file_get_contents($file->getPathname());
                self::assertIsString($source);
                self::assertStringNotContainsString('FakeRouterOS', $source);
                self::assertStringNotContainsString('GreenNet\\Tests', $source);
            }
        }
    }

    private function invoke(object $target, string $method, array $arguments): mixed
    {
        return (new ReflectionClass($target))->getMethod($method)->invokeArgs($target, $arguments);
    }
}
