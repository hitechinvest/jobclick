<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Загрузка внешних страниц по ссылке пользователя с защитой от SSRF:
 * только http/https, запрет внутренних адресов, не больше 3 редиректов, ответ до 5 МБ.
 */
class SafeHttp
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    /** @var callable|null подмена резолвера DNS в тестах */
    public static $resolver = null;

    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new IntegrationException('Ссылка должна начинаться с http:// или https://');
        }

        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 8080, 8443], true)) {
            throw new IntegrationException('Недопустимый порт в ссылке');
        }

        $ips = static::resolve($host);
        if ($ips === []) {
            throw new IntegrationException('Не удалось найти сайт по ссылке');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new IntegrationException('Ссылка ведёт на внутренний адрес');
            }
        }
    }

    /** @return array<int, string> */
    protected static function resolve(string $host): array
    {
        if (static::$resolver) {
            return (static::$resolver)($host);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));

        return $ips ?: array_values(array_filter((array) @gethostbynamel($host)));
    }

    /** GET страницы с ручной обработкой редиректов (каждый адрес проверяется). */
    public static function get(string $url, array $headers = [], int $timeout = 15): Response
    {
        $current = $url;

        for ($i = 0; $i <= 3; $i++) {
            static::assertPublicUrl($current);

            $response = Http::withHeaders($headers + [
                'User-Agent' => self::BROWSER_UA,
                'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
            ])->withOptions(['allow_redirects' => false])->timeout($timeout)->get($current);

            if ($response->redirect() && $response->header('Location')) {
                $current = static::absolute($response->header('Location'), $current);

                continue;
            }

            if (strlen($response->body()) > self::MAX_BYTES) {
                throw new IntegrationException('Страница слишком большая');
            }

            return $response;
        }

        throw new IntegrationException('Слишком много перенаправлений');
    }

    public static function absolute(string $href, string $base): string
    {
        $href = trim(html_entity_decode($href));
        if ($href === '' || preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $b = parse_url($base);
        $root = ($b['scheme'] ?? 'https').'://'.($b['host'] ?? '');

        if (str_starts_with($href, '//')) {
            return ($b['scheme'] ?? 'https').':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $root.$href;
        }

        $dir = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';

        return $root.$dir.$href;
    }
}
