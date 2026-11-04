<?php

namespace App\Services;

use App\Models\Region;
use App\Services\Integrations\IntegrationException;
use App\Services\Integrations\WordstatClient;
use App\Support\Settings;

/**
 * Прогноз кликов.
 *  - Спрос: Вордстат по фразе «<название вакансии> вакансии» в выбранных городах, клики в месяц = запросы / делитель (5).
 *  - Бюджет: клики = бюджет / цена клика (10 ₽ города Москва и СПб, 7 ₽ остальные; смешанный выбор — средневзвешенно по спросу).
 */
class ForecastService
{
    public function __construct(protected WordstatClient $wordstat) {}

    public static function phrase(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', mb_strtolower(strip_tags($title))));
        // убираем зарплату, скобки и знаки из заголовка: «Курьер (пеший), от 80 000 ₽» → «курьер пеший»
        $title = preg_replace('/\b(от|до)\s*[\d\s]+.*$/u', '', $title);
        $title = trim(preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $title));
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $title = mb_substr($title, 0, 80);

        if ($title === '') {
            return '';
        }

        return str_contains($title, 'ваканси') ? $title : $title.' вакансии';
    }

    /**
     * @param  array<int, int>  $regionIds
     * @return array<string, mixed>
     */
    public function forecast(?string $title, array $regionIds, ?float $budget = null, ?float $dailyBudget = null): array
    {
        $regionIds = array_values(array_unique(array_map('intval', $regionIds ?: [Region::RUSSIA])));
        $premiumIds = Settings::premiumRegionIds();
        $premium = array_values(array_intersect($regionIds, $premiumIds));
        $other = array_values(array_diff($regionIds, $premiumIds));

        $cpcPremium = Settings::float('tariff.cpc_premium');
        $cpcDefault = Settings::float('tariff.cpc_default');
        $divisor = max(1, Settings::float('tariff.wordstat_divisor'));
        $phrase = $title ? static::phrase($title) : '';

        $result = [
            'phrase' => $phrase,
            'searches' => null,
            'demand_clicks' => null,
            'top_phrases' => [],
            'cpc' => null,
            'budget_clicks' => null,
            'clicks_low' => null,
            'clicks_high' => null,
            'days' => null,
            'recommended_budget' => null,
            'error' => null,
            'min_total_budget' => Settings::float('tariff.min_total_budget'),
            'min_daily_budget' => Settings::float('tariff.min_daily_budget'),
        ];

        $countPremium = $countOther = null;
        if ($phrase !== '' && $this->wordstat->isConfigured()) {
            try {
                $countPremium = $premium ? $this->wordstat->topRequests($phrase, $premium)['total'] : 0;
                $otherData = $other ? $this->wordstat->topRequests($phrase, $other) : ['total' => 0, 'results' => []];
                $countOther = $otherData['total'];
                $all = $premium && $other ? $this->wordstat->topRequests($phrase, $regionIds) : ($premium ? $this->wordstat->topRequests($phrase, $premium) : $otherData);
                $result['searches'] = $countPremium + $countOther;
                $result['top_phrases'] = array_slice(array_values(array_filter($all['results'] ?? [], fn ($r) => $r['phrase'] !== $phrase)), 0, 5);
                $result['demand_clicks'] = (int) floor($result['searches'] / $divisor);
            } catch (IntegrationException $e) {
                $result['error'] = $e->getMessage();
            }
        } elseif ($phrase !== '') {
            $result['error'] = 'Статистика Вордстата временно недоступна';
        }

        $result['cpc'] = $this->cpc($premium, $other, $countPremium, $countOther, $cpcPremium, $cpcDefault);

        if ($budget && $budget > 0) {
            $clicks = (int) floor($budget / $result['cpc']);
            $tolerance = Settings::float('tariff.forecast_tolerance') / 100;
            $result['budget_clicks'] = $clicks;
            $result['clicks_low'] = (int) floor($clicks * (1 - $tolerance));
            $result['clicks_high'] = (int) ceil($clicks * (1 + $tolerance));

            $perDay = [];
            if ($result['demand_clicks']) {
                $perDay[] = $result['demand_clicks'] / 30;
            }
            if ($dailyBudget && $dailyBudget > 0) {
                $perDay[] = $dailyBudget / $result['cpc'];
            }
            if ($perDay) {
                $result['days'] = (int) max(1, ceil($clicks / max(0.1, min($perDay))));
            }
        }

        if ($result['demand_clicks']) {
            $recommended = ceil($result['demand_clicks'] * $result['cpc'] / 500) * 500;
            $result['recommended_budget'] = (float) max($result['min_total_budget'], $recommended);
        }

        return $result;
    }

    /** Цена клика: только города Москва/СПб — дорогой тариф, только другие — обычный, смесь — средневзвешенно. */
    public function cpc(array $premium, array $other, ?int $countPremium, ?int $countOther, float $cpcPremium, float $cpcDefault): float
    {
        if ($premium && ! $other) {
            return $cpcPremium;
        }
        if (! $premium) {
            return $cpcDefault;
        }

        $wPremium = $countPremium ?? 0;
        $wOther = $countOther ?? 0;
        if ($wPremium + $wOther === 0) {
            $wPremium = count($premium);
            $wOther = count($other);
        }

        return round(($cpcPremium * $wPremium + $cpcDefault * $wOther) / ($wPremium + $wOther), 2);
    }
}
