<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignService;
use App\Services\Parsers\VacancyParser;
use Illuminate\Console\Command;
use Throwable;

/** Раз в сутки: если вакансия закрыта или в архиве — останавливаем кампанию и просим админа выключить её в сетях. */
class CheckVacancies extends Command
{
    protected $signature = 'jobclick:check-vacancies';

    protected $description = 'Проверить, не закрыты ли вакансии активных кампаний';

    public function handle(VacancyParser $parser, CampaignService $campaigns): int
    {
        Campaign::whereIn('status', [CampaignStatus::Approved, CampaignStatus::Active, CampaignStatus::Paused])
            ->whereNotNull('source_url')
            ->where(fn ($q) => $q->whereNull('vacancy_checked_at')->orWhere('vacancy_checked_at', '<', now()->subHours(20)))
            ->each(function (Campaign $campaign) use ($parser, $campaigns) {
                try {
                    $vacancy = $parser->parse($campaign->source_url);
                    $campaign->update(['vacancy_checked_at' => now()]);
                    if ($vacancy->archived) {
                        $campaign->log('vacancy_closed');
                        $campaigns->complete($campaign, 'vacancy');
                        $this->info('Кампания #'.$campaign->id.' остановлена: вакансия закрыта');
                    }
                } catch (Throwable $e) {
                    $this->warn('Кампания #'.$campaign->id.': '.$e->getMessage());
                }
            });

        return self::SUCCESS;
    }
}
