<?php

declare(strict_types=1);

use GreenNet\Contracts\AuthorizedRouterOSWriterInterface;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\Models\Router;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use GreenNet\Services\RouterOS\RouterOSGatewayBundle;
use GreenNet\Services\RouterOS\RouterOSErrorNormalizer;

$repositoryRoot = dirname(__DIR__);
$sourceRoot = is_dir($repositoryRoot . '/src/app') ? $repositoryRoot . '/src' : $repositoryRoot;

spl_autoload_register(static function (string $class) use ($sourceRoot): void {
    $prefix = 'GreenNet\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $sourceRoot . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Config::load($sourceRoot . '/.env');
Database::migrate();
$_SESSION['admin_username'] = 'field-validation-cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$routerId = max(1, (int) ($argv[1] ?? 1));
$router = Router::find($routerId);
if ($router === null || empty($router['enabled'])) {
    fwrite(STDERR, "Enabled router {$routerId} was not found.\n");
    exit(2);
}

$bundle = RouterConnectionResolver::gatewayBundleForRouter($routerId, ['timeout' => 8]);
$suffix = gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
$prefix = 'GN-FV-' . $suffix;
$passwordA = 'FV-A-' . bin2hex(random_bytes(12));
$passwordB = 'FV-B-' . bin2hex(random_bytes(12));
$created = [];
$results = [
    'router_id' => $routerId,
    'router_name' => (string) ($router['name'] ?? ''),
    'prefix' => $prefix,
    'before' => [],
    'operations' => [],
    'cleanup' => [],
    'backends' => [],
];
$normalizer = new RouterOSErrorNormalizer();

$readExact = static function (string $command, string $field, string $value) use ($bundle): ?array {
    $rows = $bundle->read->read($command, ['?' . $field => $value]);
    $matches = array_values(array_filter(
        $rows,
        static fn (mixed $row): bool => is_array($row) && (string) ($row[$field] ?? '') === $value
    ));
    if (count($matches) > 1) {
        throw new RuntimeException("Ambiguous RouterOS record for {$command}.");
    }
    return $matches[0] ?? null;
};

$write = static function (
    string $operation,
    string $backend,
    string $target,
    array $commands,
    callable $verify
) use ($bundle, $routerId, $router, &$results): mixed {
    $request = new WriteExecutionRequest(
        'field_validation_' . $operation,
        $backend,
        $target,
        implode(' -> ', array_column($commands, 'command')),
        [
            'router_id' => $routerId,
            'router_identity' => (string) ($router['identity'] ?? $router['name'] ?? ''),
            'backend' => $backend,
            'target_type' => $target,
            'routeros_id' => (string) ($commands[0]['params']['numbers'] ?? ''),
            'correlation_id' => bin2hex(random_bytes(12)),
        ],
        0,
        true
    );
    $execution = $bundle->write->execute(
        $request,
        static function (AuthorizedRouterOSWriterInterface $writer) use ($commands, $verify): mixed {
            foreach ($commands as $command) {
                $writer->execute(new RouterOSWriteCommand($command['command'], $command['params']));
            }
            return $verify();
        }
    );
    $results['operations'][] = [
        'operation' => $operation,
        'backend' => $backend,
        'ok' => $execution->ok,
        'audit_id' => $execution->auditId,
        'commands' => array_column($commands, 'command'),
    ];
    return $execution->value;
};

$cleanupExact = static function (
    string $label,
    string $backend,
    string $command,
    string $id,
    callable $verify
) use ($write, &$results): void {
    if ($id === '') {
        return;
    }
    try {
        $write('cleanup_' . $label, $backend, $label, [[
            'command' => $command,
            'params' => ['numbers' => $id],
        ]], $verify);
        $results['cleanup'][] = ['target' => $label, 'id' => $id, 'ok' => true];
    } catch (Throwable $error) {
        $results['cleanup'][] = [
            'target' => $label,
            'id' => $id,
            'ok' => false,
            'error' => (new RouterOSErrorNormalizer())->normalize($error)['code'],
        ];
    }
};

$cleanupArgument = (string) ($argv[2] ?? '');
if (str_starts_with($cleanupArgument, '--cleanup-prefix=')) {
    $cleanupPrefix = substr($cleanupArgument, strlen('--cleanup-prefix='));
    if ($cleanupPrefix === '' || preg_match('/^GN-FV-[A-Za-z0-9-]+$/', $cleanupPrefix) !== 1) {
        fwrite(STDERR, "A valid GN-FV cleanup prefix is required.\n");
        exit(2);
    }
    $targets = [
        ['user-manager', '/user-manager/user-profile/print', '/user-manager/user-profile/remove', 'um_assignment'],
        ['user-manager', '/user-manager/profile-limitation/print', '/user-manager/profile-limitation/remove', 'um_profile_limitation'],
        ['user-manager', '/user-manager/user/print', '/user-manager/user/remove', 'um_user'],
        ['native-hotspot', '/ip/hotspot/user/print', '/ip/hotspot/user/remove', 'hotspot_user'],
        ['native-pppoe', '/ppp/secret/print', '/ppp/secret/remove', 'ppp_secret'],
        ['native-hotspot', '/ip/hotspot/user/profile/print', '/ip/hotspot/user/profile/remove', 'hotspot_profile'],
        ['native-pppoe', '/ppp/profile/print', '/ppp/profile/remove', 'ppp_profile'],
        ['user-manager', '/user-manager/profile/print', '/user-manager/profile/remove', 'um_profile'],
        ['user-manager', '/user-manager/limitation/print', '/user-manager/limitation/remove', 'um_limitation'],
    ];
    foreach ($targets as [$backend, $readCommand, $removeCommand, $label]) {
        foreach ($bundle->read->read($readCommand) as $row) {
            if (!is_array($row) || !str_contains(json_encode($row, JSON_UNESCAPED_SLASHES) ?: '', $cleanupPrefix)) {
                continue;
            }
            $cleanupExact(
                $label,
                $backend,
                $removeCommand,
                (string) ($row['.id'] ?? ''),
                static fn (): bool => true
            );
        }
    }
    $remaining = [];
    foreach ($targets as [, $readCommand]) {
        foreach ($bundle->read->read($readCommand) as $row) {
            if (is_array($row) && str_contains(json_encode($row, JSON_UNESCAPED_SLASHES) ?: '', $cleanupPrefix)) {
                $remaining[] = ['command' => $readCommand, 'id' => (string) ($row['.id'] ?? '')];
            }
        }
    }
    $results['cleanup_prefix'] = $cleanupPrefix;
    $results['remaining_records'] = $remaining;
    $results['cleanup_ok'] = $remaining === []
        && !array_filter($results['cleanup'], static fn (array $row): bool => empty($row['ok']));
    fwrite(STDOUT, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($results['cleanup_ok'] ? 0 : 1);
}

try {
    $identity = $bundle->read->read('/system/identity/print')[0] ?? [];
    $resource = $bundle->read->read('/system/resource/print')[0] ?? [];
    $results['identity'] = (string) ($identity['name'] ?? '');
    $results['routeros_version'] = (string) ($resource['version'] ?? '');
    $results['architecture'] = (string) ($resource['architecture-name'] ?? '');
    $results['before'] = [
        'user_manager_users' => count($bundle->read->read('/user-manager/user/print')),
        'user_manager_profiles' => count($bundle->read->read('/user-manager/profile/print')),
        'user_manager_limitations' => count($bundle->read->read('/user-manager/limitation/print')),
        'hotspot_users' => count($bundle->read->read('/ip/hotspot/user/print')),
        'ppp_secrets' => count($bundle->read->read('/ppp/secret/print')),
    ];

    $limitName = $prefix . '-LIMIT';
    $profileA = $prefix . '-PROFILE-A';
    $profileB = $prefix . '-PROFILE-B';
    $userName = strtolower($prefix . '-USER');

    $write('um_limitation_create', 'user-manager', 'limitation', [[
        'command' => '/user-manager/limitation/add',
        'params' => ['name' => $limitName, 'transfer-limit' => '10485760', 'rate-limit-rx' => '1000000', 'rate-limit-tx' => '1000000'],
    ]], static function () use ($readExact, $limitName, &$created): array {
        $row = $readExact('/user-manager/limitation/print', 'name', $limitName)
            ?? throw new RuntimeException('Created limitation not found.');
        return $created['um_limitation'] = $row;
    });

    foreach ([$profileA, $profileB] as $index => $profileName) {
        $key = $index === 0 ? 'um_profile_a' : 'um_profile_b';
        $write('um_profile_create_' . ($index + 1), 'user-manager', 'profile', [[
            'command' => '/user-manager/profile/add',
            'params' => ['name' => $profileName, 'name-for-users' => $profileName, 'starts-when' => 'first-auth', 'validity' => '1d'],
        ]], static function () use ($readExact, $profileName, $key, &$created): array {
            $row = $readExact('/user-manager/profile/print', 'name', $profileName)
                ?? throw new RuntimeException('Created profile not found.');
            return $created[$key] = $row;
        });
    }

    $write('um_profile_limitation_create', 'user-manager', 'profile-limitation', [[
        'command' => '/user-manager/profile-limitation/add',
        'params' => ['profile' => $profileA, 'limitation' => $limitName],
    ]], static function () use ($bundle, $profileA, $limitName, &$created): array {
        $rows = $bundle->read->read('/user-manager/profile-limitation/print');
        foreach ($rows as $row) {
            if (is_array($row)
                && (string) ($row['profile'] ?? '') === $profileA
                && (string) ($row['limitation'] ?? '') === $limitName) {
                return $created['um_profile_limitation'] = $row;
            }
        }
        throw new RuntimeException('Created profile limitation relation not found.');
    });

    $write('um_user_create', 'user-manager', 'user', [[
        'command' => '/user-manager/user/add',
        'params' => ['name' => $userName, 'password' => $passwordA],
    ]], static function () use ($readExact, $userName, &$created): array {
        $row = $readExact('/user-manager/user/print', 'name', $userName)
            ?? throw new RuntimeException('Created User Manager user not found.');
        return $created['um_user'] = $row;
    });

    $write('um_assign_profile_a', 'user-manager', 'user-profile', [[
        'command' => '/user-manager/user-profile/add',
        'params' => ['user' => $userName, 'profile' => $profileA],
    ]], static function () use ($bundle, $userName, $profileA, &$created): array {
        foreach ($bundle->read->read('/user-manager/user-profile/print') as $row) {
            if (is_array($row) && (string) ($row['user'] ?? '') === $userName && (string) ($row['profile'] ?? '') === $profileA) {
                return $created['um_assignment'] = $row;
            }
        }
        throw new RuntimeException('Profile A assignment not found.');
    });

    $assignmentA = (string) ($created['um_assignment']['.id'] ?? '');
    $write('um_replace_profile_b', 'user-manager', 'user-profile', [
        ['command' => '/user-manager/user-profile/remove', 'params' => ['numbers' => $assignmentA]],
        ['command' => '/user-manager/user-profile/add', 'params' => ['user' => $userName, 'profile' => $profileB]],
    ], static function () use ($bundle, $userName, $profileB, &$created): array {
        foreach ($bundle->read->read('/user-manager/user-profile/print') as $row) {
            if (is_array($row) && (string) ($row['user'] ?? '') === $userName && (string) ($row['profile'] ?? '') === $profileB) {
                return $created['um_assignment'] = $row;
            }
        }
        throw new RuntimeException('Profile B replacement not found.');
    });

    $umId = (string) ($created['um_user']['.id'] ?? '');
    foreach ([
        ['um_password_change', ['password' => $passwordB]],
        ['um_disable', ['disabled' => 'yes']],
        ['um_enable', ['disabled' => 'no']],
    ] as [$operation, $change]) {
        $write($operation, 'user-manager', 'user', [[
            'command' => '/user-manager/user/set',
            'params' => ['numbers' => $umId] + $change,
        ]], static fn (): array => $readExact('/user-manager/user/print', 'name', $userName)
            ?? throw new RuntimeException('User Manager state verification failed.'));
    }
    $results['operations'][] = [
        'operation' => 'um_reset_counters',
        'backend' => 'user-manager',
        'ok' => false,
        'status' => 'UNSUPPORTED_BY_ROUTEROS',
        'commands' => [],
    ];
    $results['backends']['user-manager'] = 'PASS';

    $hotspotServers = $bundle->read->read('/ip/hotspot/print');
    if ($hotspotServers === []) {
        $results['backends']['native-hotspot'] = 'NOT_CONFIGURED';
    } else {
        $hotspotProfile = $prefix . '-HS-PROFILE';
        $hotspotUser = strtolower($prefix . '-HS-USER');
        $write('hotspot_profile_create', 'native-hotspot', 'profile', [[
            'command' => '/ip/hotspot/user/profile/add',
            'params' => ['name' => $hotspotProfile, 'rate-limit' => '1M/1M', 'comment' => $prefix],
        ]], static function () use ($readExact, $hotspotProfile, &$created): array {
            return $created['hotspot_profile'] = $readExact('/ip/hotspot/user/profile/print', 'name', $hotspotProfile)
                ?? throw new RuntimeException('Hotspot profile not found.');
        });
        $write('hotspot_user_create', 'native-hotspot', 'user', [[
            'command' => '/ip/hotspot/user/add',
            'params' => ['name' => $hotspotUser, 'password' => $passwordA, 'profile' => $hotspotProfile, 'disabled' => 'no', 'comment' => $prefix],
        ]], static function () use ($readExact, $hotspotUser, &$created): array {
            return $created['hotspot_user'] = $readExact('/ip/hotspot/user/print', 'name', $hotspotUser)
                ?? throw new RuntimeException('Hotspot user not found.');
        });
        $hotspotId = (string) ($created['hotspot_user']['.id'] ?? '');
        foreach ([
            ['hotspot_password_change', ['password' => $passwordB]],
            ['hotspot_disable', ['disabled' => 'yes']],
            ['hotspot_enable', ['disabled' => 'no']],
            ['hotspot_reset_counters', null],
        ] as [$operation, $change]) {
            $write($operation, 'native-hotspot', 'user', [[
                'command' => $change === null ? '/ip/hotspot/user/reset-counters' : '/ip/hotspot/user/set',
                'params' => ['numbers' => $hotspotId] + ($change ?? []),
            ]], static fn (): array => $readExact('/ip/hotspot/user/print', 'name', $hotspotUser)
                ?? throw new RuntimeException('Hotspot verification failed.'));
        }
        $results['backends']['native-hotspot'] = 'PASS';
    }

    $pppoeServers = $bundle->read->read('/interface/pppoe-server/server/print');
    if ($pppoeServers === []) {
        $results['backends']['native-pppoe'] = 'NOT_CONFIGURED';
    } else {
        $pppProfile = $prefix . '-PPP-PROFILE';
        $pppUser = strtolower($prefix . '-PPP-USER');
        $write('ppp_profile_create', 'native-pppoe', 'profile', [[
            'command' => '/ppp/profile/add',
            'params' => ['name' => $pppProfile, 'rate-limit' => '1M/1M', 'comment' => $prefix],
        ]], static function () use ($readExact, $pppProfile, &$created): array {
            return $created['ppp_profile'] = $readExact('/ppp/profile/print', 'name', $pppProfile)
                ?? throw new RuntimeException('PPP profile not found.');
        });
        $write('ppp_secret_create', 'native-pppoe', 'secret', [[
            'command' => '/ppp/secret/add',
            'params' => ['name' => $pppUser, 'password' => $passwordA, 'service' => 'pppoe', 'profile' => $pppProfile, 'disabled' => 'no', 'comment' => $prefix],
        ]], static function () use ($readExact, $pppUser, &$created): array {
            return $created['ppp_secret'] = $readExact('/ppp/secret/print', 'name', $pppUser)
                ?? throw new RuntimeException('PPP secret not found.');
        });
        $pppId = (string) ($created['ppp_secret']['.id'] ?? '');
        foreach ([
            ['ppp_password_change', ['password' => $passwordB]],
            ['ppp_disable', ['disabled' => 'yes']],
            ['ppp_enable', ['disabled' => 'no']],
        ] as [$operation, $change]) {
            $write($operation, 'native-pppoe', 'secret', [[
                'command' => '/ppp/secret/set',
                'params' => ['numbers' => $pppId] + $change,
            ]], static fn (): array => $readExact('/ppp/secret/print', 'name', $pppUser)
                ?? throw new RuntimeException('PPP verification failed.'));
        }
        $results['backends']['native-pppoe'] = 'PASS';
    }
} catch (Throwable $error) {
    $normalized = $normalizer->normalize($error);
    $results['fatal'] = ['code' => $normalized['code'], 'message' => $normalized['message']];
} finally {
    $cleanupExact('hotspot_user', 'native-hotspot', '/ip/hotspot/user/remove', (string) ($created['hotspot_user']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('hotspot_profile', 'native-hotspot', '/ip/hotspot/user/profile/remove', (string) ($created['hotspot_profile']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('ppp_secret', 'native-pppoe', '/ppp/secret/remove', (string) ($created['ppp_secret']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('ppp_profile', 'native-pppoe', '/ppp/profile/remove', (string) ($created['ppp_profile']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('um_assignment', 'user-manager', '/user-manager/user-profile/remove', (string) ($created['um_assignment']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('um_user', 'user-manager', '/user-manager/user/remove', (string) ($created['um_user']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('um_profile_limitation', 'user-manager', '/user-manager/profile-limitation/remove', (string) ($created['um_profile_limitation']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('um_profile_a', 'user-manager', '/user-manager/profile/remove', (string) ($created['um_profile_a']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('um_profile_b', 'user-manager', '/user-manager/profile/remove', (string) ($created['um_profile_b']['.id'] ?? ''), static fn (): bool => true);
    $cleanupExact('um_limitation', 'user-manager', '/user-manager/limitation/remove', (string) ($created['um_limitation']['.id'] ?? ''), static fn (): bool => true);
}

$remaining = [];
foreach ([
    '/user-manager/user/print',
    '/user-manager/profile/print',
    '/user-manager/limitation/print',
    '/user-manager/user-profile/print',
    '/user-manager/profile-limitation/print',
    '/ip/hotspot/user/print',
    '/ip/hotspot/user/profile/print',
    '/ppp/secret/print',
    '/ppp/profile/print',
] as $command) {
    try {
        foreach ($bundle->read->read($command) as $row) {
            if (is_array($row) && str_contains(json_encode($row, JSON_UNESCAPED_SLASHES) ?: '', $prefix)) {
                $remaining[] = ['command' => $command, 'id' => (string) ($row['.id'] ?? '')];
            }
        }
    } catch (Throwable $error) {
        $remaining[] = ['command' => $command, 'error' => $normalizer->normalize($error)['code']];
    }
}
$results['after'] = [
    'user_manager_users' => count($bundle->read->read('/user-manager/user/print')),
    'user_manager_profiles' => count($bundle->read->read('/user-manager/profile/print')),
    'user_manager_limitations' => count($bundle->read->read('/user-manager/limitation/print')),
    'hotspot_users' => count($bundle->read->read('/ip/hotspot/user/print')),
    'ppp_secrets' => count($bundle->read->read('/ppp/secret/print')),
];
$results['remaining_records'] = $remaining;
$results['cleanup_ok'] = !array_filter($results['cleanup'], static fn (array $row): bool => empty($row['ok']));
$results['cleanup_ok'] = $results['cleanup_ok'] && $remaining === [];
$auditRows = Database::connection()->query(
    "SELECT * FROM api_audit_logs WHERE action LIKE 'field_validation_%' ORDER BY id DESC LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);
$databaseBytes = @file_get_contents((string) Config::get('DB_DATABASE', '')) ?: '';
$results['secret_occurrences'] = [
    'audit_rows' => substr_count(serialize($auditRows), $passwordA) + substr_count(serialize($auditRows), $passwordB),
    'database_bytes' => substr_count($databaseBytes, $passwordA) + substr_count($databaseBytes, $passwordB),
    'session' => substr_count(serialize($_SESSION), $passwordA) + substr_count(serialize($_SESSION), $passwordB),
];
fwrite(STDOUT, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$secretLeak = array_sum($results['secret_occurrences']) > 0;
exit(isset($results['fatal']) || !$results['cleanup_ok'] || $secretLeak ? 1 : 0);
