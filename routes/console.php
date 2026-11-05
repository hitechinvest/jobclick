<?php

use Illuminate\Support\Facades\Schedule;

// cron: * * * * * cd /path && php artisan schedule:run

// Очередь писем без supervisor: планировщик раз в минуту разбирает очередь (драйвер database)
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')->everyMinute()->withoutOverlapping()->runInBackground();

Schedule::command('jobclick:sync-payments')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('jobclick:check-vacancies')->dailyAt('06:10')->withoutOverlapping();
Schedule::command('jobclick:send-reports')->dailyAt('10:20');
Schedule::command('queue:prune-failed --hours=720')->daily();
