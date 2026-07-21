<?php

declare(strict_types=1);

namespace GreenNet\Tests\Support;

use PDO;
use RuntimeException;

final class TempDatabase
{
    private string $directory;
    private string $path;
    private ?PDO $connection = null;

    public function __construct()
    {
        $this->directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'greennet-tests-'
            . bin2hex(random_bytes(12));
        $this->path = $this->directory . DIRECTORY_SEPARATOR . 'test.sqlite';

        $this->assertIsolatedPath($this->path);

        if (!mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create the isolated test directory.');
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function connection(): PDO
    {
        if ($this->connection === null) {
            $this->connection = new PDO('sqlite:' . $this->path);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return $this->connection;
    }

    public function cleanup(): void
    {
        $this->connection = null;

        foreach ([$this->path, $this->path . '-wal', $this->path . '-shm', $this->path . '-journal'] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('Unable to remove an isolated SQLite test file.');
            }
        }

        if (is_dir($this->directory) && !rmdir($this->directory)) {
            throw new RuntimeException('Unable to remove the isolated test directory.');
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    private function assertIsolatedPath(string $candidate): void
    {
        $candidate = $this->normalize($candidate);
        $liveDatabase = $this->normalize((string) GRENNET_LIVE_DATABASE);
        $liveBackups = rtrim($this->normalize((string) GRENNET_LIVE_BACKUPS), '/') . '/';

        if ($candidate === $liveDatabase || str_starts_with($candidate . '/', $liveBackups)) {
            throw new RuntimeException('Refusing to use a live GreenNet runtime path in tests.');
        }

        if (!str_starts_with($candidate, rtrim($this->normalize(sys_get_temp_dir()), '/') . '/')) {
            throw new RuntimeException('Test databases must be created under the system temporary directory.');
        }
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
