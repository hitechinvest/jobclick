<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'jobclick:admin {email} {password}';

    protected $description = 'Создать или обновить администратора (вход в /admin по паролю)';

    public function handle(): int
    {
        $user = User::firstOrNew(['email' => mb_strtolower($this->argument('email'))]);
        $user->password = $this->argument('password');
        $user->is_admin = true;
        $user->email_verified_at ??= now();
        $user->save();
        $this->info('Администратор '.$user->email.' готов. Вход: '.url('/admin'));

        return self::SUCCESS;
    }
}
