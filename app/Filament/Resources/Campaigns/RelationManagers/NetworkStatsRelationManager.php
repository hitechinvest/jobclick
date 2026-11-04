<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Enums\Platform;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Статистика из кабинетов сетей (ввод вручную) — для сверки с нашими кликами и расчёта маржи. */
class NetworkStatsRelationManager extends RelationManager
{
    protected static string $relationship = 'networkStats';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Статистика сетей';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                DatePicker::make('date')->label('Дата')->required()->default(now()->subDay()),
                Select::make('platform')->label('Площадка')->options(Platform::options())->required(),
                TextInput::make('impressions')->label('Показы')->numeric()->default(0),
                TextInput::make('clicks')->label('Клики в сети')->numeric()->default(0),
                TextInput::make('spend')->label('Расход в сети, ₽')->numeric()->default(0),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Дата')->date('d.m.Y'),
                TextColumn::make('platform')->label('Площадка')->badge(),
                TextColumn::make('impressions')->label('Показы'),
                TextColumn::make('clicks')->label('Клики'),
                TextColumn::make('spend')->label('Расход')->formatStateUsing(fn ($state) => Money::format($state, true)),
            ])
            ->headerActions([CreateAction::make()->label('Добавить день')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
