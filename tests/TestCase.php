<?php

namespace Tests;

use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Creative;
use App\Models\Image;
use App\Models\Region;
use App\Models\User;
use App\Services\Integrations\SafeHttp;
use App\Support\Settings;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        SafeHttp::$resolver = fn (string $host) => ['93.184.216.34'];
    }

    protected function tearDown(): void
    {
        SafeHttp::$resolver = null;
        Settings::flush();
        parent::tearDown();
    }

    protected function seedRegions(): void
    {
        $this->seed(RegionSeeder::class);
    }

    /** Полностью заполненная кампания, готовая к отправке на модерацию. */
    protected function readyCampaign(array $attributes = [], ?User $user = null): Campaign
    {
        $user ??= User::factory()->create();
        if (! $user->company) {
            Company::create(['user_id' => $user->id, 'inn' => '7707083893', 'name_full' => 'ПАО Сбербанк', 'state' => 'ACTIVE']);
        }

        $campaign = $user->campaigns()->create(array_merge([
            'source_url' => 'https://hh.ru/vacancy/123456',
            'title' => 'Курьер — от 80 000 ₽',
            'text' => 'Свободный график, выплаты каждую неделю. Откликнитесь!',
            'regions' => [Region::MOSCOW],
            'platforms' => [Platform::YandexRsya->value, Platform::Vkontakte->value],
            'total_budget' => 3000,
            'cpc' => 10,
        ], $attributes));

        $image = Image::create(['user_id' => $user->id, 'path' => 'images/x.jpg', 'width' => 1080, 'height' => 1080, 'source' => 'upload', 'sha256' => str_repeat('a', 64)]);
        Creative::create(['campaign_id' => $campaign->id, 'image_id' => $image->id, 'format' => '1:1', 'path' => 'creatives/x.jpg', 'width' => 1080, 'height' => 1080]);

        return $campaign->fresh();
    }
}
