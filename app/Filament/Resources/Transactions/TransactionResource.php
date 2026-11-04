<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Transaction;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static ?string $navigationLabel = 'Операции по балансам';

    protected static ?string $modelLabel = 'операция';

    protected static ?string $pluralModelLabel = 'Операции по балансам';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i:s'),
                TextColumn::make('user.email')->label('Пользователь')->searchable(),
                TextColumn::make('type')->label('Тип')->badge(),
                TextColumn::make('amount')->label('Сумма')->formatStateUsing(fn ($state) => Money::format($state, true))
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success'),
                TextColumn::make('balance_after')->label('Баланс после')->formatStateUsing(fn ($state) => Money::format($state, true)),
                TextColumn::make('reserved_after')->label('Резерв после')->formatStateUsing(fn ($state) => Money::format($state, true))->toggleable(),
                TextColumn::make('campaign_id')->label('Кампания')->formatStateUsing(fn ($state) => '#'.$state)->placeholder('—'),
                TextColumn::make('comment')->label('Комментарий')->limit(50)->toggleable(),
            ])
            ->filters([SelectFilter::make('type')->label('Тип')->options(TransactionType::options())]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTransactions::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
