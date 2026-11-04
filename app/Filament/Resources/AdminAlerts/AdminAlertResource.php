<?php

namespace App\Filament\Resources\AdminAlerts;

use App\Filament\Resources\AdminAlerts\Pages\ListAdminAlerts;
use App\Models\AdminAlert;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

class AdminAlertResource extends Resource
{
    protected static ?string $model = AdminAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Кампании';

    protected static ?string $navigationLabel = 'Требуют действия';

    protected static ?string $modelLabel = 'алерт';

    protected static ?string $pluralModelLabel = 'Требуют действия';

    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        $n = AdminAlert::whereNull('resolved_at')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Когда')->since(),
                TextColumn::make('type')->label('Тип')->badge()->formatStateUsing(fn ($state) => AdminAlert::TYPES[$state] ?? $state)
                    ->color(fn ($state) => in_array($state, ['budget_100', 'cancelled', 'vacancy_closed', 'paused', 'error'], true) ? 'danger' : (in_array($state, ['moderation', 'placement'], true) ? 'warning' : 'info')),
                TextColumn::make('message')->label('Что сделать')->wrap(),
                TextColumn::make('resolved_at')->label('Решено')->since()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('open')->label('Только открытые')->default(true)
                    ->queries(true: fn ($query) => $query->whereNull('resolved_at'), false: fn ($query) => $query->whereNotNull('resolved_at'), blank: fn ($query) => $query),
            ])
            ->recordUrl(fn (AdminAlert $a) => $a->url)
            ->recordActions([
                Action::make('open')->label('Открыть')->url(fn (AdminAlert $a) => $a->url)->visible(fn (AdminAlert $a) => (bool) $a->url),
                Action::make('resolve')->label('Сделано')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (AdminAlert $a) => ! $a->resolved_at)
                    ->action(fn (AdminAlert $a) => $a->update(['resolved_at' => now()])),
            ])
            ->toolbarActions([
                BulkAction::make('resolveMany')->label('Отметить сделанными')
                    ->action(fn (Collection $records) => $records->each->update(['resolved_at' => now()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAdminAlerts::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
