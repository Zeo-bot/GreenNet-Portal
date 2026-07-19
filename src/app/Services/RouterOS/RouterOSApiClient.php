<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Services\RouterSettingsService;
use RuntimeException;
use Throwable;

class RouterOSApiClient
{
    private string $host = '';
    private int $port = 8728;
    private string $username = '';
    private string $password = '';
    private int $timeout = 3;

    /** @var resource|null */
    private $socket = null;

    private bool $connected = false;

    public function __construct(array $settings = [])
    {
        /*
         * Important:
         * The old behavior used the passed array directly.
         * So new RouterOSApiClient(['timeout' => 3]) lost host/username/password.
         *
         * New behavior:
         * 1) Load base connection settings from RouterSettingsService / .env
         * 2) Merge any overrides, such as timeout
         * 3) Normalize aliases: port/api_port/MIKROTIK_API_PORT
         */
        $baseSettings = $this->loadSettings();
        $settings = $this->mergeSettings($baseSettings, $settings);

        $this->host = trim((string) ($settings['host'] ?? $settings['MIKROTIK_HOST'] ?? ''));

        $this->port = (int) (
            $settings['port']
            ?? $settings['api_port']
            ?? $settings['MIKROTIK_API_PORT']
            ?? 8728
        );

        $this->username = trim((string) (
            $settings['username']
            ?? $settings['MIKROTIK_USERNAME']
            ?? ''
        ));

        $this->password = (string) (
            $settings['password']
            ?? $settings['MIKROTIK_PASSWORD']
            ?? ''
        );

        $this->timeout = max(1, (int) (
            $settings['timeout']
            ?? $settings['MIKROTIK_TIMEOUT']
            ?? 3
        ));

        if ($this->port <= 0) {
            $this->port = 8728;
        }
    }

    public function connect(): void
    {
        if ($this->connected && is_resource($this->socket)) {
            return;
        }

        if ($this->host === '') {
            throw new RuntimeException('MikroTik host is empty. Check Router Setup or .env.');
        }

        if ($this->username === '') {
            throw new RuntimeException('MikroTik API username is empty. Check Router Setup or .env.');
        }

        $address = 'tcp://' . $this->host . ':' . $this->port;
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            throw new RuntimeException(
                'Router unreachable or API port closed. Host: ' .
                $this->host .
                ':' .
                $this->port .
                '. Error: ' .
                ($errstr !== '' ? $errstr : ('code ' . $errno))
            );
        }

        stream_set_timeout($socket, $this->timeout);
        stream_set_blocking($socket, true);

        $this->socket = $socket;

        try {
            $this->login();
            $this->connected = true;
        } catch (Throwable $e) {
            $this->disconnect();

            throw new RuntimeException('MikroTik API login failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function comm(string $command, array $params = []): array
    {
        $this->connect();

        $this->writeSentence($command, $params);

        return $this->readResponse();
    }

    public function run(string $command, array $params = []): array
    {
        return $this->comm($command, $params);
    }

    public function command(string $command, array $params = []): array
    {
        return $this->comm($command, $params);
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }

        $this->socket = null;
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected && is_resource($this->socket);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function login(): void
    {
        $this->writeSentence('/login', [
            'name' => $this->username,
            'password' => $this->password,
        ]);

        $this->readResponse();
    }

    private function writeSentence(string $command, array $params = []): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Socket is not connected.');
        }

        $this->writeWord($command);

        foreach ($params as $key => $value) {
            $key = (string) $key;

            if ($key === '') {
                continue;
            }

            if ($value === null) {
                $value = '';
            }

            if (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            }

            $value = (string) $value;

            if ($key[0] === '?') {
                $this->writeWord($key . '=' . $value);
                continue;
            }

            if ($key[0] === '=') {
                $this->writeWord($key . '=' . $value);
                continue;
            }

            $this->writeWord('=' . $key . '=' . $value);
        }

        $this->writeWord('');
    }

    private function readResponse(): array
    {
        $rows = [];

        while (true) {
            $sentence = $this->readSentence();

            if (count($sentence) === 0) {
                continue;
            }

            $type = (string) ($sentence[0] ?? '');

            if ($type === '!done') {
                return $rows;
            }

            if ($type === '!fatal') {
                $data = $this->parseSentence($sentence);

                throw new RuntimeException(
                    'MikroTik fatal error: ' . (string) ($data['message'] ?? 'unknown')
                );
            }

            if ($type === '!trap') {
                $data = $this->parseSentence($sentence);

                throw new RuntimeException(
                    'MikroTik trap: ' . (string) ($data['message'] ?? 'unknown')
                );
            }

            if ($type === '!re') {
                $rows[] = $this->parseSentence($sentence);
            }
        }
    }

    private function readSentence(): array
    {
        $words = [];

        while (true) {
            $word = $this->readWord();

            if ($word === '') {
                break;
            }

            $words[] = $word;
        }

        return $words;
    }

    private function parseSentence(array $sentence): array
    {
        $row = [];

        foreach ($sentence as $word) {
            if ($word === '' || $word[0] === '!') {
                continue;
            }

            if ($word[0] !== '=') {
                continue;
            }

            $parts = explode('=', substr($word, 1), 2);

            if (count($parts) === 2) {
                $row[$parts[0]] = $parts[1];
            }
        }

        return $row;
    }

    private function writeWord(string $word): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Socket is not connected.');
        }

        $payload = $this->encodeLength(strlen($word)) . $word;
        $length = strlen($payload);
        $offset = 0;

        while ($offset < $length) {
            $written = @fwrite($this->socket, substr($payload, $offset));

            if ($written === false || $written === 0) {
                $this->throwIfTimedOut();

                throw new RuntimeException('Failed to write to MikroTik API socket.');
            }

            $offset += $written;
        }
    }

    private function readWord(): string
    {
        $length = $this->readLength();

        if ($length === 0) {
            return '';
        }

        $data = '';

        while (strlen($data) < $length) {
            if (!is_resource($this->socket)) {
                throw new RuntimeException('Socket is not connected.');
            }

            $chunk = @fread($this->socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                $this->throwIfTimedOut();

                throw new RuntimeException('MikroTik API connection closed while reading.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function readLength(): int
    {
        $first = $this->readByte();

        if (($first & 0x80) === 0x00) {
            return $first;
        }

        if (($first & 0xC0) === 0x80) {
            $second = $this->readByte();

            return (($first & ~0xC0) << 8) + $second;
        }

        if (($first & 0xE0) === 0xC0) {
            $second = $this->readByte();
            $third = $this->readByte();

            return (($first & ~0xE0) << 16) + ($second << 8) + $third;
        }

        if (($first & 0xF0) === 0xE0) {
            $second = $this->readByte();
            $third = $this->readByte();
            $fourth = $this->readByte();

            return (($first & ~0xF0) << 24) + ($second << 16) + ($third << 8) + $fourth;
        }

        $second = $this->readByte();
        $third = $this->readByte();
        $fourth = $this->readByte();
        $fifth = $this->readByte();

        return ($second << 24) + ($third << 16) + ($fourth << 8) + $fifth;
    }

    private function readByte(): int
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Socket is not connected.');
        }

        $byte = @fread($this->socket, 1);

        if ($byte === false || $byte === '') {
            $this->throwIfTimedOut();

            throw new RuntimeException('MikroTik API connection closed or returned no data.');
        }

        return ord($byte);
    }

    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        if ($length < 0x4000) {
            return chr(($length >> 8) | 0x80) . chr($length & 0xFF);
        }

        if ($length < 0x200000) {
            return chr(($length >> 16) | 0xC0) .
                chr(($length >> 8) & 0xFF) .
                chr($length & 0xFF);
        }

        if ($length < 0x10000000) {
            return chr(($length >> 24) | 0xE0) .
                chr(($length >> 16) & 0xFF) .
                chr(($length >> 8) & 0xFF) .
                chr($length & 0xFF);
        }

        return chr(0xF0) .
            chr(($length >> 24) & 0xFF) .
            chr(($length >> 16) & 0xFF) .
            chr(($length >> 8) & 0xFF) .
            chr($length & 0xFF);
    }

    private function throwIfTimedOut(): void
    {
        if (!is_resource($this->socket)) {
            return;
        }

        $meta = stream_get_meta_data($this->socket);

        if (($meta['timed_out'] ?? false) === true) {
            throw new RuntimeException(
                'MikroTik API timeout after ' .
                $this->timeout .
                ' seconds. Router may be powered off, unreachable, or API service is not responding.'
            );
        }
    }

    private function loadSettings(): array
    {
        $settings = $this->envSettings();

        if (class_exists(RouterSettingsService::class) && method_exists(RouterSettingsService::class, 'connectionSettings')) {
            try {
                $serviceSettings = RouterSettingsService::connectionSettings();

                if (is_array($serviceSettings)) {
                    $settings = $this->mergeSettings($settings, $serviceSettings);
                }
            } catch (Throwable) {
                // Keep .env fallback.
            }
        }

        return $settings;
    }

    private function envSettings(): array
    {
        return [
            'host' => (string) ($_ENV['MIKROTIK_HOST'] ?? getenv('MIKROTIK_HOST') ?: ''),
            'api_port' => (int) ($_ENV['MIKROTIK_API_PORT'] ?? getenv('MIKROTIK_API_PORT') ?: 8728),
            'port' => (int) ($_ENV['MIKROTIK_API_PORT'] ?? getenv('MIKROTIK_API_PORT') ?: 8728),
            'username' => (string) ($_ENV['MIKROTIK_USERNAME'] ?? getenv('MIKROTIK_USERNAME') ?: ''),
            'password' => (string) ($_ENV['MIKROTIK_PASSWORD'] ?? getenv('MIKROTIK_PASSWORD') ?: ''),
            'timeout' => (int) ($_ENV['MIKROTIK_TIMEOUT'] ?? getenv('MIKROTIK_TIMEOUT') ?: 3),
        ];
    }

    private function mergeSettings(array $base, array $override): array
    {
        $merged = $base;

        foreach ($override as $key => $value) {
            $key = (string) $key;

            if ($key === '') {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (in_array($key, ['host', 'MIKROTIK_HOST', 'username', 'MIKROTIK_USERNAME'], true)) {
                if (trim((string) $value) === '') {
                    continue;
                }
            }

            if (in_array($key, ['port', 'api_port', 'MIKROTIK_API_PORT'], true)) {
                $port = (int) $value;

                if ($port <= 0) {
                    continue;
                }

                $merged[$key] = $port;

                if ($key === 'api_port' || $key === 'MIKROTIK_API_PORT') {
                    $merged['port'] = $port;
                }

                if ($key === 'port') {
                    $merged['api_port'] = $port;
                }

                continue;
            }

            if (in_array($key, ['timeout', 'MIKROTIK_TIMEOUT'], true)) {
                $timeout = (int) $value;

                if ($timeout <= 0) {
                    continue;
                }

                $merged['timeout'] = $timeout;
                continue;
            }

            if (in_array($key, ['password', 'MIKROTIK_PASSWORD'], true)) {
                /*
                 * Do not overwrite an existing non-empty password with an empty override.
                 * This protects cases where a form or partial override sends password as blank.
                 */
                if ((string) $value === '' && (string) ($merged['password'] ?? '') !== '') {
                    continue;
                }
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}