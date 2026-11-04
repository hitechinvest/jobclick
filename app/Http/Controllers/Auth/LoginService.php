<?php

namespace App\Http\Controllers\Auth;

use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/** Вход по одноразовому коду на e-mail. Первый вход = регистрация. */
class LoginService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function sendCode(string $email, ?string $ip = null): void
    {
        $email = mb_strtolower(trim($email));

        foreach (['login-email:'.$email => [1, 60], 'login-email-hour:'.$email => [5, 3600], 'login-ip:'.$ip => [20, 3600]] as $key => [$max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new InvalidArgumentException('Слишком часто. Попробуйте через '.RateLimiter::availableIn($key).' сек.');
            }
        }
        foreach (['login-email:'.$email => 60, 'login-email-hour:'.$email => 3600, 'login-ip:'.$ip => 3600] as $key => $decay) {
            RateLimiter::hit($key, $decay);
        }

        $code = (string) random_int(100000, 999999);
        LoginCode::where('email', $email)->whereNull('used_at')->update(['used_at' => now()]);
        LoginCode::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip' => $ip,
        ]);

        $link = URL::temporarySignedRoute('login.link', now()->addMinutes(self::TTL_MINUTES), ['email' => $email, 'code' => $code]);
        Mail::to($email)->send(new LoginCodeMail($code, $link));
    }

    public function verify(string $email, string $code): User
    {
        $email = mb_strtolower(trim($email));
        $record = LoginCode::where('email', $email)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();

        if (! $record) {
            throw new InvalidArgumentException('Код устарел — запросите новый');
        }
        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw new InvalidArgumentException('Слишком много попыток — запросите новый код');
        }
        if (! Hash::check(trim($code), $record->code_hash)) {
            $record->increment('attempts');
            throw new InvalidArgumentException('Неверный код');
        }

        $record->update(['used_at' => now()]);
        $user = User::firstOrCreate(['email' => $email]);
        $user->email_verified_at ??= now();

        return $this->login($user);
    }

    public function login(User $user): User
    {
        if ($user->is_blocked) {
            throw new InvalidArgumentException('Аккаунт заблокирован. Напишите в поддержку.');
        }
        $user->last_login_at = now();
        $user->save();
        Auth::login($user, true);
        session()->regenerate();

        return $user;
    }
}
