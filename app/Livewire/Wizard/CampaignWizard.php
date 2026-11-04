<?php

namespace App\Livewire\Wizard;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Livewire\Concerns\WithCompanyForm;
use App\Livewire\Concerns\WithPayments;
use App\Livewire\Concerns\WithRegionPicker;
use App\Models\Campaign;
use App\Models\Image;
use App\Models\Interest;
use App\Models\Region;
use App\Services\CampaignService;
use App\Services\ForecastService;
use App\Services\ImageService;
use App\Services\Integrations\DeepSeekClient;
use App\Services\Integrations\IntegrationException;
use App\Services\Integrations\QwenImageClient;
use App\Services\Parsers\VacancyParser;
use App\Support\Settings;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.cabinet')]
class CampaignWizard extends Component
{
    use WithCompanyForm, WithFileUploads, WithPayments, WithRegionPicker;

    public const STEPS = [
        1 => 'Вакансия',
        2 => 'Объявление',
        3 => 'Картинки',
        4 => 'Аудитория',
        5 => 'Площадки',
        6 => 'Бюджет',
        7 => 'Запуск',
    ];

    public const INCOMES = ['any' => 'Любой', 'low' => 'Низкий', 'mid' => 'Средний', 'high' => 'Высокий'];

    public const GENDERS = ['any' => 'Все', 'male' => 'Мужчины', 'female' => 'Женщины'];

    public const TONES = ['нейтральный' => 'Нейтральный', 'дружелюбный' => 'Дружелюбный', 'деловой' => 'Деловой', 'энергичный' => 'Энергичный'];

    public Campaign $campaign;

    public int $step = 1;

    // Шаг 1
    public string $sourceUrl = '';

    public string $manualText = '';

    public bool $showManual = false;

    // Шаг 2
    public string $title = '';

    public string $text = '';

    public string $longText = '';

    public string $tone = 'нейтральный';

    // Шаг 3
    public string $imageTab = 'library';

    /** @var array<int, int> */
    public array $selectedImages = [];

    /** @var array<int, string> */
    public array $focus = [];

    public array $uploads = [];

    public string $prompt = '';

    public bool $pro = false;

    public string $genFormat = '1:1';

    // Шаг 4
    public string $gender = 'any';

    public int $ageFrom = 18;

    public int $ageTo = 65;

    public bool $ageUnknown = true;

    public string $income = 'any';

    public array $interests = [];

    public string $interestsCustom = '';

    // Шаг 5
    public array $platforms = [];

    // Шаг 6
    public ?int $totalBudget = null;

    public bool $useDaily = false;

    public ?int $dailyBudget = null;

    public ?array $forecast = null;

    // Шаг 7
    public bool $confirm = false;

    public function mount(?Campaign $campaign = null)
    {
        if (! $campaign?->exists) {
            $calc = session('calc', []);
            $campaign = auth()->user()->campaigns()->create([
                'status' => CampaignStatus::Draft,
                'regions' => $calc['region_ids'] ?? [Region::MOSCOW],
                'total_budget' => $calc['budget'] ?? max(10000, Settings::float('tariff.min_total_budget')),
                'title' => isset($calc['title']) ? mb_substr(mb_convert_case($calc['title'], MB_CASE_TITLE), 0, 56) : null,
                'platforms' => array_map(fn (Platform $p) => $p->value, Platform::cases()),
            ]);
            $campaign->log('created');
            session()->forget('calc');

            return $this->redirectRoute('cabinet.campaigns.edit', $campaign);
        }

        abort_unless($campaign->user_id === auth()->id(), 404);
        if (! $campaign->status->isEditable()) {
            return $this->redirectRoute('cabinet.campaigns.show', $campaign);
        }

        $this->campaign = $campaign;
        $this->step = max(1, min(7, (int) request()->query('step', $campaign->wizard_step ?: 1)));
        $this->fillFromCampaign();
        $this->loadCompany();

        if ($this->step === 6) {
            $this->recalculate();
        }
    }

    protected function fillFromCampaign(): void
    {
        $c = $this->campaign;
        $this->sourceUrl = (string) $c->source_url;
        $this->title = (string) $c->title;
        $this->text = (string) $c->text;
        $this->longText = (string) $c->long_text;
        $this->regionIds = $c->regions ?? [];
        $this->gender = $c->gender;
        $this->ageFrom = $c->age_from;
        $this->ageTo = $c->age_to;
        $this->ageUnknown = $c->age_unknown;
        $this->income = $c->income;
        $this->interests = $c->interests ?? [];
        $this->interestsCustom = (string) $c->interests_custom;
        $this->platforms = $c->platforms ?? [];
        $this->totalBudget = $c->total_budget ? (int) $c->total_budget : null;
        $this->dailyBudget = $c->daily_budget ? (int) $c->daily_budget : null;
        $this->useDaily = $c->daily_budget !== null;
        $this->selectedImages = $c->creatives()->pluck('image_id')->unique()->values()->all();
        $this->focus = $c->creatives()->get()->pluck('focus', 'image_id')->all();
        $this->prompt = $this->defaultPrompt();
    }

    protected function defaultPrompt(): string
    {
        $city = Region::whereIn('id', $this->regionIds)->value('name');

        return app(DeepSeekClient::class)->imagePrompt($this->title ?: ($this->campaign->vacancy['title'] ?? 'вакансия'), $city);
    }

    // ───────────── Навигация ─────────────

    public function goTo(int $step): void
    {
        if ($step < $this->step || $step <= $this->campaign->wizard_step) {
            $this->step = $step;
            $this->afterStepChange();
        }
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->afterStepChange();
    }

    public function next(): void
    {
        $ok = match ($this->step) {
            1 => $this->saveStep1(),
            2 => $this->saveStep2(),
            3 => $this->saveStep3(),
            4 => $this->saveStep4(),
            5 => $this->saveStep5(),
            6 => $this->saveStep6(),
            default => true,
        };

        if ($ok) {
            $this->step = min(7, $this->step + 1);
            if ($this->campaign->wizard_step < $this->step) {
                $this->campaign->update(['wizard_step' => $this->step]);
            }
            $this->afterStepChange();
        }
    }

    protected function afterStepChange(): void
    {
        if ($this->step === 6) {
            $this->recalculate();
        }
        if ($this->step === 3 && $this->prompt === '') {
            $this->prompt = $this->defaultPrompt();
        }
        $this->dispatch('wizard-step', step: $this->step);
    }

    // ───────────── Шаг 1: ссылка на вакансию ─────────────

    public function loadVacancy(VacancyParser $parser): void
    {
        $this->validate(['sourceUrl' => 'required|url:http,https|max:2000'], [
            'sourceUrl.required' => 'Вставьте ссылку на вакансию',
            'sourceUrl.url' => 'Это не похоже на ссылку — она должна начинаться с https://',
        ]);
        @set_time_limit(90);

        $this->campaign->update(['source_url' => $this->sourceUrl, 'source_site' => VacancyParser::detectSite($this->sourceUrl), 'parse_status' => 'pending']);

        try {
            $vacancy = $parser->parse($this->sourceUrl);
        } catch (IntegrationException $e) {
            $this->campaign->update(['parse_status' => 'failed', 'parse_error' => $e->getMessage()]);
            $this->addError('sourceUrl', $e->getMessage());
            $this->showManual = true;

            return;
        } catch (Throwable $e) {
            report($e);
            $this->campaign->update(['parse_status' => 'failed', 'parse_error' => 'Не удалось открыть страницу']);
            $this->showManual = true;

            return;
        }

        $data = $vacancy->toArray();
        $this->campaign->update(['vacancy' => $data, 'parse_status' => $vacancy->hasContent() ? 'done' : 'failed']);

        // Город вакансии сразу подставляем в аудиторию, если пользователь ещё не выбирал её сам
        if ($this->campaign->wizard_step < 4 && ($region = Region::guess($vacancy->city))) {
            $this->regionIds = [$region->id];
            $this->campaign->update(['regions' => $this->regionIds]);
        }

        if ($vacancy->blocked && ! $vacancy->hasContent()) {
            $this->showManual = true;
            $this->addError('sourceUrl', 'Сайт не отдал данные вакансии. Вставьте текст вакансии ниже — объявление сделаем из него.');

            return;
        }
        if ($vacancy->archived) {
            $this->addError('sourceUrl', 'Похоже, вакансия закрыта или в архиве. Рекламировать её не получится — проверьте ссылку.');
        }

        $this->generateTexts(true);
    }

    public function useManualText(): void
    {
        $this->validate(['manualText' => 'required|string|min:30|max:8000'], ['manualText.min' => 'Добавьте хотя бы пару предложений о вакансии']);
        $first = trim(strtok($this->manualText, "\n"));
        $vacancy = array_merge($this->campaign->vacancy ?? [], [
            'title' => ($this->campaign->vacancy['title'] ?? null) ?: mb_substr($first, 0, 100),
            'description' => $this->manualText,
            'manual' => true,
        ]);
        $this->campaign->update(['vacancy' => $vacancy, 'parse_status' => 'done']);
        $this->resetErrorBag();
        $this->generateTexts(true);
    }

    protected function saveStep1(): bool
    {
        $this->validate(['sourceUrl' => 'required|url:http,https|max:2000'], [
            'sourceUrl.required' => 'Вставьте ссылку на вакансию — на неё будут переходить кандидаты',
            'sourceUrl.url' => 'Ссылка должна начинаться с https://',
        ]);
        $this->campaign->update(['source_url' => $this->sourceUrl, 'source_site' => VacancyParser::detectSite($this->sourceUrl)]);

        if (! $this->title) {
            $this->title = mb_substr((string) ($this->campaign->vacancy['title'] ?? ''), 0, 56);
        }

        return true;
    }

    // ───────────── Шаг 2: тексты ─────────────

    public function generateTexts(bool $silent = false): void
    {
        $vacancy = $this->campaign->vacancy ?? [];
        $client = app(DeepSeekClient::class);
        @set_time_limit(90);

        if (! $client->isConfigured()) {
            $this->fallbackTexts($vacancy);
            if (! $silent) {
                session()->flash('info', 'ИИ-генерация пока не подключена — заполнили тексты по данным вакансии.');
            }

            return;
        }

        try {
            $ad = $client->generateAd($vacancy, $this->campaign->source_url, $vacancy['raw_text'] ?? null, $this->tone);
            $this->title = $ad['title'] ?: $this->title;
            $this->text = $ad['text'] ?: $this->text;
            $this->longText = $ad['long_text'] ?: $this->longText;
            $this->campaign->update(['title' => $this->title, 'text' => $this->text, 'long_text' => $this->longText]);
        } catch (IntegrationException $e) {
            $this->fallbackTexts($vacancy);
            if (! $silent) {
                $this->addError('title', $e->getMessage());
            }
        }
    }

    protected function fallbackTexts(array $vacancy): void
    {
        $title = trim((string) ($vacancy['title'] ?? $this->title));
        if ($title && ! empty($vacancy['salary']) && mb_strlen($title.' — '.$vacancy['salary']) <= 56) {
            $title .= ' — '.$vacancy['salary'];
        }
        $this->title = $this->title ?: mb_substr($title, 0, 56);
        if (! $this->text) {
            $cta = 'Откликнитесь сегодня!';
            $text = '';
            foreach (array_filter([$vacancy['schedule'] ?? null, $vacancy['city'] ?? null]) as $part) {
                $part = trim(explode(',', (string) $part)[0]);
                if (mb_strlen($text.$part.'. '.$cta) <= 81) {
                    $text .= $part.'. ';
                }
            }
            $this->text = $text.$cta;
        }
        if (! $this->longText && ! empty($vacancy['description'])) {
            $this->longText = mb_substr(preg_replace('/\s+/u', ' ', (string) $vacancy['description']), 0, 217).'…';
        }
    }

    protected function saveStep2(): bool
    {
        $this->validate([
            'title' => 'required|string|min:5|max:56',
            'text' => 'required|string|min:10|max:81',
            'longText' => 'nullable|string|max:220',
        ], [
            'title.required' => 'Напишите заголовок',
            'title.max' => 'Заголовок — до 56 символов (лимит Яндекс Директа)',
            'text.required' => 'Напишите текст объявления',
            'text.max' => 'Текст — до 81 символа (лимит Яндекс Директа)',
            'longText.max' => 'Длинный текст для VK — до 220 символов',
        ]);
        $this->campaign->update(['title' => $this->title, 'text' => $this->text, 'long_text' => $this->longText ?: null]);
        $this->prompt = $this->defaultPrompt();

        return true;
    }

    // ───────────── Шаг 3: картинки ─────────────

    public function updatedUploads(ImageService $images): void
    {
        $this->validate(['uploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240'], [
            'uploads.*.image' => 'Загрузите картинку JPG, PNG или WebP',
            'uploads.*.max' => 'Картинка больше 10 МБ',
        ]);
        foreach ($this->uploads as $file) {
            try {
                $image = $images->store(auth()->user(), file_get_contents($file->getRealPath()), 'upload');
                $this->select($image->id);
            } catch (IntegrationException $e) {
                $this->addError('uploads', $e->getMessage());
            }
        }
        $this->uploads = [];
        $this->imageTab = 'library';
    }

    public function importImage(string $url, ImageService $images): void
    {
        if (! in_array($url, $this->campaign->vacancy['images'] ?? [], true)) {
            return;
        }
        try {
            $image = $images->importFromUrl(auth()->user(), $url);
            $this->select($image->id);
            if (! $image->isSuitable()) {
                session()->flash('info', 'Картинка маленькая ('.$image->width.'×'.$image->height.') — сети могут её не принять. Лучше выбрать другую.');
            }
        } catch (IntegrationException $e) {
            $this->addError('images', $e->getMessage());
        }
    }

    public function generateImage(QwenImageClient $qwen, ImageService $images): void
    {
        $this->validate(['prompt' => 'required|string|min:10|max:1500', 'genFormat' => 'in:1:1,16:9,4:5']);
        $key = 'gen:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, Settings::int('tariff.generations_per_day'))) {
            $this->addError('prompt', 'Лимит генераций на сегодня исчерпан. Попробуйте завтра или загрузите свою картинку.');

            return;
        }
        @set_time_limit(180);

        try {
            $binary = $qwen->generate($this->prompt, $this->genFormat, $this->pro);
            RateLimiter::hit($key, 86400);
            $image = $images->store(auth()->user(), $binary, 'generated', [
                'prompt' => $this->prompt,
                'model' => Settings::get($this->pro ? 'qwen_pro.model' : 'qwen.model'),
            ]);
            $this->select($image->id);
            $this->imageTab = 'library';
        } catch (IntegrationException $e) {
            $this->addError('prompt', $e->getMessage());
        }
    }

    public function toggleImage(int $id): void
    {
        if (in_array($id, $this->selectedImages, true)) {
            $this->selectedImages = array_values(array_diff($this->selectedImages, [$id]));
        } else {
            $this->select($id);
        }
    }

    protected function select(int $id): void
    {
        if (! in_array($id, $this->selectedImages, true) && Image::where('user_id', auth()->id())->whereKey($id)->exists()) {
            if (count($this->selectedImages) >= 5) {
                $this->addError('images', 'Можно выбрать до 5 картинок');

                return;
            }
            $this->selectedImages[] = $id;
            $this->focus[$id] ??= 'center';
        }
    }

    protected function saveStep3(): bool
    {
        if (! $this->selectedImages) {
            $this->addError('images', 'Выберите хотя бы одну картинку — без неё не будет показов в РСЯ и VK');

            return false;
        }
        app(ImageService::class)->buildCreatives($this->campaign, $this->selectedImages, $this->focus);

        return true;
    }

    // ───────────── Шаг 4: аудитория ─────────────

    protected function saveStep4(): bool
    {
        $this->validate([
            'regionIds' => 'required|array|min:1',
            'gender' => 'in:any,male,female',
            'ageFrom' => 'required|integer|min:14|max:80',
            'ageTo' => 'required|integer|min:14|max:80|gte:ageFrom',
            'income' => 'in:any,low,mid,high',
            'interests' => 'array',
            'interestsCustom' => 'nullable|string|max:500',
        ], ['regionIds.required' => 'Выберите хотя бы один город или регион', 'ageTo.gte' => 'Возраст «до» должен быть больше «от»']);

        if ($this->ageTo - $this->ageFrom < 3) {
            $this->addError('ageTo', 'Минимальный возрастной интервал — 3 года');

            return false;
        }

        $this->campaign->update([
            'regions' => $this->regionIds,
            'gender' => $this->gender,
            'age_from' => $this->ageFrom,
            'age_to' => $this->ageTo,
            'age_unknown' => $this->ageUnknown,
            'income' => $this->income,
            'interests' => array_values($this->interests),
            'interests_custom' => $this->interestsCustom ?: null,
        ]);

        return true;
    }

    // ───────────── Шаг 5: площадки ─────────────

    public function toggleAllPlatforms(): void
    {
        $all = array_map(fn (Platform $p) => $p->value, Platform::cases());
        $this->platforms = count($this->platforms) === count($all) ? [] : $all;
    }

    public function toggleGroup(string $group): void
    {
        $values = collect(Platform::cases())->filter(fn (Platform $p) => $p->group() === $group)->map->value->values()->all();
        $this->platforms = count(array_intersect($values, $this->platforms)) === count($values)
            ? array_values(array_diff($this->platforms, $values))
            : array_values(array_unique(array_merge($this->platforms, $values)));
    }

    protected function saveStep5(): bool
    {
        $this->platforms = array_values(array_intersect($this->platforms, array_map(fn (Platform $p) => $p->value, Platform::cases())));
        if (! $this->platforms) {
            $this->addError('platforms', 'Выберите хотя бы одну площадку');

            return false;
        }
        $this->campaign->update(['platforms' => $this->platforms]);

        return true;
    }

    // ───────────── Шаг 6: бюджет и прогноз ─────────────

    public function updatedTotalBudget(): void
    {
        $this->recalculate();
    }

    public function updatedDailyBudget(): void
    {
        $this->recalculate();
    }

    public function updatedUseDaily(): void
    {
        if ($this->useDaily && ! $this->dailyBudget) {
            $this->dailyBudget = (int) max(Settings::float('tariff.min_daily_budget'), round(($this->totalBudget ?: 0) / 7 / 100) * 100);
        }
        $this->recalculate();
    }

    public function useRecommended(): void
    {
        if ($this->forecast['recommended_budget'] ?? null) {
            $this->totalBudget = (int) $this->forecast['recommended_budget'];
            $this->recalculate();
        }
    }

    public function recalculate(): void
    {
        $this->forecast = app(ForecastService::class)->forecast(
            $this->campaign->title ?: ($this->campaign->vacancy['title'] ?? null),
            $this->regionIds ?: ($this->campaign->regions ?? []),
            $this->totalBudget ? (float) $this->totalBudget : null,
            $this->useDaily && $this->dailyBudget ? (float) $this->dailyBudget : null,
        );
    }

    protected function saveStep6(): bool
    {
        $min = (int) Settings::get('tariff.min_total_budget');
        $minDaily = (int) Settings::get('tariff.min_daily_budget');
        $this->validate([
            'totalBudget' => 'required|integer|min:'.$min.'|max:10000000',
            'dailyBudget' => $this->useDaily ? 'required|integer|min:'.$minDaily.'|lte:totalBudget' : 'nullable',
        ], [
            'totalBudget.required' => 'Укажите бюджет',
            'totalBudget.min' => 'Минимальный бюджет — '.number_format($min, 0, ',', ' ').' ₽',
            'dailyBudget.min' => 'Минимальный дневной лимит — '.number_format($minDaily, 0, ',', ' ').' ₽',
            'dailyBudget.lte' => 'Дневной лимит не может быть больше общего бюджета',
        ]);

        $this->campaign->update([
            'total_budget' => $this->totalBudget,
            'daily_budget' => $this->useDaily ? $this->dailyBudget : null,
            'cpc' => $this->forecast['cpc'] ?? null,
            'forecast_clicks' => $this->forecast['budget_clicks'] ?? null,
            'wordstat_count' => $this->forecast['searches'] ?? null,
        ]);

        return true;
    }

    // ───────────── Шаг 7: рекламодатель и оплата ─────────────

    public function launch(string $method = 'balance')
    {
        $this->validate(['confirm' => 'accepted'], ['confirm.accepted' => 'Подтвердите, что вы рекламодатель или его представитель']);
        if (! $this->saveCompany()) {
            return null;
        }

        try {
            $missing = app(CampaignService::class)->submit($this->campaign->fresh());
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }

        if ($missing <= 0) {
            session()->flash('success', '🎉 Кампания отправлена на модерацию! Обычно проверка занимает до одного рабочего дня.');

            return $this->redirectRoute('cabinet.campaigns.show', $this->campaign);
        }

        return match ($method) {
            'online' => $this->payOnline($missing, $this->campaign->id),
            'invoice' => $this->issueInvoice($missing, $this->campaign->id),
            default => $this->redirectRoute('cabinet.campaigns.show', $this->campaign),
        };
    }

    public function render()
    {
        $data = ['steps' => self::STEPS];
        $user = auth()->user();

        if ($this->step === 3) {
            $data['library'] = $user->images()->latest('id')->limit(48)->get();
            $data['vacancyImages'] = array_slice($this->campaign->vacancy['images'] ?? [], 0, 24);
            $data['qwenReady'] = app(QwenImageClient::class)->isConfigured(false) || app(QwenImageClient::class)->isConfigured(true);
            $data['qwenProReady'] = app(QwenImageClient::class)->isConfigured(true);
        }
        if ($this->step === 4) {
            $data['regions'] = $this->selectedRegions();
            $data['interestOptions'] = Interest::orderBy('sort')->pluck('name');
        }
        if ($this->step === 7) {
            $data['available'] = $user->available();
            $data['need'] = max(0, round((float) $this->campaign->total_budget - (float) $this->campaign->reserved, 2));
            $data['creative'] = $this->campaign->creatives()->where('format', '16:9')->first();
        }

        $data['previewImage'] = $this->selectedImages
            ? Image::find($this->selectedImages[0])?->url()
            : null;

        return view('livewire.wizard.campaign-wizard', $data)->title('Кампания: '.$this->campaign->displayTitle());
    }
}
