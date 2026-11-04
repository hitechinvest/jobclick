<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Services\ForecastService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_phrase_appends_vacancies_and_strips_salary(): void
    {
        $this->assertSame('курьер вакансии', ForecastService::phrase('Курьер'));
        $this->assertSame('курьер пеший вакансии', ForecastService::phrase('Курьер (пеший), от 80 000 ₽'));
        $this->assertSame('вакансии повар', ForecastService::phrase('Вакансии повар'));
    }

    public function test_cpc_is_10_for_moscow_and_spb_cities_only(): void
    {
        $f = app(ForecastService::class)->forecast(null, [Region::MOSCOW, Region::SPB], 3000);
        $this->assertEquals(10, $f['cpc']);
        $this->assertSame(300, $f['budget_clicks']);
    }

    public function test_cpc_is_7_for_other_cities_and_regions(): void
    {
        // 1 — «Москва и область» (регион, а не город) — обычный тариф
        $f = app(ForecastService::class)->forecast(null, [43, 1], 7000);
        $this->assertEquals(7, $f['cpc']);
        $this->assertSame(1000, $f['budget_clicks']);
    }

    public function test_wordstat_demand_divided_by_five_and_weighted_cpc(): void
    {
        Settings::set('wordstat.api_key', 'test-key');
        Http::fake([
            'searchapi.api.cloud.yandex.net/*' => function ($request) {
                $regions = $request->data()['regions'] ?? [];
                $total = $regions === ['213'] ? 3000 : ($regions === ['43'] ? 1000 : 4000);

                return Http::response(['totalCount' => (string) $total, 'results' => [['phrase' => 'курьер вакансии москва', 'count' => '1200']], 'associations' => []]);
            },
        ]);

        $f = app(ForecastService::class)->forecast('Курьер', [Region::MOSCOW, 43], 10000, 1000);

        $this->assertSame('курьер вакансии', $f['phrase']);
        $this->assertSame(4000, $f['searches']);
        $this->assertSame(800, $f['demand_clicks']); // 4000 / 5
        $this->assertEquals(9.25, $f['cpc']);          // (10*3000 + 7*1000) / 4000
        $this->assertSame(1081, $f['budget_clicks']);  // 10000 / 9.25
        $this->assertNotNull($f['days']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Api-Key test-key'));
    }

    public function test_calculator_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Сколько кандидатов вы получите?');
    }
}
