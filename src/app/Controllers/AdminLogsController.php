<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use PDO;

class AdminLogsController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $q = trim((string) ($_GET['q'] ?? ''));
        $level = trim((string) ($_GET['level'] ?? 'all'));

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(message LIKE :q OR context LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if ($level !== 'all') {
            $where[] = 'level = :level';
            $params['level'] = $level;
        }

        $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = Database::connection()->prepare("
            SELECT *
            FROM app_logs
            {$whereSql}
            ORDER BY id DESC
            LIMIT 300
        ");

        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = [
            'total' => count($logs),
            'info' => 0,
            'warning' => 0,
            'error' => 0,
        ];

        foreach ($logs as $log) {
            $logLevel = (string) ($log['level'] ?? 'info');

            if (isset($stats[$logLevel])) {
                $stats[$logLevel]++;
            }
        }

        return View::render('admin/logs', [
            'title' => 'سجل العمليات',
            'logs' => $logs,
            'stats' => $stats,
            'filters' => [
                'q' => $q,
                'level' => $level,
            ],
        ]);
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}