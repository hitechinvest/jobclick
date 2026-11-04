<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Region;
use App\Services\Integrations\DaDataClient;
use App\Services\Integrations\DeepSeekClient;
use App\Services\Integrations\WordstatClient;
use App\Support\Settings as SettingsStore;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/** Все настройки сервиса: ключи интеграций, ЮKassa, реквизиты для счетов, тарифы, почта и вход. */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?string $navigationLabel = 'Настройки';

    protected static ?string $title = 'Настройки сервиса';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $values = [];
        foreach (SettingsStore::DEFINITIONS as $key => [$default, $secret]) {
            $values[$this->field($key)] = $secret ? null : SettingsStore::get($key);
        }
        $values[$this->field('tariff.premium_region_ids')] = SettingsStore::premiumRegionIds();
        $values[$this->field('yookassa.receipts')] = SettingsStore::bool('yookassa.receipts');
        $this->form->fill($values);
    }

    /** Ключ настройки «wordstat.api_key» → имя поля формы «wordstat__api_key». */
    protected function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    protected function secret(string $key, string $label): TextInput
    {
        return TextInput::make($this->field($key))->label($label)->password()->revealable()
            ->placeholder(SettingsStore::has($key) ? '•••••••• сохранено — оставьте пустым, чтобы не менять' : 'не задан')
            ->autocomplete('new-password');
    }

    protected function text(string $key, string $label): TextInput
    {
        return TextInput::make($this->field($key))->label($label);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make('settings')->persistTabInQueryString()->tabs([
                Tab::make('Интеграции')->icon(Heroicon::OutlinedPuzzlePiece)->schema([
                    Section::make('Яндекс Вордстат')->description('Статистика запросов для калькулятора и прогноза. Yandex Cloud → Search API, роль search-api.webSearch.user.')
                        ->schema([
                            Grid::make(2)->schema([
                                $this->secret('wordstat.api_key', 'API-ключ'),
                                $this->text('wordstat.folder_id', 'ID каталога (folderId), если нужен'),
                            ]),
                            $this->text('wordstat.base_url', 'URL API'),
                        ])->headerActions([$this->testAction('testWordstat', 'Проверить')]),
                    Section::make('DeepSeek')->description('Генерация заголовка и текстов объявления.')->schema([
                        Grid::make(3)->schema([
                            $this->secret('deepseek.api_key', 'API-ключ'),
                            $this->text('deepseek.base_url', 'URL API'),
                            $this->text('deepseek.model', 'Модель'),
                        ]),
                    ])->headerActions([$this->testAction('testDeepSeek', 'Проверить')]),
                    Section::make('Qwen Image 3 — обычная')->description('Полный URL эндпоинта: OpenAI-совместимый …/images/generations или DashScope …/multimodal-generation/generation.')->schema([
                        Grid::make(3)->schema([
                            $this->secret('qwen.api_key', 'API-ключ'),
                            $this->text('qwen.url', 'URL')->columnSpan(1),
                            $this->text('qwen.model', 'Модель'),
                        ]),
                    ]),
                    Section::make('Qwen Image 3 — Pro')->schema([
                        Grid::make(3)->schema([
                            $this->secret('qwen_pro.api_key', 'API-ключ'),
                            $this->text('qwen_pro.url', 'URL'),
                            $this->text('qwen_pro.model', 'Модель'),
                        ]),
                    ]),
                    Section::make('DaData')->description('Подсказки по ИНН на последнем шаге создания кампании.')->schema([
                        Grid::make(2)->schema([
                            $this->secret('dadata.api_key', 'API-ключ'),
                            $this->secret('dadata.secret_key', 'Секретный ключ (необязательно)'),
                        ]),
                    ])->headerActions([$this->testAction('testDaData', 'Проверить')]),
                    Section::make('Сайты вакансий')->description('Необязательно: без ключей данные берутся со страницы вакансии.')->collapsed()->schema([
                        Grid::make(3)->schema([
                            $this->secret('hh.token', 'hh.ru: токен приложения'),
                            $this->text('hh.user_agent', 'hh.ru: HH-User-Agent'),
                            $this->secret('superjob.app_key', 'SuperJob: X-Api-App-Id'),
                        ]),
                    ]),
                ]),

                Tab::make('ЮKassa')->icon(Heroicon::OutlinedCreditCard)->schema([
                    Section::make('Приём онлайн-платежей (юрлицо, чеки 54-ФЗ)')->schema([
                        Grid::make(2)->schema([
                            $this->text('yookassa.shop_id', 'shopId'),
                            $this->secret('yookassa.secret_key', 'Секретный ключ'),
                        ]),
                        Toggle::make($this->field('yookassa.receipts'))->label('Отправлять чеки 54-ФЗ'),
                        Text::make('URL для HTTP-уведомлений (укажите в личном кабинете ЮKassa, события payment.succeeded и payment.canceled): '.route('webhooks.yookassa')),
                        Text::make('Система налогообложения и ставка НДС для чека берутся из вкладки «Реквизиты».'),
                    ]),
                ]),

                Tab::make('Реквизиты')->icon(Heroicon::OutlinedBuildingOffice2)->schema([
                    Section::make('Получатель платежей — печатается в счёте и QR-коде')->schema([
                        Grid::make(3)->schema([
                            $this->text('requisites.name', 'Наименование')->columnSpan(2),
                            $this->text('requisites.inn', 'ИНН'),
                            $this->text('requisites.kpp', 'КПП'),
                            $this->text('requisites.ogrn', 'ОГРН / ОГРНИП'),
                            $this->text('requisites.director', 'Руководитель (Фамилия И.О.)'),
                            $this->text('requisites.address', 'Юридический адрес')->columnSpan(2),
                            $this->text('requisites.accountant', 'Бухгалтер'),
                            $this->text('requisites.bank_name', 'Банк'),
                            $this->text('requisites.bik', 'БИК'),
                            $this->text('requisites.account', 'Расчётный счёт'),
                            $this->text('requisites.corr_account', 'Корр. счёт'),
                            $this->text('requisites.phone', 'Телефон'),
                            $this->text('requisites.email', 'E-mail'),
                        ]),
                    ]),
                    Section::make('Налоги и счета')->schema([
                        Grid::make(4)->schema([
                            Select::make($this->field('requisites.tax_system_code'))->label('Система налогообложения')->options(SettingsStore::TAX_SYSTEMS)->required(),
                            Select::make($this->field('requisites.vat_code'))->label('Ставка НДС в чеке')->options(SettingsStore::VAT_CODES)->required(),
                            $this->text('requisites.invoice_prefix', 'Префикс номера счёта'),
                            $this->text('requisites.invoice_due_days', 'Срок оплаты, раб. дней')->numeric(),
                        ]),
                        $this->text('requisites.offer_url', 'Ссылка на полный текст оферты (необязательно)')->url(),
                        Grid::make(2)->schema([
                            FileUpload::make($this->field('requisites.logo'))->label('Логотип для счёта')->image()->disk('public')->directory('requisites'),
                            FileUpload::make($this->field('requisites.stamp'))->label('Подпись и печать (PNG с прозрачностью)')->image()->disk('public')->directory('requisites'),
                        ]),
                    ]),
                ]),

                Tab::make('Тарифы')->icon(Heroicon::OutlinedCurrencyDollar)->schema([
                    Section::make('Цена клика и бюджеты')->schema([
                        Grid::make(3)->schema([
                            $this->text('tariff.cpc_premium', 'Клик в «дорогих» городах, ₽')->numeric()->required(),
                            $this->text('tariff.cpc_default', 'Клик в остальных городах, ₽')->numeric()->required(),
                            Select::make($this->field('tariff.premium_region_ids'))->label('«Дорогие» города')->multiple()->searchable()
                                ->options(fn () => Region::whereIn('id', SettingsStore::premiumRegionIds())->pluck('name', 'id'))
                                ->getSearchResultsUsing(fn (string $search) => Region::search($search, 20)->mapWithKeys(fn ($r) => [$r->id => $r->label()]))
                                ->getOptionLabelsUsing(fn (array $values) => Region::whereIn('id', $values)->pluck('name', 'id')),
                            $this->text('tariff.min_total_budget', 'Минимальный бюджет, ₽')->numeric()->required(),
                            $this->text('tariff.min_daily_budget', 'Минимальный дневной лимит, ₽')->numeric()->required(),
                            $this->text('tariff.forecast_tolerance', 'Вилка прогноза, %')->numeric(),
                        ]),
                    ]),
                    Section::make('Прогноз по Вордстату и картинки')->schema([
                        Grid::make(4)->schema([
                            $this->text('tariff.wordstat_divisor', 'Делитель: клики = запросы / N')->numeric()->required(),
                            $this->text('tariff.image_min_width', 'Мин. ширина картинки, px')->numeric(),
                            $this->text('tariff.image_min_height', 'Мин. высота картинки, px')->numeric(),
                            $this->text('tariff.generations_per_day', 'Генераций картинок в день на пользователя')->numeric(),
                        ]),
                    ]),
                ]),

                Tab::make('Почта и вход')->icon(Heroicon::OutlinedEnvelope)->schema([
                    Section::make('SMTP — свой почтовый сервер')->schema([
                        Grid::make(3)->schema([
                            $this->text('mail.host', 'Хост'),
                            $this->text('mail.port', 'Порт')->numeric(),
                            Select::make($this->field('mail.scheme'))->label('Шифрование')->options(['smtps' => 'SSL (465)', 'smtp' => 'STARTTLS / без шифрования (587, 25)']),
                            $this->text('mail.username', 'Логин'),
                            $this->secret('mail.password', 'Пароль'),
                            $this->text('mail.from_address', 'Адрес отправителя'),
                            $this->text('mail.from_name', 'Имя отправителя'),
                        ]),
                        Textarea::make($this->field('mail.admin_emails'))->label('E-mail администраторов для алертов (через запятую)')->rows(2),
                    ])->headerActions([$this->testAction('testMail', 'Отправить тестовое письмо')]),
                    Section::make('Вход через Яндекс ID и Mail.ru')->schema([
                        Text::make('Redirect URI для Яндекса: '.url('/auth/yandex/callback').' · для Mail.ru: '.url('/auth/mailru/callback')),
                        Grid::make(2)->schema([
                            $this->text('oauth.yandex_client_id', 'Яндекс ClientID'),
                            $this->secret('oauth.yandex_client_secret', 'Яндекс Client secret'),
                            $this->text('oauth.mailru_client_id', 'Mail.ru ID приложения'),
                            $this->secret('oauth.mailru_client_secret', 'Mail.ru Секрет'),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Сохранить настройки')->submit('save')->keyBindings(['mod+s'])]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = [];

        foreach (SettingsStore::DEFINITIONS as $key => [$default, $secret]) {
            $field = $this->field($key);
            if (! array_key_exists($field, $state)) {
                continue;
            }
            $value = $state[$field];
            if ($secret && blank($value)) {
                continue; // пустой секрет = не менять
            }
            if ($key === 'tariff.premium_region_ids') {
                $value = implode(',', array_map('intval', (array) $value));
            }
            if (is_array($value)) {
                $value = reset($value) ?: null; // FileUpload
            }
            if ((string) SettingsStore::get($key) !== (string) (is_bool($value) ? (int) $value : $value)) {
                $changed[$key] = $secret ? '***' : $value;
            }
            SettingsStore::set($key, $value);
        }

        if ($changed) {
            AuditLog::create(['user_id' => auth()->id(), 'action' => 'settings', 'subject_type' => 'settings', 'changes' => $changed]);
        }

        $this->mount();
        Notification::make()->title('Настройки сохранены')->success()->send();
    }

    protected function testAction(string $name, string $label): Action
    {
        return Action::make($name)->label($label)->link()->action(fn () => $this->{$name}());
    }

    protected function report(bool $ok, string $message): void
    {
        Notification::make()->title($ok ? 'Работает' : 'Ошибка')->body($message)->{$ok ? 'success' : 'danger'}()->send();
    }

    public function testWordstat(): void
    {
        try {
            $data = app(WordstatClient::class)->topRequests('курьер вакансии', [Region::MOSCOW], 1);
            $this->report(true, '«курьер вакансии» в Москве: '.number_format($data['total'], 0, ',', ' ').' запросов в месяц');
        } catch (Throwable $e) {
            $this->report(false, $e->getMessage());
        }
    }

    public function testDeepSeek(): void
    {
        try {
            $ad = app(DeepSeekClient::class)->generateAd(['title' => 'Курьер', 'salary' => 'от 80 000 ₽', 'city' => 'Москва']);
            $this->report(true, $ad['title'].' — '.$ad['text']);
        } catch (Throwable $e) {
            $this->report(false, $e->getMessage());
        }
    }

    public function testDaData(): void
    {
        $found = app(DaDataClient::class)->suggestParty('7707083893');
        $this->report((bool) $found, $found ? $found[0]['name_short'] : 'Нет ответа — проверьте ключ');
    }

    public function testMail(): void
    {
        $to = auth()->user()->email;
        try {
            SettingsStore::flush();
            SettingsStore::applyMailConfig();
            Mail::raw('Тестовое письмо JobClick: почта настроена правильно.', fn ($m) => $m->to($to)->subject('JobClick: тест почты'));
            $this->report(true, 'Письмо отправлено на '.$to);
        } catch (Throwable $e) {
            $this->report(false, $e->getMessage());
        }
    }
}
