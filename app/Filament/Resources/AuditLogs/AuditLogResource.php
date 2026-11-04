<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?string $navigationLabel = 'Журнал действий';

    protected static ?string $modelLabel = 'запись';

    protected static ?string $pluralModelLabel = 'Журнал действий';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i'),
                TextColumn::make('user.email')->label('Кто'),
                TextColumn::make('action')->label('Действие')->badge(),
                TextColumn::make('subject_type')->label('Объект')->formatStateUsing(fn ($state, AuditLog $l) => class_basename($state).($l->subject_id ? ' #'.$l->subject_id : '')),
                TextColumn::make('changes')->label('Изменения')->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (string) $state)->limit(80)->wrap(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
