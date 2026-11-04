<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static ?string $navigationLabel = 'Счета';

    protected static ?string $modelLabel = 'счёт';

    protected static ?string $pluralModelLabel = 'Счета';

    public static function getNavigationBadge(): ?string
    {
        $n = Invoice::where('status', 'issued')->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Номер')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Дата')->date('d.m.Y'),
                TextColumn::make('payer.name')->label('Плательщик')->description(fn (Invoice $i) => 'ИНН '.($i->payer['inn'] ?? ''))->searchable(query: fn ($query, string $search) => $query->where('payer', 'like', '%'.$search.'%')),
                TextColumn::make('amount')->label('Сумма')->formatStateUsing(fn ($state) => Money::format($state, true))->sortable(),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn ($state) => Invoice::STATUSES[$state])
                    ->color(fn ($state) => ['issued' => 'warning', 'paid' => 'success', 'cancelled' => 'gray'][$state]),
                TextColumn::make('campaign_id')->label('Кампания')->formatStateUsing(fn ($state) => '#'.$state)->placeholder('—'),
                TextColumn::make('paid_at')->label('Оплачен')->dateTime('d.m.Y H:i')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->label('Статус')->options(Invoice::STATUSES)])
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                    ->url(fn (Invoice $i) => route('cabinet.invoices.pdf', $i), true),
                Action::make('paid')->label('Оплачен')->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (Invoice $i) => $i->status === 'issued')
                    ->modalHeading(fn (Invoice $i) => 'Подтвердить оплату счёта '.$i->number)
                    ->modalDescription(fn (Invoice $i) => Money::format($i->amount, true).' будут зачислены на баланс клиента, ожидающие кампании уйдут на модерацию.')
                    ->schema([TextInput::make('payment_doc')->label('Платёжное поручение (№ и дата)')->required()])
                    ->action(function (Invoice $i, array $data) {
                        try {
                            app(InvoiceService::class)->markPaid($i, $data['payment_doc'], auth()->id());
                            AuditLog::create(['user_id' => auth()->id(), 'action' => 'invoice_paid', 'subject_type' => Invoice::class, 'subject_id' => $i->id, 'changes' => $data]);
                            Notification::make()->title('Деньги зачислены на баланс')->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('cancel')->label('Отменить')->color('danger')->requiresConfirmation()
                    ->visible(fn (Invoice $i) => $i->status === 'issued')
                    ->action(fn (Invoice $i) => $i->update(['status' => 'cancelled'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoices::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
