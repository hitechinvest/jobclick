<?php

namespace Tests\Feature;

use App\Services\Integrations\IntegrationException;
use App\Services\Integrations\SafeHttp;
use App\Services\Parsers\VacancyParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParserTest extends TestCase
{
    public function test_detects_supported_sites(): void
    {
        $this->assertSame('hh', VacancyParser::detectSite('https://spb.hh.ru/vacancy/1'));
        $this->assertSame('zarplata', VacancyParser::detectSite('https://www.zarplata.ru/vacancy/card/id1'));
        $this->assertSame('superjob', VacancyParser::detectSite('https://www.superjob.ru/vakansii/kurer-1.html'));
        $this->assertSame('avito', VacancyParser::detectSite('https://www.avito.ru/moskva/vakansii/kurer_123'));
        $this->assertSame('gderabota', VacancyParser::detectSite('https://kazan.gderabota.ru/vacancy/1'));
        $this->assertSame('trudvsem', VacancyParser::detectSite('https://trudvsem.ru/vacancy/card/a/b'));
        $this->assertSame('other', VacancyParser::detectSite('https://career.company.ru/jobs/1'));
    }

    public function test_hh_page_with_job_posting_when_api_is_unavailable(): void
    {
        Http::fake([
            'api.hh.ru/*' => Http::response(['errors' => [['type' => 'forbidden']]], 403),
            'hh.ru/vacancy/*' => Http::response(file_get_contents(base_path('tests/Fixtures/hh_vacancy.html'))),
        ]);

        $v = app(VacancyParser::class)->parse('https://hh.ru/vacancy/123456');

        $this->assertSame('hh', $v->site);
        $this->assertSame('Повар горячего цеха', $v->title);
        $this->assertSame('от 70 000 до 90 000 ₽', $v->salary);
        $this->assertSame('Казань', $v->city);
        $this->assertSame('ООО Вкусно', $v->employer);
        $this->assertStringContainsString('График 2/2', $v->description);
        $this->assertContains('https://img.example.ru/og/povar.jpg', $v->images);
        $this->assertContains('https://hh.ru/static/kitchen.jpg', $v->images);
        $this->assertSame('https://img.example.ru/logo.png', $v->logo);
    }

    public function test_hh_api(): void
    {
        Http::fake(['api.hh.ru/vacancies/77777777' => Http::response([
            'name' => 'Курьер', 'salary' => ['from' => 80000, 'to' => null, 'currency' => 'RUR', 'gross' => false],
            'area' => ['name' => 'Москва'], 'employer' => ['name' => 'Самокат', 'logo_urls' => ['original' => 'https://hh.ru/logo.png']],
            'schedule' => ['name' => 'Гибкий график'], 'employment' => ['name' => 'Полная занятость'],
            'description' => '<p>Доставка заказов</p>', 'archived' => false,
        ])]);

        $v = app(VacancyParser::class)->parse('https://hh.ru/vacancy/77777777?from=search');

        $this->assertSame('Курьер', $v->title);
        $this->assertSame('от 80 000 ₽', $v->salary);
        $this->assertSame('Москва', $v->city);
        $this->assertFalse($v->archived);
    }

    public function test_trudvsem_open_data(): void
    {
        Http::fake([
            'opendata.trudvsem.ru/*' => Http::response(['results' => ['vacancies' => [['vacancy' => [
                'job-name' => 'Водитель', 'salary_min' => 50000, 'salary_max' => 60000, 'duty' => 'Перевозка грузов',
                'company' => ['name' => 'ООО Транс', 'inn' => '1234567890'], 'region' => ['name' => 'Тульская область'], 'schedule' => 'Сменный график',
            ]]]]]),
            'trudvsem.ru/*' => Http::response('<html><body></body></html>'),
        ]);

        $v = app(VacancyParser::class)->parse('https://trudvsem.ru/vacancy/card/6c3ea0b0-7442-11ec-b224-57fc951f3846/69ce9448-698c-11f1-b58e-dd53c9fce633');

        $this->assertSame('Водитель', $v->title);
        $this->assertSame('от 50 000 до 60 000 ₽', $v->salary);
        $this->assertSame('1234567890', $v->employerInn);
    }

    public function test_blocked_page_is_flagged(): void
    {
        Http::fake(['www.avito.ru/*' => Http::response('<html><body>Доступ ограничен: проблема с IP. Пройдите капчу</body></html>', 403)]);

        $v = app(VacancyParser::class)->parse('https://www.avito.ru/moskva/vakansii/kurer_123');

        $this->assertTrue($v->blocked);
        $this->assertFalse($v->hasContent() && mb_strlen((string) $v->description) > 200);
    }

    public function test_ssrf_protection(): void
    {
        SafeHttp::$resolver = fn ($host) => ['127.0.0.1'];
        $this->expectException(IntegrationException::class);
        app(VacancyParser::class)->parse('https://evil.example.com/vacancy');
    }

    public function test_only_http_links(): void
    {
        $this->expectException(IntegrationException::class);
        SafeHttp::assertPublicUrl('file:///etc/passwd');
    }
}
