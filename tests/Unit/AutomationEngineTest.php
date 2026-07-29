<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Core\Database;
use GreenNet\Services\AutomationEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AutomationEngineTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new ReflectionClass(Database::class))->getProperty('connection')->setValue(null, $this->pdo);
        Database::migrate();
    }

    public function testRunnerRecordsSuccessAndIsolatesFailedJob(): void
    {
        $engine = new AutomationEngine([
            'synthetic:success' => static fn (): array => ['processed' => 2, 'succeeded' => 2, 'failed' => 0],
            'synthetic:failure' => static function (): array {
                throw new RuntimeException('Synthetic job failure.');
            },
        ]);

        $result = $engine->run();

        self::assertFalse($result['ok']);
        self::assertSame('success', $result['jobs']['synthetic:success']['status']);
        self::assertSame('failed', $result['jobs']['synthetic:failure']['status']);
        self::assertCount(2, $engine->history());
    }

    public function testActiveLockRejectsOverlappingRun(): void
    {
        $engine = new AutomationEngine(['synthetic' => static fn (): array => []]);
        $this->pdo->exec("INSERT INTO automation_lock (id, acquired_at, owner) VALUES (1, CURRENT_TIMESTAMP, 'other')");

        $result = $engine->run();

        self::assertFalse($result['ok']);
        self::assertTrue($result['locked']);
        self::assertSame([], $result['jobs']);
    }
}
