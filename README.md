# JobClick

Self-service сервис рекламы вакансий в Яндекс Директе и VK Рекламе (аналог Clickme от hh.ru).
Пользователь вставляет ссылку на вакансию, ИИ готовит объявление и картинку, сервис показывает прогноз кликов по Вордстату.
После оплаты кампания уходит на модерацию, админ одобряет её и размещает в сетях вручную.

- План разработки: [docs/DEVELOPMENT_PLAN.md](docs/DEVELOPMENT_PLAN.md)
- Стек: PHP 8.2+, Laravel 12, MariaDB 10.6+ (подходит и MySQL 8), Filament 5 (админка), Livewire 4 + Tailwind 4 (сайт и кабинет). Без Redis, S3 и Docker.
- Фронтенд собран и лежит в `public/build`: на сервере Node.js не нужен. После правок вёрстки выполните `npm run build` и закоммитьте результат.

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

## Запуск на платформе sitegen

Сайт создаётся в sitegen как обычный проект (например, `/var/www/sites/575`), база MariaDB — тоже в sitegen.
Репозиторий приватный, поэтому скрипт `deploy/sitegen.sh` для первого запуска положите на сервер вручную: откройте его на GitHub, нажмите Raw, скопируйте и вставьте в `nano /tmp/sitegen.sh` (или `scp deploy/sitegen.sh сервер:/tmp/`). Затем:

```bash
sudo bash /tmp/sitegen.sh install /var/www/sites/575
```

Скрипт:
- проверит PHP 8.2+ и расширения, при необходимости скачает Composer;
- при первом запуске создаст deploy-ключ и покажет его — добавьте его в GitHub (Settings → Deploy keys) и запустите команду ещё раз;
- уберёт заглушку sitegen в `.stub-backup`, загрузит код, спросит адрес сайта и доступы к базе, выполнит миграции и справочники, создаст администратора;
- добавит планировщик в cron владельца папки (письма, сверка оплат, проверка вакансий) — supervisor не нужен;
- проверит, что веб-сервер отдаёт папку `public/`, и подскажет, что поменять, если нет.

Все команды выполняются от владельца папки сайта. Обновление после изменений в коде:

```bash
sudo bash /var/www/sites/575/deploy/sitegen.sh update /var/www/sites/575
```

## Продакшен (один VPS)

1. Ubuntu, nginx, PHP 8.3-FPM с расширениями `gd, intl, mbstring, xml, curl, zip, mysql`, MariaDB 10.6+.
2. Конфиги: `deploy/nginx.conf`, `deploy/crontab` (планировщик и бэкап). Воркер очереди запускается планировщиком, `deploy/supervisor.conf` — по желанию.
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://ваш-домен`, `DB_CONNECTION=mariadb` и доступы к базе.
4. Первый запуск: `php artisan migrate --seed`, `php artisan jobclick:admin …`, затем заполнить **Настройки** в админке.
5. Обновление: `./deploy/deploy.sh`.
6. В кабинете ЮKassa указать URL уведомлений `https://ваш-домен/webhooks/yookassa` (события `payment.succeeded`, `payment.canceled`).

Планировщик (`routes/console.php`): сверка платежей ЮKassa каждые 15 минут, проверка закрытых вакансий раз в сутки, письмо с результатами через 3 дня после запуска и напоминания по счетам.
