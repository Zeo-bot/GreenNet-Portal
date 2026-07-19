<?php

declare(strict_types=1);

if (!function_exists('gn_available_langs')) {
    function gn_available_langs(): array
    {
        return ['ar', 'en'];
    }
}

if (!function_exists('gn_lang')) {
    function gn_lang(): string
    {
        $cookieLang = (string) ($_COOKIE['greennet_admin_lang'] ?? '');
        $sessionLang = (string) ($_SESSION['greennet_admin_lang'] ?? '');

        $lang = $cookieLang !== '' ? $cookieLang : $sessionLang;

        if (!in_array($lang, gn_available_langs(), true)) {
            $lang = 'ar';
        }

        $_SESSION['greennet_admin_lang'] = $lang;

        return $lang;
    }
}

if (!function_exists('gn_dir')) {
    function gn_dir(?string $lang = null): string
    {
        $lang = $lang ?: gn_lang();

        return $lang === 'en' ? 'ltr' : 'rtl';
    }
}

if (!function_exists('gn_lang_file')) {
    function gn_lang_file(string $lang): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

        return $basePath . '/app/Lang/' . $lang . '.php';
    }
}

if (!function_exists('gn_translations')) {
    function gn_translations(?string $lang = null): array
    {
        static $cache = [];

        $lang = $lang ?: gn_lang();

        if (isset($cache[$lang])) {
            return $cache[$lang];
        }

        $file = gn_lang_file($lang);

        if (!is_file($file)) {
            $cache[$lang] = [];
            return $cache[$lang];
        }

        $data = require $file;

        $cache[$lang] = is_array($data) ? $data : [];

        return $cache[$lang];
    }
}

if (!function_exists('gn_t')) {
    function gn_t(string $key, ?string $fallback = null, ?string $lang = null): string
    {
        $translations = gn_translations($lang);

        if (array_key_exists($key, $translations)) {
            return (string) $translations[$key];
        }

        return $fallback ?? $key;
    }
}

if (!function_exists('__')) {
    function __(string $key, ?string $fallback = null): string
    {
        return gn_t($key, $fallback);
    }
}

if (!function_exists('gn_html_attrs')) {
    function gn_html_attrs(): string
    {
        return 'lang="' . htmlspecialchars(gn_lang()) . '" dir="' . htmlspecialchars(gn_dir()) . '"';
    }
}

if (!function_exists('gn_is_rtl')) {
    function gn_is_rtl(): bool
    {
        return gn_dir() === 'rtl';
    }
}