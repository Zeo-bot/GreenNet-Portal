<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

final class AdminRouterSetupController
{
    public function index(): string
    {
        return $this->renderAdmin('admin/router_setup', [
            'title' => 'إعداد الراوتر',
        ]);
    }

    public function save(): string
    {
        return $this->index();
    }

    public function store(): string
    {
        return $this->index();
    }

    public function update(): string
    {
        return $this->index();
    }

    public function test(): string
    {
        return $this->renderAdmin('admin/router_setup', [
            'title' => 'إعداد الراوتر',
            'message' => 'تم تعطيل فحص الاتصال المباشر من Router Setup. استخدم API Diagnostics للفحص.',
            'message_type' => 'info',
        ]);
    }

    private function renderAdmin(string $view, array $data = []): string
    {
        $viewsPath = dirname(__DIR__) . '/Views';
        $viewFile = $viewsPath . '/' . $view . '.php';
        $layoutFile = $viewsPath . '/layouts/admin.php';

        if (!is_file($viewFile)) {
            return $this->plainError('View not found: ' . $viewFile);
        }

        if (!is_file($layoutFile)) {
            return $this->plainError('Layout not found: ' . $layoutFile);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        ob_start();
        require $layoutFile;

        return (string) ob_get_clean();
    }

    private function plainError(string $message): string
    {
        return '<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>GreenNet Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            padding: 40px;
        }

        .box {
            max-width: 900px;
            margin: auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #dc2626;
        }

        code {
            display: block;
            direction: ltr;
            text-align: left;
            background: #0f172a;
            color: #e5e7eb;
            padding: 14px;
            border-radius: 12px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>GreenNet Router Setup Error</h1>
        <p>تعذر تحميل الصفحة بسبب ملف ناقص:</p>
        <code>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</code>
    </div>
</body>
</html>';
    }
}