<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Services\CampaignService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/** @property Campaign $record */
class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    public const CHECKLIST = [
        'link' => 'Ссылка открывается, вакансия активна и соответствует объявлению',
        'discrimination' => 'Нет требований к полу, возрасту, национальности, внешности, месту жительства',
        'topics' => 'Нет запрещённых тем: МЛМ, «заработок без вложений», пирамиды, 18+',
        'images' => 'Картинки качественные, без чужих логотипов и ошибок в тексте',
        'advertiser' => 'Рекламодатель действует (ЕГРЮЛ), ИНН совпадает с работодателем или это объяснимо',
        'budget' => 'Бюджет достаточен для выбранных площадок',
    ];

    public const REJECT_REASONS = [
        'Дискриминационные требования в тексте объявления или вакансии',
        'Вакансия закрыта или ссылка не открывается',
        'Объявление не соответствует вакансии',
        'Запрещённая тематика (МЛМ, «заработок без вложений», 18+)',
        'Рекламодатель ликвидирован или ИНН не соответствует работодателю',
        'Другое',
    ];

    public function getTitle(): string
    {
        return '#'.$this->record->id.' · '.$this->record->displayTitle();
    }

    protected function run(callable $action, string $done, string $audit): void
    {
        try {
            $action(app(CampaignService::class));
            AuditLog::create(['user_id' => auth()->id(), 'action' => $audit, 'subject_type' => Campaign::class, 'subject_id' => $this->record->id]);
            Notification::make()->title($done)->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Не получилось')->body($e->getMessage())->danger()->send();
        }
        $this->record->refresh();
        $this->refreshFormData([]);
    }

    protected function getHeaderActions(): array
    {
        $c = $this->record;

        return [
            Action::make('approve')->label('Одобрить')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn () => $this->record->status === CampaignStatus::Moderation)
                ->modalHeading('Чек-лист модерации')
                ->modalDescription('Отметьте все пункты — тогда кампания перейдёт в «Ждут размещения».')
                ->schema([
                    CheckboxList::make('checks')->hiddenLabel()->options(self::CHECKLIST)->required()
                        ->rule(fn () => function ($attribute, $value, $fail) {
                            if (count((array) $value) < count(self::CHECKLIST)) {
                                $fail('Отметьте все пункты чек-листа');
                            }
                        }),
                ])
                ->action(fn () => $this->run(fn (CampaignService $s) => $s->approve($this->record, auth()->id()), 'Кампания одобрена. Разместите её в сетях.', 'approve')),

            Action::make('changes')->label('На доработку')->icon(Heroicon::OutlinedPencilSquare)->color('warning')
                ->visible(fn () => $this->record->status === CampaignStatus::Moderation)
                ->schema([Textarea::make('comment')->label('Что исправить')->required()->rows(4)])
                ->action(fn (array $data) => $this->run(fn (CampaignService $s) => $s->requestChanges($this->record, $data['comment'], auth()->id()), 'Отправлено на доработку', 'needs_changes')),

            Action::make('reject')->label('Отклонить')->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible(fn () => in_array($this->record->status, [CampaignStatus::Moderation, CampaignStatus::NeedsChanges, CampaignStatus::PendingPayment], true))
                ->schema([
                    Select::make('reason')->label('Причина')->options(array_combine(self::REJECT_REASONS, self::REJECT_REASONS))->required(),
                    Textarea::make('comment')->label('Комментарий для клиента')->rows(3),
                ])
                ->action(fn (array $data) => $this->run(fn (CampaignService $s) => $s->reject($this->record, trim($data['reason'].'. '.($data['comment'] ?? ''), '. '), auth()->id()), 'Кампания отклонена, деньги вернулись на баланс клиента', 'reject')),

            Action::make('zip')->label('Скачать креативы')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(fn () => route('admin.campaigns.creatives', $this->record))->openUrlInNewTab(),

            ActionGroup::make([
                Action::make('complete')->label('Завершить кампанию')->color('danger')->requiresConfirmation()
                    ->modalDescription('Остаток бюджета вернётся на баланс клиента. Не забудьте остановить кампании в сетях.')
                    ->visible(fn () => $this->record->status->isLive())
                    ->action(fn () => $this->run(fn (CampaignService $s) => $s->complete($this->record, 'admin'), 'Кампания завершена', 'complete')),
                Action::make('pause')->label('Пауза')->visible(fn () => $this->record->status === CampaignStatus::Active)
                    ->action(fn () => $this->run(fn (CampaignService $s) => $s->pause($this->record), 'Кампания на паузе', 'pause')),
                Action::make('resume')->label('Возобновить')->visible(fn () => $this->record->status === CampaignStatus::Paused)
                    ->action(fn () => $this->run(fn (CampaignService $s) => $s->resume($this->record), 'Кампания активна', 'resume')),
            ])->icon(Heroicon::OutlinedEllipsisVertical),
        ];
    }
}
