<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Config;
use GreenNet\Models\Setting;
use Throwable;

class SiteSettingsService
{
    public static function defaults(): array
    {
        $supportPhone = Config::supportPhone();
        $countryCode = (string) Config::get('SUPPORT_COUNTRY_CODE', '963');

        return [
            'network_name' => Config::appName(),
            'app_name' => Config::appName(),
            'app_title' => Config::appTitle(),

            'support_phone' => $supportPhone,
            'support_country_code' => $countryCode,
            'support_whatsapp' => self::buildWhatsappNumber($supportPhone, $countryCode),

            'default_currency' => 'SYP',

            'theme_color' => '#16a34a',
            'site_logo_path' => '',
            'app_icon_path' => '',
            'login_background_path' => '',

            'login_welcome_text' => 'أهلاً بك في بوابة المشتركين. أدخل اسم المستخدم ورقم الهاتف المسجل للمتابعة.',
            'subscriber_welcome_text' => 'تابع اشتراكك، باقتك، حالة الدفع، وسجل الدفعات من هذه الصفحة.',
            'support_text' => 'للدعم أو التجديد، تواصل معنا عبر واتساب.',
            'footer_text' => 'GreenNet Portal',

            'whatsapp_renew_message' => "مرحبا، أريد تجديد اشتراك GreenNet\nاسم المستخدم: {username}\nالباقة: {package}\nتاريخ الانتهاء: {expires_at}",

            'show_price_to_subscriber' => '1',
            'show_quota_to_subscriber' => '1',
            'show_mikrotik_profile_to_subscriber' => '0',
        ];
    }

    public static function ensureDefaults(): void
    {
        try {
            Setting::seedDefaults(self::defaults());
        } catch (Throwable) {
            // Database may not be migrated yet.
        }
    }

    public static function all(): array
    {
        $defaults = self::defaults();

        try {
            self::ensureDefaults();
            $stored = Setting::allKeyValue();
        } catch (Throwable) {
            $stored = [];
        }

        $settings = array_merge($defaults, $stored);

        $settings['theme_color'] = self::normalizeThemeColor((string) ($settings['theme_color'] ?? '#16a34a'));
        $settings['support_whatsapp'] = self::normalizeWhatsappSettings($settings);

        foreach (['site_logo_path', 'app_icon_path', 'login_background_path'] as $pathKey) {
            $settings[$pathKey] = self::normalizePublicMediaPath((string) ($settings[$pathKey] ?? ''));
        }

        return $settings;
    }

    public static function publicViewData(): array
    {
        $settings = self::all();

        return [
            'site_settings' => $settings,

            'network_name' => $settings['network_name'],
            'app_name' => $settings['app_name'],
            'app_title' => $settings['app_title'],

            'support_phone' => $settings['support_phone'],
            'support_country_code' => $settings['support_country_code'],
            'support_whatsapp' => $settings['support_whatsapp'],

            'default_currency' => $settings['default_currency'],

            'theme_color' => $settings['theme_color'],
            'site_logo_path' => $settings['site_logo_path'],
            'app_icon_path' => $settings['app_icon_path'],
            'login_background_path' => $settings['login_background_path'],

            'login_welcome_text' => $settings['login_welcome_text'],
            'subscriber_welcome_text' => $settings['subscriber_welcome_text'],
            'support_text' => $settings['support_text'],
            'footer_text' => $settings['footer_text'],

            'whatsapp_renew_message' => $settings['whatsapp_renew_message'],

            'show_price_to_subscriber' => $settings['show_price_to_subscriber'] === '1',
            'show_quota_to_subscriber' => $settings['show_quota_to_subscriber'] === '1',
            'show_mikrotik_profile_to_subscriber' => $settings['show_mikrotik_profile_to_subscriber'] === '1',
        ];
    }

    public static function renderTemplate(string $template, array $values): string
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    public static function normalizeThemeColor(string $color): string
    {
        $color = trim($color);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            return strtolower($color);
        }

        return '#16a34a';
    }

    public static function normalizeWhatsappSettings(array $settings): string
    {
        $whatsapp = preg_replace('/\D+/', '', (string) ($settings['support_whatsapp'] ?? '')) ?? '';

        if ($whatsapp !== '') {
            return $whatsapp;
        }

        return self::buildWhatsappNumber(
            (string) ($settings['support_phone'] ?? ''),
            (string) ($settings['support_country_code'] ?? '963')
        );
    }

    public static function normalizePublicMediaPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (!str_starts_with($path, '/media/')) {
            return '';
        }

        if (str_contains($path, '..')) {
            return '';
        }

        return $path;
    }

    private static function buildWhatsappNumber(string $phone, string $countryCode): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        $countryCode = preg_replace('/\D+/', '', $countryCode) ?? '';

        if ($phone === '') {
            return $countryCode;
        }

        while (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        return $countryCode . $phone;
    }
}