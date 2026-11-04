<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Настройки, которые задаются в админке: ключи интеграций, реквизиты, тарифы.
 * Секреты хранятся зашифрованными (Crypt, ключ APP_KEY).
 */
class Settings
{
    /** Ключ => [значение по умолчанию, секрет?] */
    public const DEFINITIONS = [
        // Тарифы
        'tariff.cpc_premium' => [10, false],
        'tariff.cpc_default' => [7, false],
        'tariff.premium_region_ids' => ['213,2', false], // города Москва и Санкт-Петербург без областей
        'tariff.min_total_budget' => [3000, false],
        'tariff.min_daily_budget' => [500, false],
        'tariff.wordstat_divisor' => [5, false],
        'tariff.forecast_tolerance' => [15, false],
        'tariff.image_min_width' => [450, false],
        'tariff.image_min_height' => [450, false],
        'tariff.generations_per_day' => [20, false],

        // DeepSeek
        'deepseek.api_key' => [null, true],
        'deepseek.base_url' => ['https://api.deepseek.com', false],
        'deepseek.model' => ['deepseek-chat', false],

        // Qwen Image 3 — обычная и Pro
        'qwen.api_key' => [null, true],
        // Полный URL эндпоинта: OpenAI-совместимый .../images/generations или DashScope .../multimodal-generation/generation
        'qwen.url' => ['https://dashscope-intl.aliyuncs.com/api/v1/services/aigc/multimodal-generation/generation', false],
        'qwen.model' => ['qwen-image-3.0', false],
        'qwen_pro.api_key' => [null, true],
        'qwen_pro.url' => ['https://dashscope-intl.aliyuncs.com/api/v1/services/aigc/multimodal-generation/generation', false],
        'qwen_pro.model' => ['qwen-image-3.0-pro', false],

        // Яндекс Вордстат (Yandex Cloud Search API)
        'wordstat.api_key' => [null, true],
        'wordstat.folder_id' => [null, false],
        'wordstat.base_url' => ['https://searchapi.api.cloud.yandex.net/v2/wordstat', false],

        // DaData
        'dadata.api_key' => [null, true],
        'dadata.secret_key' => [null, true],

        // hh.ru API: токен приложения (dev.hh.ru), необязателен — без него берём данные со страницы
        'hh.token' => [null, true],
        'hh.user_agent' => ['JobClick/1.0 (admin@jobclick.ru)', false],

        // SuperJob API
        'superjob.app_key' => [null, true],

        // ЮKassa
        'yookassa.shop_id' => [null, false],
        'yookassa.secret_key' => [null, true],
        'yookassa.receipts' => [true, false],

        // OAuth
        'oauth.yandex_client_id' => [null, false],
        'oauth.yandex_client_secret' => [null, true],
        'oauth.mailru_client_id' => [null, false],
        'oauth.mailru_client_secret' => [null, true],

        // SMTP (свой сервер)
        'mail.host' => [null, false],
        'mail.port' => [465, false],
        'mail.scheme' => ['smtps', false],
        'mail.username' => [null, false],
        'mail.password' => [null, true],
        'mail.from_address' => [null, false],
        'mail.from_name' => ['JobClick', false],
        'mail.admin_emails' => [null, false],

        // Реквизиты нашей компании для счетов
        'requisites.name' => [null, false],
        'requisites.inn' => [null, false],
        'requisites.kpp' => [null, false],
        'requisites.ogrn' => [null, false],
        'requisites.address' => [null, false],
        'requisites.bank_name' => [null, false],
        'requisites.bik' => [null, false],
        'requisites.account' => [null, false],
        'requisites.corr_account' => [null, false],
        'requisites.director' => [null, false],
        'requisites.accountant' => [null, false],
        'requisites.phone' => [null, false],
        'requisites.email' => [null, false],
        'requisites.logo' => [null, false],
        'requisites.stamp' => [null, false],
        'requisites.invoice_prefix' => ['JC', false],
        'requisites.invoice_due_days' => [5, false],
        // Код системы налогообложения по справочнику ЮKassa (1 ОСН, 2 УСН доходы, 3 УСН доходы-расходы, 4 ЕНВД, 5 ЕСХН, 6 Патент)
        'requisites.tax_system_code' => [2, false],
        // Ставка НДС в чеке ЮKassa: 1 — без НДС
        'requisites.vat_code' => [1, false],
        'requisites.offer_url' => [null, false],
    ];

    public const TAX_SYSTEMS = [
        1 => 'ОСН — общая',
        2 => 'УСН «Доходы»',
        3 => 'УСН «Доходы минус расходы»',
        4 => 'ЕНВД',
        5 => 'ЕСХН',
        6 => 'Патент',
    ];

    public const VAT_CODES = [
        1 => 'Без НДС',
        2 => 'НДС 0%',
        3 => 'НДС 10%',
        4 => 'НДС 20%',
    ];

    protected static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = static::all();
        $value = $values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? (self::DEFINITIONS[$key][0] ?? null);
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $secret = self::DEFINITIONS[$key][1] ?? false;

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $stored = ($value === null || $value === '') ? null : ($secret ? Crypt::encryptString((string) $value) : (string) $value);
        Setting::updateOrCreate(['key' => $key], ['value' => $stored]);
        static::$cache = null;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::set($key, $value);
        }
    }

    public static function isSecret(string $key): bool
    {
        return self::DEFINITIONS[$key][1] ?? false;
    }

    public static function has(string $key): bool
    {
        $value = static::all()[$key] ?? null;

        return $value !== null && $value !== '';
    }

    /** Все сохранённые значения (расшифрованные). */
    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }
            $rows = Setting::all()->pluck('value', 'key')->all();
        } catch (Throwable) {
            return [];
        }

        foreach ($rows as $key => $value) {
            if ($value !== null && static::isSecret($key)) {
                try {
                    $rows[$key] = Crypt::decryptString($value);
                } catch (Throwable) {
                    $rows[$key] = null;
                }
            }
        }

        return static::$cache = $rows;
    }

    /** Применяет SMTP из настроек к конфигу почты (свой сервер заказчика). */
    public static function applyMailConfig(): void
    {
        if (static::has('mail.host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.scheme' => static::get('mail.scheme') ?: null,
                'mail.mailers.smtp.host' => static::get('mail.host'),
                'mail.mailers.smtp.port' => static::int('mail.port'),
                'mail.mailers.smtp.username' => static::get('mail.username'),
                'mail.mailers.smtp.password' => static::get('mail.password'),
            ]);
            app()->forgetInstance('mail.manager');
            Mail::clearResolvedInstances();
        }
        if (static::has('mail.from_address')) {
            config(['mail.from.address' => static::get('mail.from_address'), 'mail.from.name' => static::get('mail.from_name')]);
        }
    }

    public static function flush(): void
    {
        static::$cache = null;
    }

    public static function float(string $key): float
    {
        return (float) static::get($key);
    }

    public static function int(string $key): int
    {
        return (int) static::get($key);
    }

    public static function bool(string $key): bool
    {
        return filter_var(static::get($key), FILTER_VALIDATE_BOOL);
    }

    /** @return array<int, int> */
    public static function premiumRegionIds(): array
    {
        return collect(explode(',', (string) static::get('tariff.premium_region_ids')))
            ->map(fn ($id) => (int) trim($id))->filter()->values()->all();
    }

    /** @return array<int, string> */
    public static function adminEmails(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) static::get('mail.admin_emails')))->filter()->values()->all();
    }
}
