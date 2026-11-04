<?php

namespace App\Services\Integrations;

use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Яндекс Вордстат через Yandex Cloud Search API (метод topRequests).
 * Данные — число запросов за последние 30 дней.
 */
class WordstatClient
{
    public function isConfigured(): bool
    {
        return Settings::has('wordstat.api_key');
    }

    /**
     * @param  array<int, int|string>  $regions  коды регионов Вордстата (как в гео Директа)
     * @return array{total:int, results:array<int, array{phrase:string,count:int}>, associations:array<int, array{phrase:string,count:int}>}
     */
    public function topRequests(string $phrase, array $regions = [], int $numPhrases = 10): array
    {
        if (! $this->isConfigured()) {
            throw IntegrationException::notConfigured('Яндекс Вордстат');
        }

        $phrase = mb_substr(trim(preg_replace('/\s+/u', ' ', $phrase)), 0, 400);
        $regions = array_values(array_slice(array_map('strval', $regions), 0, 100));
        sort($regions);
        $cacheKey = 'wordstat:'.md5($phrase.'|'.implode(',', $regions).'|'.$numPhrases);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($phrase, $regions, $numPhrases) {
            $body = array_filter([
                'phrase' => $phrase,
                'numPhrases' => (string) $numPhrases,
                'regions' => $regions ?: null,
                'devices' => ['DEVICE_ALL'],
                'folderId' => Settings::get('wordstat.folder_id') ?: null,
            ]);

            try {
                $response = Http::withHeaders(['Authorization' => 'Api-Key '.Settings::get('wordstat.api_key')])
                    ->acceptJson()->timeout(20)->retry(2, 500, throw: false)
                    ->post(rtrim(Settings::get('wordstat.base_url'), '/').'/topRequests', $body);
            } catch (Throwable $e) {
                Log::warning('Wordstat request failed', ['error' => $e->getMessage()]);
                throw new IntegrationException('Вордстат недоступен, попробуйте позже');
            }

            if (! $response->successful()) {
                Log::warning('Wordstat error', ['status' => $response->status(), 'body' => $response->body()]);
                throw new IntegrationException('Вордстат вернул ошибку '.$response->status());
            }

            $map = fn ($rows) => array_map(fn ($r) => ['phrase' => (string) $r['phrase'], 'count' => (int) $r['count']], $rows ?? []);

            return [
                'total' => (int) $response->json('totalCount', 0),
                'results' => $map($response->json('results')),
                'associations' => $map($response->json('associations')),
            ];
        });
    }
}
