<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Services\RouterSettingsService;
use RuntimeException;

class RouterOSApiClient
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private int $timeout;
    private mixed $socket = null;
    private bool $connected = false;

    public function __construct(?array $settings = null, int $timeout = 5)
    {
        if ($settings === null) {
            $settings = RouterSettingsService::connectionSettings();
        }

        $this->host = trim((string) ($settings['host'] ?? ''));
        $this->port = (int) ($settings['api_port'] ?? $settings['port'] ?? 8728);
        $this->username = trim((string) ($settings['username'] ?? ''));
        $this->password = (string) ($settings['password'] ?? '');
        $this->timeout = $timeout;

        if ($this->host === '') {
            throw new RuntimeException('MikroTik host is empty.');
        }

        if ($this->port <= 0) {
            $this->port = 8728;
        }

        if ($this->username === '') {
            throw new RuntimeException('MikroTik username is empty.');
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function comm(string $command, array $attributes = []): array
    {
        return $this->command($command, $attributes);
    }

    public function run(string $command, array $attributes = []): array
    {
        return $this->command($command, $attributes);
    }

    public function command(string $command, array $attributes = []): array
    {
        $this->connect();

        $words = [$command];

        foreach ($attributes as $key => $value) {
            if (is_int($key)) {
                $words[] = (string) $value;
                continue;
            }

            $key = (string) $key;

            if (str_starts_with($key, '=')) {
                $words[] = $key . '=' . (string) $value;
            } else {
                $words[] = '=' . $key . '=' . (string) $value;
            }
        }

        $this->writeSentence($words);

        return $this->readResponse();
    }

    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $errno = 0;
        $errstr = '';

        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$socket) {
            throw new RuntimeException(
                'Cannot connect to MikroTik API ' . $this->host . ':' . $this->port . ' — ' . $errstr
            );
        }

        stream_set_timeout($socket, $this->timeout);

        $this->socket = $socket;

        $this->login();

        $this->connected = true;
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
        $this->connected = false;
    }

    private function login(): void
    {
        $this->writeSentence([
            '/login',
            '=name=' . $this->username,
            '=password=' . $this->password,
        ]);

        $this->readResponse();
    }

    private function readResponse(): array
    {
        $rows = [];

        while (true) {
            $sentence = $this->readSentence();

            if (count($sentence) === 0) {
                continue;
            }

            $reply = (string) ($sentence[0] ?? '');

            if ($reply === '!done') {
                break;
            }

            if ($reply === '!trap' || $reply === '!fatal') {
                $message = 'RouterOS API Error';

                foreach ($sentence as $word) {
                    $parsed = $this->parseWord($word);

                    if (($parsed['key'] ?? '') === 'message') {
                        $message = (string) ($parsed['value'] ?? $message);
                    }
                }

                throw new RuntimeException($message);
            }

            if ($reply === '!re') {
                $row = [];

                foreach (array_slice($sentence, 1) as $word) {
                    $parsed = $this->parseWord($word);

                    if (($parsed['key'] ?? '') !== '') {
                        $row[(string) $parsed['key']] = (string) ($parsed['value'] ?? '');
                    }
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function parseWord(string $word): array
    {
        if (!str_starts_with($word, '=')) {
            return [
                'key' => '',
                'value' => $word,
            ];
        }

        $word = substr($word, 1);
        $position = strpos($word, '=');

        if ($position === false) {
            return [
                'key' => $word,
                'value' => '',
            ];
        }

        return [
            'key' => substr($word, 0, $position),
            'value' => substr($word, $position + 1),
        ];
    }

    private function writeSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord((string) $word);
        }

        $this->writeWord('');
    }

    private function readSentence(): array
    {
        $sentence = [];

        while (true) {
            $word = $this->readWord();

            if ($word === '') {
                break;
            }

            $sentence[] = $word;
        }

        return $sentence;
    }

    private function writeWord(string $word): void
    {
        $length = strlen($word);
        $this->writeLength($length);

        if ($length > 0) {
            $written = fwrite($this->socket, $word);

            if ($written === false) {
                throw new RuntimeException('Failed writing to MikroTik API socket.');
            }
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
            $chunk = fread($this->socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Failed reading from MikroTik API socket.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function writeLength(int $length): void
    {
        if ($length < 0x80) {
            $encoded = chr($length);
        } elseif ($length < 0x4000) {
            $encoded = chr(($length >> 8) | 0x80) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $encoded = chr(($length >> 16) | 0xC0) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $encoded = chr(($length >> 24) | 0xE0) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } else {
            $encoded = chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        fwrite($this->socket, $encoded);
    }

    private function readLength(): int
    {
        $first = $this->readByte();

        if (($first & 0x80) === 0x00) {
            return $first;
        }

        if (($first & 0xC0) === 0x80) {
            return (($first & ~0xC0) << 8) + $this->readByte();
        }

        if (($first & 0xE0) === 0xC0) {
            return (($first & ~0xE0) << 16) + ($this->readByte() << 8) + $this->readByte();
        }

        if (($first & 0xF0) === 0xE0) {
            return (($first & ~0xF0) << 24)
                + ($this->readByte() << 16)
                + ($this->readByte() << 8)
                + $this->readByte();
        }

        return ($this->readByte() << 24)
            + ($this->readByte() << 16)
            + ($this->readByte() << 8)
            + $this->readByte();
    }

    private function readByte(): int
    {
        $byte = fread($this->socket, 1);

        if ($byte === false || $byte === '') {
            throw new RuntimeException('Failed reading byte from MikroTik API socket.');
        }

        return ord($byte);
    }
}