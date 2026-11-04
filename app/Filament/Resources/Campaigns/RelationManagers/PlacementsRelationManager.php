<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Models\CampaignPlatform;
use App\Services\CampaignService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Ручное размещение: админ создаёт кампанию в Директе/VK и фиксирует её ID, ссылку и ERID. */
class PlacementsRelationManager extends RelationManager
{
    protected static string $relationship = 'placements';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Размещение в сетях';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function fields(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('external_id')->label('ID кампании в сети'),
                TextInput::make('erid')->label('ERID')->helperText('Токен маркировки из кабинета Директа / VK'),
                TextInput::make('external_url')->label('Ссылка на кампанию в кабинете')->url()->columnSpanFull(),
                TextInput::make('daily_limit')->label('Дневной лимит в сети, ₽')->numeric(),
                Select::make('status')->label('Статус')->options(CampaignPlatform::STATUSES)->required(),
                Textarea::make('notes')->label('Заметки')->rows(2)->columnSpanFull(),
            ]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->fields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')->label('Площадка')->badge(),
                TextColumn::make('status')->label('Статус')->formatStateUsing(fn ($state) => CampaignPlatform::STATUSES[$state] ?? $state)->badge()
                    ->color(fn ($state) => match ($state) {
                        'placed' => 'success', 'planned' => 'warning', 'rejected' => 'danger', default => 'gray'
                    }),
                TextColumn::make('external_id')->label('ID в сети')->placeholder('—')->url(fn (CampaignPlatform $p) => $p->external_url, true),
                TextColumn::make('erid')->label('ERID')->copyable()->placeholder('—'),
                TextColumn::make('daily_limit')->label('Лимит/день')->formatStateUsing(fn ($state) => $state ? Money::format($state) : '—'),
                TextColumn::make('placed_at')->label('Размещено')->dateTime('d.m H:i')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('place')->label('Разместил')->icon('heroicon-o-rocket-launch')->color('success')
                    ->visible(fn (CampaignPlatform $p) => $p->status === 'planned')
                    ->fillForm(fn (CampaignPlatform $p) => $p->only(['external_id', 'erid', 'external_url', 'daily_limit', 'notes']) + ['status' => 'placed'])
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('external_id')->label('ID кампании в сети')->required(),
                            TextInput::make('erid')->label('ERID')->required(),
                            TextInput::make('external_url')->label('Ссылка на кампанию в кабинете')->url()->columnSpanFull(),
                            TextInput::make('daily_limit')->label('Дневной лимит в сети, ₽')->numeric(),
                            Textarea::make('notes')->label('Заметки')->rows(2),
                        ]),
                    ])
                    ->action(function (CampaignPlatform $p, array $data) {
                        app(CampaignService::class)->markPlaced($p, collect($data)->only(['external_id', 'erid', 'external_url', 'daily_limit', 'notes'])->all(), auth()->id());
                        Notification::make()->title('Площадка отмечена размещённой')->success()->send();
                        $this->dispatch('$refresh');
                    }),
                EditAction::make()->label('Изменить'),
            ]);
    }
}
