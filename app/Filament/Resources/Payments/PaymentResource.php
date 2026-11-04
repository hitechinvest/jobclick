<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static ?string $navigationLabel = 'Онлайн-платежи';

    protected static ?string $modelLabel = 'онлайн-платёж';

    protected static ?string $pluralModelLabel = 'Онлайн-платежи';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i'),
                TextColumn::make('user.email')->label('Пользователь')->searchable(),
                TextColumn::make('amount')->label('Сумма')->formatStateUsing(fn ($state) => Money::format($state, true)),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn ($state) => Payment::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'succeeded' => 'success', 'canceled' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('provider_id')->label('ID ЮKassa')->copyable()->fontFamily('mono')->toggleable(),
                TextColumn::make('credited_at')->label('Зачислен')->dateTime('d.m.Y H:i')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->label('Статус')->options(Payment::STATUSES)])
            ->recordActions([
                Action::make('sync')->label('Сверить')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Payment $p) => $p->status === 'pending')
                    ->action(function (Payment $p) {
                        try {
                            $p = app(PaymentService::class)->sync($p);
                            Notification::make()->title('Статус: '.(Payment::STATUSES[$p->status] ?? $p->status))->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
