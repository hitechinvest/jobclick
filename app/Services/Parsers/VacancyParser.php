<?php

namespace App\Services\Parsers;

use App\Services\Integrations\IntegrationException;
use App\Services\Integrations\SafeHttp;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Загрузка вакансии по ссылке: hh.ru, zarplata.ru, superjob.ru, avito.ru, gderabota.ru, trudvsem.ru и любые сайты с JobPosting.
 */
class VacancyParser
{
    public const SITES = [
        'hh' => 'hh.ru',
        'zarplata' => 'Зарплата.ру',
        'superjob' => 'SuperJob',
        'avito' => 'Авито Работа',
        'gderabota' => 'ГдеРабота',
        'trudvsem' => 'Работа России',
        'other' => 'Другой сайт',
    ];

    public function __construct(protected HtmlExtractor $html) {}

    public static function detectSite(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $is = fn (string $domain) => $host === $domain || str_ends_with($host, '.'.$domain);

        return match (true) {
            $is('hh.ru') || $is('headhunter.ru') => 'hh',
            $is('zarplata.ru') => 'zarplata',
            $is('superjob.ru') => 'superjob',
            $is('avito.ru') => 'avito',
            $is('gderabota.ru') => 'gderabota',
            $is('trudvsem.ru') => 'trudvsem',
            default => 'other',
        };
    }

    public function parse(string $url): ParsedVacancy
    {
        $url = trim($url);
        SafeHttp::assertPublicUrl($url);
        $site = static::detectSite($url);

        $vacancy = match ($site) {
            'hh', 'zarplata' => $this->hh($url, $site),
            'superjob' => $this->superjob($url),
            'trudvsem' => $this->trudvsem($url),
            default => null,
        };

        if (! $vacancy || ! $vacancy->hasContent() || ! $vacancy->images) {
            try {
                $fromPage = $this->fromPage($url, $site);
                $vacancy = $vacancy ? $vacancy->mergeMissing($fromPage) : $fromPage;
            } catch (IntegrationException $e) {
                if (! $vacancy) {
                    throw $e;
                }
            }
        }

        return $vacancy;
    }

    public function fromPage(string $url, string $site): ParsedVacancy
    {
        $response = SafeHttp::get($url);

        if ($response->status() === 404 || $response->status() === 410) {
            $vacancy = new ParsedVacancy($site);
            $vacancy->archived = true;

            return $vacancy;
        }

        $vacancy = $this->html->extract($response->body(), $url, $site);
        if (in_array($response->status(), [401, 403, 429], true)) {
            $vacancy->blocked = true;
        }

        return $vacancy;
    }

    /** hh.ru и zarplata.ru (одна платформа): публичный API вакансий. */
    protected function hh(string $url, string $site): ?ParsedVacancy
    {
        if (! preg_match('#/vacancy/(?:card/)?(?:id)?(\d{5,})#', $url, $m)) {
            return null;
        }

        try {
            $request = Http::withHeaders(['HH-User-Agent' => Settings::get('hh.user_agent'), 'User-Agent' => Settings::get('hh.user_agent')])
                ->acceptJson()->timeout(10);
            if ($token = Settings::get('hh.token')) {
                $request = $request->withToken($token);
            }
            $response = $request->get('https://api.hh.ru/vacancies/'.$m[1]);
        } catch (Throwable $e) {
            Log::info('hh api failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 404) {
            $vacancy = new ParsedVacancy($site);
            $vacancy->archived = true;

            return $vacancy;
        }
        if (! $response->successful()) {
            return null;
        }

        $j = $response->json();
        $salary = $j['salary_range'] ?? $j['salary'] ?? null;

        $vacancy = new ParsedVacancy(
            site: $site,
            title: $j['name'] ?? null,
            salary: $salary ? ParsedVacancy::salaryText($salary['from'] ?? null, $salary['to'] ?? null, $salary['currency'] ?? 'RUR', (bool) ($salary['gross'] ?? false)) : null,
            city: $j['area']['name'] ?? null,
            employer: $j['employer']['name'] ?? null,
            schedule: collect([$j['schedule']['name'] ?? null, $j['employment']['name'] ?? null])->filter()->join(', ') ?: null,
            description: $this->html->clean(strip_tags(str_replace(['</p>', '<br />', '<br>', '</li>'], "\n", (string) ($j['description'] ?? '')))),
            logo: $j['employer']['logo_urls']['original'] ?? $j['employer']['logo_urls']['240'] ?? null,
        );
        $vacancy->archived = (bool) ($j['archived'] ?? false);
        if ($vacancy->logo) {
            $vacancy->images[] = $vacancy->logo;
        }
        return $vacancy;
    }

    protected function superjob(string $url): ?ParsedVacancy
    {
        $key = Settings::get('superjob.app_key');
        if (! $key || ! preg_match('#-(\d{5,})\.html#', $url, $m)) {
            return null;
        }

        try {
            $response = Http::withHeaders(['X-Api-App-Id' => $key])->acceptJson()->timeout(10)
                ->get('https://api.superjob.ru/2.0/vacancies/'.$m[1].'/');
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }

        $j = $response->json();
        $vacancy = new ParsedVacancy(
            site: 'superjob',
            title: $j['profession'] ?? null,
            salary: ParsedVacancy::salaryText($j['payment_from'] ?? null, $j['payment_to'] ?? null, $j['currency'] ?? 'rub'),
            city: $j['town']['title'] ?? null,
            employer: $j['firm_name'] ?? ($j['client']['title'] ?? null),
            schedule: $j['type_of_work']['title'] ?? null,
            description: $this->html->clean(strip_tags((string) ($j['vacancyRichText'] ?? $j['candidat'] ?? ''))),
            logo: $j['client_logo'] ?? ($j['client']['client_logo'] ?? null),
        );
        $vacancy->archived = (bool) ($j['is_archive'] ?? false);
        if ($vacancy->logo) {
            $vacancy->images[] = $vacancy->logo;
        }

        return $vacancy;
    }

    /** «Работа России» — открытые данные trudvsem.ru: /vacancy/card/{компания}/{вакансия}. */
    protected function trudvsem(string $url): ?ParsedVacancy
    {
        if (! preg_match('#/vacancy/card/([0-9a-f-]{20,})/([0-9a-f-]{20,})#i', $url, $m)) {
            return null;
        }

        try {
            $response = Http::acceptJson()->timeout(15)->get('https://opendata.trudvsem.ru/api/v1/vacancies/vacancy/'.$m[1].'/'.$m[2]);
        } catch (Throwable) {
            return null;
        }

        $v = $response->successful() ? $response->json('results.vacancies.0.vacancy') : null;
        if (! $v) {
            return null;
        }

        $requirement = is_array($v['requirement'] ?? null)
            ? collect($v['requirement'])->map(fn ($val, $k) => is_scalar($val) ? $val : null)->filter()->join('. ')
            : ($v['requirement'] ?? '');

        return new ParsedVacancy(
            site: 'trudvsem',
            title: $v['job-name'] ?? null,
            salary: ParsedVacancy::salaryText($v['salary_min'] ?? null, $v['salary_max'] ?? null),
            city: $v['addresses']['address'][0]['location'] ?? ($v['region']['name'] ?? null),
            employer: $v['company']['name'] ?? null,
            schedule: $v['schedule'] ?? null,
            description: $this->html->clean(strip_tags(implode("\n", array_filter([$v['duty'] ?? null, $requirement, $v['requirements'] ?? null])))),
            employerInn: $v['company']['inn'] ?? null,
        );
    }
}
