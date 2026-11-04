<?php

use Illuminate\Support\Facades\Schedule;

// cron: * * * * * php /path/artisan schedule:run
Schedule::command('jobclick:sync-payments')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('jobclick:check-vacancies')->dailyAt('06:10')->withoutOverlapping();
Schedule::command('jobclick:send-reports')->dailyAt('10:20');
Schedule::command('queue:prune-failed --hours=720')->daily();
