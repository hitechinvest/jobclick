<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\BillingService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Клиенты';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Аккаунт')->schema([
                Grid::make(2)->schema([
                    TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true),
                    TextInput::make('name')->label('Имя'),
                    Toggle::make('is_blocked')->label('Заблокирован'),
                    Toggle::make('is_admin')->label('Администратор'),
                    TextInput::make('password')->label('Пароль для входа в админку')->password()->revealable()
                        ->dehydrated(fn ($state) => filled($state))->helperText('Оставьте пустым, чтобы не менять'),
                ]),
            ]),
            Section::make('Компания и баланс')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('company.name_full')->label('Компания')->placeholder('не указана'),
                    TextEntry::make('company.inn')->label('ИНН')->placeholder('—'),
                    TextEntry::make('balance')->label('Баланс / резерв')->state(fn (?User $record) => $record ? Money::format($record->balance, true).' / '.Money::format($record->reserved, true) : '—'),
                ]),
            ])->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('company')->withCount('campaigns'))
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('email')->label('E-mail')->searchable()->description(fn (User $u) => $u->company?->displayName()),
                TextColumn::make('company.inn')->label('ИНН')->searchable()->placeholder('—'),
                TextColumn::make('balance')->label('Баланс')->formatStateUsing(fn ($state) => Money::format($state, true))->sortable(),
                TextColumn::make('reserved')->label('В резерве')->formatStateUsing(fn ($state) => Money::format($state, true)),
                TextColumn::make('campaigns_count')->label('Кампаний'),
                IconColumn::make('is_admin')->label('Админ')->boolean(),
                IconColumn::make('is_blocked')->label('Блок')->boolean(),
                TextColumn::make('last_login_at')->label('Вход')->since()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('adjust')->label('Баланс ±')->icon(Heroicon::OutlinedScale)
                    ->schema([
                        TextInput::make('amount')->label('Сумма, ₽ (минус — списание)')->numeric()->required(),
                        TextInput::make('comment')->label('Причина')->required(),
                    ])
                    ->action(function (User $u, array $data) {
                        try {
                            app(BillingService::class)->adjust($u, (float) $data['amount'], $data['comment'], auth()->id());
                            AuditLog::create(['user_id' => auth()->id(), 'action' => 'balance_adjust', 'subject_type' => User::class, 'subject_id' => $u->id, 'changes' => $data]);
                            Notification::make()->title('Баланс изменён')->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'edit' => EditUser::route('/{record}/edit')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
