<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    public const PROVIDERS = ['yandex' => 'Яндекс ID', 'mailru' => 'Mail.ru'];

    public static function providerEnabled(string $provider): bool
    {
        return Settings::has('oauth.'.$provider.'_client_id') && Settings::has('oauth.'.$provider.'_client_secret');
    }

    public function link(Request $request, LoginService $login)
    {
        try {
            $login->verify((string) $request->query('email'), (string) $request->query('code'));
        } catch (InvalidArgumentException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        return redirect()->intended(route('cabinet.dashboard'));
    }

    public function redirect(string $provider)
    {
        abort_unless(isset(self::PROVIDERS[$provider]) && self::providerEnabled($provider), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, LoginService $login)
    {
        abort_unless(isset(self::PROVIDERS[$provider]) && self::providerEnabled($provider), 404);

        try {
            $remote = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->with('error', 'Не удалось войти через '.self::PROVIDERS[$provider].'. Попробуйте ещё раз.');
        }

        $column = $provider.'_id';
        $email = $remote->getEmail() ? mb_strtolower($remote->getEmail()) : null;
        $user = User::where($column, $remote->getId())->first()
            ?? ($email ? User::where('email', $email)->first() : null);

        if (! $user) {
            if (! $email) {
                return redirect()->route('login')->with('error', 'Сервис не передал e-mail. Войдите по коду на почту.');
            }
            $user = User::create(['email' => $email, 'name' => $remote->getName()]);
            $user->email_verified_at = now();
        }

        $user->{$column} = $remote->getId();
        $user->name ??= $remote->getName();

        try {
            $login->login($user);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        return redirect()->intended(route('cabinet.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
