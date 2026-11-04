<?php

namespace App\Services\Integrations;

use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** DaData: подсказки по организациям (ИНН, ОГРН, название). Ключ только на бэкенде. */
class DaDataClient
{
    protected const BASE = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs';

    public function isConfigured(): bool
    {
        return Settings::has('dadata.api_key');
    }

    /** @return array<int, array<string, mixed>> нормализованные карточки компаний */
    public function suggestParty(string $query, int $count = 7): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3 || ! $this->isConfigured()) {
            return [];
        }

        return Cache::remember('dadata:party:'.md5($query), now()->addDay(), function () use ($query, $count) {
            $endpoint = preg_match('/^\d{10}(\d{2})?$/', $query) ? '/findById/party' : '/suggest/party';

            try {
                $response = Http::withHeaders(['Authorization' => 'Token '.Settings::get('dadata.api_key')])
                    ->acceptJson()->timeout(10)
                    ->post(self::BASE.$endpoint, ['query' => $query, 'count' => $count]);
            } catch (Throwable $e) {
                Log::warning('DaData request failed', ['error' => $e->getMessage()]);

                return [];
            }

            if (! $response->successful()) {
                Log::warning('DaData error', ['status' => $response->status()]);

                return [];
            }

            return array_map([$this, 'normalize'], $response->json('suggestions', []));
        });
    }

    public function normalize(array $s): array
    {
        $d = $s['data'] ?? [];

        return [
            'value' => $s['value'] ?? '',
            'inn' => $d['inn'] ?? null,
            'kpp' => $d['kpp'] ?? null,
            'ogrn' => $d['ogrn'] ?? null,
            'type' => $d['type'] ?? 'LEGAL',
            'name_full' => $d['name']['full_with_opf'] ?? ($s['value'] ?? ''),
            'name_short' => $d['name']['short_with_opf'] ?? ($s['value'] ?? ''),
            'legal_address' => $d['address']['unrestricted_value'] ?? ($d['address']['value'] ?? null),
            'okved' => $d['okved'] ?? null,
            'management_name' => $d['management']['name'] ?? (! empty($d['fio']) ? trim(implode(' ', array_filter($d['fio']))) : null),
            'state' => $d['state']['status'] ?? null,
            'raw' => $s,
        ];
    }
}
