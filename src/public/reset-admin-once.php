<?php

declare(strict_types=1);

$newUsername = 'admin';
$newPassword = 'Admin@123456';

$appRoot = dirname(__DIR__);
$envFile = $appRoot . '/.env';
$dbFile = $appRoot . '/database/database.sqlite';

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$messages = [];

function qid(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT name
        FROM sqlite_master
        WHERE type = 'table'
          AND name = :name
        LIMIT 1
    ");

    $stmt->execute(['name' => $table]);

    return (bool) $stmt->fetchColumn();
}

function columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('PRAGMA table_info(' . qid($table) . ')');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_values(array_filter(array_map(
        static fn (array $row): string => (string) ($row['name'] ?? ''),
        $rows
    )));
}

function setEnvValue(string $file, string $key, string $value, array &$messages): void
{
    if (!is_file($file)) {
        $messages[] = "لم أجد ملف .env: {$file}";
        return;
    }

    $content = (string) file_get_contents($file);
    $line = $key . '=' . $value;

    if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
        $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $line, $content);
    } else {
        $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
    }

    file_put_contents($file, $content);

    $messages[] = "تم تحديث {$key} داخل .env";
}

function upsertSetting(PDO $pdo, string $key, string $value, array &$messages): void
{
    if (!tableExists($pdo, 'settings')) {
        return;
    }

    $cols = columns($pdo, 'settings');

    $keyCol = null;
    foreach (['setting_key', 'key', 'name', 'option_key'] as $candidate) {
        if (in_array($candidate, $cols, true)) {
            $keyCol = $candidate;
            break;
        }
    }

    $valueCol = null;
    foreach (['setting_value', 'value', 'option_value'] as $candidate) {
        if (in_array($candidate, $cols, true)) {
            $valueCol = $candidate;
            break;
        }
    }

    if (!$keyCol || !$valueCol) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT rowid FROM ' . qid('settings') . ' WHERE ' . qid($keyCol) . ' = :key LIMIT 1'
    );

    $stmt->execute(['key' => $key]);
    $rowid = $stmt->fetchColumn();

    if ($rowid !== false) {
        $update = $pdo->prepare(
            'UPDATE ' . qid('settings') .
            ' SET ' . qid($valueCol) . ' = :value WHERE rowid = :rowid'
        );

        $update->execute([
            'value' => $value,
            'rowid' => $rowid,
        ]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO ' . qid('settings') .
            ' (' . qid($keyCol) . ', ' . qid($valueCol) . ') VALUES (:key, :value)'
        );

        $insert->execute([
            'key' => $key,
            'value' => $value,
        ]);
    }

    $messages[] = "تم تحديث settings: {$key}";
}

function resetAdminTable(PDO $pdo, string $table, string $username, string $password, string $hash, array &$messages): void
{
    if (!tableExists($pdo, $table)) {
        return;
    }

    $cols = columns($pdo, $table);

    $usernameCol = null;
    foreach (['username', 'email', 'name', 'admin_username'] as $candidate) {
        if (in_array($candidate, $cols, true)) {
            $usernameCol = $candidate;
            break;
        }
    }

    $passwordCols = array_values(array_filter($cols, static function (string $col): bool {
        $lower = strtolower($col);
        return in_array($lower, ['password', 'password_hash', 'admin_password', 'admin_password_hash'], true);
    }));

    if (count($passwordCols) === 0) {
        return;
    }

    $setParts = [];
    $params = [];

    if ($usernameCol) {
        $setParts[] = qid($usernameCol) . ' = :username';
        $params['username'] = $username;
    }

    foreach ($passwordCols as $passwordCol) {
        $param = 'p_' . $passwordCol;
        $setParts[] = qid($passwordCol) . ' = :' . $param;

        $params[$param] = str_contains(strtolower($passwordCol), 'hash')
            ? $hash
            : $hash;
    }

    if (in_array('updated_at', $cols, true)) {
        $setParts[] = qid('updated_at') . ' = :updated_at';
        $params['updated_at'] = date('Y-m-d H:i:s');
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . qid($table))->fetchColumn();

    if ($count > 0) {
        $sql = 'UPDATE ' . qid($table) . ' SET ' . implode(', ', $setParts);

        if ($usernameCol) {
            $sql .= ' WHERE ' . qid($usernameCol) . ' = :where_username';
            $params['where_username'] = $username;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0 && $usernameCol) {
            unset($params['where_username']);
            $sql = 'UPDATE ' . qid($table) . ' SET ' . implode(', ', $setParts) . ' LIMIT 1';
            $pdo->prepare($sql)->execute($params);
        }

        $messages[] = "تم تحديث جدول {$table}";
        return;
    }

    $insertCols = [];
    $insertParams = [];

    if ($usernameCol) {
        $insertCols[] = $usernameCol;
        $insertParams[$usernameCol] = $username;
    }

    foreach ($passwordCols as $passwordCol) {
        $insertCols[] = $passwordCol;
        $insertParams[$passwordCol] = $hash;
    }

    if (in_array('created_at', $cols, true)) {
        $insertCols[] = 'created_at';
        $insertParams['created_at'] = date('Y-m-d H:i:s');
    }

    if (in_array('updated_at', $cols, true)) {
        $insertCols[] = 'updated_at';
        $insertParams['updated_at'] = date('Y-m-d H:i:s');
    }

    $sql = 'INSERT INTO ' . qid($table) .
        ' (' . implode(', ', array_map('qid', $insertCols)) . ')' .
        ' VALUES (' . implode(', ', array_map(static fn ($col): string => ':' . $col, $insertCols)) . ')';

    $pdo->prepare($sql)->execute($insertParams);

    $messages[] = "تم إنشاء admin داخل جدول {$table}";
}

setEnvValue($envFile, 'ADMIN_USERNAME', $newUsername, $messages);
setEnvValue($envFile, 'ADMIN_PASSWORD', $newPassword, $messages);

if (!is_file($dbFile)) {
    $messages[] = "لم أجد قاعدة البيانات: {$dbFile}";
} else {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    upsertSetting($pdo, 'admin_username', $newUsername, $messages);
    upsertSetting($pdo, 'admin_password', $newPassword, $messages);
    upsertSetting($pdo, 'admin_password_hash', $hash, $messages);
    upsertSetting($pdo, 'ADMIN_USERNAME', $newUsername, $messages);
    upsertSetting($pdo, 'ADMIN_PASSWORD', $newPassword, $messages);

    resetAdminTable($pdo, 'admins', $newUsername, $newPassword, $hash, $messages);
    resetAdminTable($pdo, 'admin_users', $newUsername, $newPassword, $hash, $messages);
}

header('Content-Type: text/plain; charset=utf-8');

echo "GreenNet Admin Password Reset\n";
echo "=============================\n\n";

foreach ($messages as $message) {
    echo "- {$message}\n";
}

echo "\nبيانات الدخول الجديدة:\n";
echo "Username: {$newUsername}\n";
echo "Password: {$newPassword}\n\n";
echo "مهم جداً: احذف هذا الملف الآن:\n";
echo "src/public/reset-admin-once.php\n";