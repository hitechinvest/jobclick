<?php

namespace App\Filament\Resources\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Resources\Campaigns\RelationManagers\NetworkStatsRelationManager;
use App\Filament\Resources\Campaigns\RelationManagers\PlacementsRelationManager;
use App\Livewire\Wizard\CampaignWizard;
use App\Models\Campaign;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Кампании';

    protected static ?string $navigationLabel = 'Кампании';

    protected static ?string $modelLabel = 'кампания';

    protected static ?string $pluralModelLabel = 'Кампании';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = Campaign::whereIn('status', [CampaignStatus::Moderation, CampaignStatus::Approved])->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user.company'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('title')->label('Объявление')->searchable()->limit(45)
                    ->description(fn (Campaign $c) => $c->user->company?->displayName() ?? $c->user->email),
                TextColumn::make('status')->label('Статус')->badge()->sortable(),
                TextColumn::make('total_budget')->label('Бюджет')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('spent')->label('Потрачено')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('clicks')->label('Клики')->sortable(),
                TextColumn::make('cpc')->label('CPC')->formatStateUsing(fn ($state) => $state ? Money::format($state) : '—'),
                TextColumn::make('submitted_at')->label('На модерацию')->dateTime('d.m.Y H:i')->sortable()->since()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(CampaignStatus::options()),
            ])
            ->recordActions([ViewAction::make()->label('Открыть')])
            ->recordUrl(fn (Campaign $c) => static::getUrl('view', ['record' => $c]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                Section::make('Объявление')->columnSpan(2)->schema([
                    TextEntry::make('status')->label('Статус')->badge(),
                    TextEntry::make('title')->label('Заголовок')->copyable()->weight('bold'),
                    TextEntry::make('text')->label('Текст (Директ, короткое описание VK)')->copyable(),
                    TextEntry::make('long_text')->label('Длинный текст VK')->copyable()->placeholder('—'),
                    TextEntry::make('source_url')->label('Ссылка на вакансию')->url(fn ($state) => $state, true)->copyable(),
                    TextEntry::make('vacancy.description')->label('Текст вакансии')->limit(600)->placeholder('—'),
                    TextEntry::make('moderation_comment')->label('Комментарий модератора')->placeholder('—'),
                ]),
                Section::make('Рекламодатель')->columnSpan(1)->schema([
                    TextEntry::make('user.company.name_full')->label('Наименование')->copyable()->placeholder('не указан'),
                    TextEntry::make('user.company.inn')->label('ИНН конечного рекламодателя')->copyable()->weight('bold'),
                    TextEntry::make('user.company.kpp')->label('КПП')->placeholder('—'),
                    TextEntry::make('user.company.state')->label('Статус в ЕГРЮЛ')->badge()
                        ->color(fn ($state) => ! $state || $state === 'ACTIVE' ? 'success' : 'danger')->placeholder('нет данных'),
                    TextEntry::make('vacancy.employer')->label('Работодатель в вакансии')->placeholder('—'),
                    TextEntry::make('user.email')->label('E-mail пользователя')->copyable(),
                ]),
            ]),
            Section::make('Пакет для размещения')->columnSpanFull()->description('Всё, что нужно, чтобы вручную создать кампании в Директе и VK Рекламе.')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('regions')->label('География')->state(fn (Campaign $c) => $c->regionNames()),
                    TextEntry::make('gender')->label('Пол')->state(fn (Campaign $c) => CampaignWizard::GENDERS[$c->gender]),
                    TextEntry::make('age')->label('Возраст')->state(fn (Campaign $c) => $c->age_from.'–'.$c->age_to.($c->age_unknown ? ' + неизвестный' : '')),
                    TextEntry::make('income')->label('Доход')->state(fn (Campaign $c) => CampaignWizard::INCOMES[$c->income]),
                    TextEntry::make('interests_all')->label('Интересы')->state(fn (Campaign $c) => trim(collect($c->interests ?? [])->join(', ').($c->interests_custom ? '; '.$c->interests_custom : ''), '; ') ?: '—'),
                    TextEntry::make('platforms')->label('Площадки')->state(fn (Campaign $c) => collect($c->platformEnums())->map->label()->join(', ')),
                    TextEntry::make('total_budget')->label('Бюджет / дневной лимит')->state(fn (Campaign $c) => Money::format($c->total_budget).' / '.($c->daily_budget ? Money::format($c->daily_budget) : 'без лимита')),
                    TextEntry::make('cpc')->label('Цена клика для клиента')->state(fn (Campaign $c) => Money::format($c->cpc ?? 0)),
                    TextEntry::make('forecast_clicks')->label('Прогноз кликов / спрос Вордстата')->state(fn (Campaign $c) => ($c->forecast_clicks ?? '—').' / '.($c->wordstat_count ?? '—')),
                ]),
                RepeatableEntry::make('tracking')->label('Трекинг-ссылки — ставьте их в объявления вместо ссылки на вакансию')
                    ->state(fn (Campaign $c) => collect($c->platformEnums())->map(fn (Platform $p) => ['platform' => $p->label(), 'url' => $c->trackingUrl($p->value)])->all())
                    ->schema([
                        TextEntry::make('platform')->hiddenLabel()->weight('bold'),
                        TextEntry::make('url')->hiddenLabel()->copyable()->fontFamily('mono'),
                    ])->columns(2),
            ]),
            Section::make('Креативы')->columnSpanFull()->schema([
                RepeatableEntry::make('creatives')->hiddenLabel()->schema([
                    ImageEntry::make('path')->hiddenLabel()->disk('public')->imageHeight(160)->url(fn ($record) => $record->url(), true),
                    TextEntry::make('format')->hiddenLabel()->badge()->state(fn ($record) => $record->format.' · '.$record->width.'×'.$record->height),
                ])->grid(4),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [PlacementsRelationManager::class, NetworkStatsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'view' => ViewCampaign::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
