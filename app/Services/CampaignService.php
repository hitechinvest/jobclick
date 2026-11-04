<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Mail\CampaignStatusMail;
use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/** Жизненный цикл кампании: оплата → модерация → размещение админом → показы → завершение. */
class CampaignService
{
    public function __construct(
        protected BillingService $billing,
        protected ForecastService $forecast,
        protected AlertService $alerts,
    ) {}

    /** Проверка, что все шаги мастера заполнены. Возвращает список ошибок. */
    public function validateForSubmit(Campaign $campaign): array
    {
        $errors = [];
        $min = Settings::float('tariff.min_total_budget');
        $minDaily = Settings::float('tariff.min_daily_budget');

        if (! $campaign->source_url) {
            $errors[] = 'Укажите ссылку на вакансию';
        }
        if (! $campaign->title || ! $campaign->text) {
            $errors[] = 'Заполните заголовок и текст объявления';
        }
        if ($campaign->creatives()->count() === 0) {
            $errors[] = 'Выберите хотя бы одну картинку';
        }
        if (empty($campaign->regions)) {
            $errors[] = 'Выберите город или регион показа';
        }
        if (empty($campaign->platforms)) {
            $errors[] = 'Выберите площадки';
        }
        if ((float) $campaign->total_budget < $min) {
            $errors[] = 'Минимальный бюджет — '.number_format($min, 0, ',', ' ').' ₽';
        }
        if ($campaign->daily_budget !== null && (float) $campaign->daily_budget < $minDaily) {
            $errors[] = 'Минимальный дневной лимит — '.number_format($minDaily, 0, ',', ' ').' ₽';
        }
        if (! $campaign->user->company) {
            $errors[] = 'Укажите ИНН рекламодателя';
        }

        return $errors;
    }

    /** Пересчитывает цену клика и прогноз и сохраняет в кампании. */
    public function refreshForecast(Campaign $campaign): array
    {
        $result = $this->forecast->forecast($campaign->title ?: ($campaign->vacancy['title'] ?? null), $campaign->regions ?? [],
            (float) $campaign->total_budget, $campaign->daily_budget ? (float) $campaign->daily_budget : null);

        $campaign->forceFill([
            'cpc' => $result['cpc'],
            'forecast_clicks' => $result['budget_clicks'],
            'wordstat_count' => $result['searches'],
        ])->save();

        return $result;
    }

    /**
     * Отправка на модерацию. Если свободных денег не хватает — статус «Ожидает оплаты»
     * и возвращается недостающая сумма.
     */
    public function submit(Campaign $campaign): float
    {
        if ($errors = $this->validateForSubmit($campaign)) {
            throw new InvalidArgumentException(implode('. ', $errors));
        }
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::PendingPayment, CampaignStatus::NeedsChanges], true)) {
            throw new InvalidArgumentException('Кампанию в этом статусе нельзя отправить на модерацию');
        }

        $this->refreshForecast($campaign);
        $need = round((float) $campaign->total_budget - (float) $campaign->reserved, 2);

        if ($need < 0) {
            $this->billing->release($campaign, -$need, 'Бюджет кампании уменьшен');
            $need = 0;
        }

        if ($need > 0) {
            $available = $campaign->user->fresh()->available();
            if ($available + 0.001 < $need) {
                if ($campaign->status !== CampaignStatus::PendingPayment) {
                    $campaign->update(['status' => CampaignStatus::PendingPayment]);
                    $campaign->log('pending_payment', 'Не хватает '.number_format($need - $available, 2, ',', ' ').' ₽');
                }

                return round($need - $available, 2);
            }
            $this->billing->reserve($campaign, $need);
        }

        $wasChanges = $campaign->status === CampaignStatus::NeedsChanges;
        $campaign->update(['status' => CampaignStatus::Moderation, 'submitted_at' => now(), 'wizard_step' => 7]);
        $campaign->log('submitted', $wasChanges ? 'Исправления отправлены повторно' : null);
        $this->alerts->raise('moderation', 'Новая кампания на модерации: «'.$campaign->displayTitle().'», бюджет '
            .number_format((float) $campaign->total_budget, 0, ',', ' ').' ₽', $campaign);
        $this->notify($campaign, 'submitted');

        return 0;
    }

    /** После пополнения баланса — отправляем ожидающие оплаты кампании на модерацию. */
    public function processPendingPayments(User $user): void
    {
        foreach ($user->campaigns()->where('status', CampaignStatus::PendingPayment)->oldest('id')->get() as $campaign) {
            try {
                $this->submit($campaign);
            } catch (Throwable $e) {
                Log::info('Pending campaign not submitted', ['campaign' => $campaign->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function approve(Campaign $campaign, ?int $adminId = null): void
    {
        $this->assertStatus($campaign, [CampaignStatus::Moderation]);

        DB::transaction(function () use ($campaign, $adminId) {
            $campaign->update(['status' => CampaignStatus::Approved, 'approved_at' => now(), 'moderation_comment' => null]);
            $share = count($campaign->platforms ?? []) ?: 1;
            foreach ($campaign->platforms ?? [] as $platform) {
                CampaignPlatform::firstOrCreate(['campaign_id' => $campaign->id, 'platform' => $platform], [
                    'status' => 'planned',
                    'daily_limit' => $campaign->daily_budget ? round((float) $campaign->daily_budget / $share, 2) : null,
                ]);
            }
            $campaign->log('approved', null, $adminId);
        });

        $this->alerts->resolveFor($campaign, ['moderation']);
        $this->alerts->raise('placement', 'Разместите кампанию «'.$campaign->displayTitle().'» в рекламных сетях', $campaign);
        $this->notify($campaign, 'approved');
    }

    public function reject(Campaign $campaign, string $reason, ?int $adminId = null): void
    {
        $this->assertStatus($campaign, [CampaignStatus::Moderation, CampaignStatus::NeedsChanges, CampaignStatus::PendingPayment]);
        $this->billing->release($campaign, null, 'Кампания отклонена модератором');
        $campaign->update(['status' => CampaignStatus::Rejected, 'moderation_comment' => $reason, 'finished_at' => now()]);
        $campaign->log('rejected', $reason, $adminId);
        $this->alerts->resolveFor($campaign, ['moderation']);
        $this->notify($campaign, 'rejected', ['reason' => $reason]);
    }

    public function requestChanges(Campaign $campaign, string $comment, ?int $adminId = null): void
    {
        $this->assertStatus($campaign, [CampaignStatus::Moderation]);
        $campaign->update(['status' => CampaignStatus::NeedsChanges, 'moderation_comment' => $comment]);
        $campaign->log('needs_changes', $comment, $adminId);
        $this->alerts->resolveFor($campaign, ['moderation']);
        $this->notify($campaign, 'needs_changes', ['reason' => $comment]);
    }

    /** Админ отметил площадку размещённой. Когда размещены все — кампания активна. */
    public function markPlaced(CampaignPlatform $placement, array $data, ?int $adminId = null): void
    {
        $placement->fill($data + ['status' => 'placed']);
        $placement->placed_at ??= now();
        $placement->placed_by ??= $adminId;
        $placement->save();

        $campaign = $placement->campaign;
        $campaign->log('placed', $placement->platform->label().($placement->erid ? ', ERID '.$placement->erid : ''), $adminId);

        $pending = $campaign->placements()->where('status', 'planned')->count();
        if ($pending === 0 && $campaign->status === CampaignStatus::Approved) {
            $campaign->update(['status' => CampaignStatus::Active, 'started_at' => now()]);
            $campaign->log('started', null, $adminId);
            $this->alerts->resolveFor($campaign, ['placement']);
            $this->notify($campaign, 'started');
        }
    }

    public function pause(Campaign $campaign): void
    {
        $this->assertStatus($campaign, [CampaignStatus::Active]);
        $campaign->update(['status' => CampaignStatus::Paused]);
        $campaign->log('paused');
        $this->alerts->raise('paused', 'Поставьте на паузу в сетях: «'.$campaign->displayTitle().'»', $campaign);
    }

    public function resume(Campaign $campaign): void
    {
        $this->assertStatus($campaign, [CampaignStatus::Paused]);
        if ((float) $campaign->reserved < (float) $campaign->cpc) {
            throw new InvalidArgumentException('Бюджет кампании исчерпан — пополните его');
        }
        $campaign->update(['status' => CampaignStatus::Active]);
        $campaign->log('resumed');
        $this->alerts->resolveFor($campaign, ['paused']);
        $this->alerts->raise('resumed', 'Возобновите показы в сетях: «'.$campaign->displayTitle().'»', $campaign);
    }

    /** Остановка пользователем: до модерации — отмена, после запуска — завершение. Резерв возвращается. */
    public function cancel(Campaign $campaign): void
    {
        $live = $campaign->status->isLive();
        $this->assertStatus($campaign, [CampaignStatus::Draft, CampaignStatus::PendingPayment, CampaignStatus::Moderation,
            CampaignStatus::NeedsChanges, CampaignStatus::Approved, CampaignStatus::Active, CampaignStatus::Paused]);

        if ($live) {
            $this->complete($campaign, 'user');
            $this->alerts->raise('cancelled', 'Остановите в сетях: пользователь завершил «'.$campaign->displayTitle().'»', $campaign);

            return;
        }

        $this->billing->release($campaign, null, 'Кампания отменена');
        $campaign->update(['status' => CampaignStatus::Cancelled, 'finished_at' => now(), 'stop_reason' => 'user']);
        $campaign->log('cancelled');
        $this->alerts->resolveFor($campaign, ['moderation']);
    }

    /** Завершение: бюджет исчерпан / вакансия закрыта / пользователь остановил. */
    public function complete(Campaign $campaign, string $reason): void
    {
        if (! $campaign->status->isLive()) {
            return;
        }
        $this->billing->release($campaign);
        $campaign->update(['status' => CampaignStatus::Completed, 'finished_at' => now(), 'stop_reason' => $reason]);
        $campaign->placements()->whereIn('status', ['planned', 'placed', 'paused'])->update(['status' => 'stopped']);
        $campaign->log('completed', match ($reason) {
            'budget' => 'Бюджет израсходован',
            'vacancy' => 'Вакансия закрыта',
            'user' => 'Остановлена пользователем',
            default => $reason,
        });
        $this->alerts->resolveFor($campaign, ['placement', 'paused', 'resumed', 'budget_80']);

        if ($reason === 'budget') {
            $this->alerts->raise('budget_100', 'Бюджет исчерпан — остановите «'.$campaign->displayTitle().'» в Директе и VK', $campaign);
        }
        if ($reason === 'vacancy') {
            $this->alerts->raise('vacancy_closed', 'Вакансия закрыта — остановите «'.$campaign->displayTitle().'» в сетях', $campaign);
        }
        $this->notify($campaign, 'completed', ['reason' => $reason]);
    }

    /** Пополнение бюджета работающей кампании со свободного баланса. */
    public function addBudget(Campaign $campaign, float $amount): void
    {
        $this->assertStatus($campaign, [CampaignStatus::Approved, CampaignStatus::Active, CampaignStatus::Paused]);
        $this->billing->reserve($campaign, $amount);
        $campaign->update([
            'total_budget' => round((float) $campaign->total_budget + $amount, 2),
            'forecast_clicks' => (int) floor(((float) $campaign->total_budget + $amount) / max(0.01, (float) $campaign->cpc)),
            'alert_80_at' => null,
        ]);
        $campaign->log('topped_up', '+'.number_format($amount, 0, ',', ' ').' ₽');
    }

    public function notify(Campaign $campaign, string $type, array $extra = []): void
    {
        try {
            Mail::to($campaign->user->email)->queue(new CampaignStatusMail($campaign, $type, $extra));
        } catch (Throwable $e) {
            Log::warning('Campaign mail failed', ['error' => $e->getMessage()]);
        }
    }

    protected function assertStatus(Campaign $campaign, array $allowed): void
    {
        if (! in_array($campaign->status, $allowed, true)) {
            throw new InvalidArgumentException('Действие недоступно в статусе «'.$campaign->status->label().'»');
        }
    }
}
