# JobClick

Self-service сервис рекламы вакансий в Яндекс Директе и VK Рекламе (аналог Clickme от hh.ru).
Пользователь вставляет ссылку на вакансию, ИИ готовит объявление и картинку, сервис показывает прогноз кликов по Вордстату.
После оплаты кампания уходит на модерацию, админ одобряет её и размещает в сетях вручную.

- План разработки: [docs/DEVELOPMENT_PLAN.md](docs/DEVELOPMENT_PLAN.md)
- Стек: PHP 8.3, Laravel 12, MySQL 8, Filament 5 (админка), Livewire 4 + Tailwind 4 (сайт и кабинет). Без Redis, S3 и Docker.

## Что внутри

| Раздел | Где |
|---|---|
| Главная с калькулятором Вордстата | `resources/views/pages/home.blade.php`, `app/Livewire/Landing/Calculator.php` |
| Вход по коду на e-mail, Яндекс ID, Mail.ru | `app/Livewire/Auth/Login.php`, `app/Http/Controllers/Auth` |
| Мастер кампании, 7 шагов | `app/Livewire/Wizard/CampaignWizard.php`, `resources/views/livewire/wizard` |
| Кабинет: кампании, финансы, картинки, профиль | `app/Livewire/Cabinet` |
| Админка: модерация, размещение, счета, настройки | `app/Filament`, вход на `/admin` |
| Прогноз, биллинг, счета с QR, трекинг кликов | `app/Services` |
| Интеграции и парсеры вакансий | `app/Services/Integrations`, `app/Services/Parsers` |

Все ключи (Вордстат, DeepSeek, Qwen Image обычная и Pro, DaData, ЮKassa, SMTP, OAuth), реквизиты для счетов, система налогообложения и тарифы задаются в админке: **Настройки**. Секреты хранятся в базе в зашифрованном виде.

## Локальный запуск

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
```

Для разработки можно поставить в `.env` `DB_CONNECTION=sqlite` и создать файл `database/database.sqlite`.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan jobclick:admin admin@example.ru 'надёжный-пароль'
php artisan serve
```

Письма в разработке пишутся в `storage/logs/laravel.log` (`MAIL_MAILER=log`), там же код входа.

## Тесты

```bash
php artisan test
```

```bash
./vendor/bin/pint --test
```

## Продакшен (один VPS)

1. Ubuntu, nginx, PHP 8.3-FPM с расширениями `gd, intl, mbstring, zip, pdo_mysql`, MySQL 8, Node 22 для сборки.
2. Конфиги: `deploy/nginx.conf`, `deploy/supervisor.conf` (воркер очереди писем), `deploy/crontab` (планировщик и бэкап).
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://ваш-домен`, доступы к MySQL.
4. Первый запуск: `php artisan migrate --seed`, `php artisan jobclick:admin …`, затем заполнить **Настройки** в админке.
5. Обновление: `./deploy/deploy.sh`.
6. В кабинете ЮKassa указать URL уведомлений `https://ваш-домен/webhooks/yookassa` (события `payment.succeeded`, `payment.canceled`).

Планировщик (`routes/console.php`): сверка платежей ЮKassa каждые 15 минут, проверка закрытых вакансий раз в сутки, письмо с результатами через 3 дня после запуска и напоминания по счетам.
