<?php

declare(strict_types=1);

namespace GreenNet\Core;

use GreenNet\Services\SiteSettingsService;
use Throwable;

class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): string
    {
        $viewPath = BASE_PATH . '/app/Views/' . $view . '.php';

        $layout = self::resolveLayout($view, $layout);
        $layoutPath = BASE_PATH . '/app/Views/' . $layout . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            return 'View not found: ' . htmlspecialchars($view);
        }

        try {
            $settingsData = SiteSettingsService::publicViewData();
        } catch (Throwable) {
            $settingsData = [];
        }

        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
        $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '/';

        $data = array_merge($data, $settingsData, [
            'current_uri' => $currentUri,
            'current_path' => $currentPath,
        ]);

        extract($data);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if (!file_exists($layoutPath)) {
            return $content;
        }

        ob_start();
        require $layoutPath;

        return ob_get_clean();
    }

    private static function resolveLayout(string $view, string $layout): string
    {
        if ($layout !== 'layouts/app') {
            return $layout;
        }

        if (str_starts_with($view, 'admin/') && $view !== 'admin/login') {
            return 'layouts/admin';
        }

        return $layout;
    }
}