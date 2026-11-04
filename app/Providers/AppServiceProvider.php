<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureFromSettings();

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('yandex', \SocialiteProviders\Yandex\Provider::class);
            $event->extendSocialite('mailru', \SocialiteProviders\Mailru\Provider::class);
        });
    }

    /** Почта (свой SMTP) и OAuth-ключи задаются в админке, а не в .env. */
    protected function configureFromSettings(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        if (Settings::has('mail.host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.scheme' => Settings::get('mail.scheme') ?: null,
                'mail.mailers.smtp.host' => Settings::get('mail.host'),
                'mail.mailers.smtp.port' => Settings::int('mail.port'),
                'mail.mailers.smtp.username' => Settings::get('mail.username'),
                'mail.mailers.smtp.password' => Settings::get('mail.password'),
            ]);
        }
        if (Settings::has('mail.from_address')) {
            config(['mail.from.address' => Settings::get('mail.from_address'), 'mail.from.name' => Settings::get('mail.from_name')]);
        }

        config([
            'services.yandex' => [
                'client_id' => Settings::get('oauth.yandex_client_id'),
                'client_secret' => Settings::get('oauth.yandex_client_secret'),
                'redirect' => url('/auth/yandex/callback'),
            ],
            'services.mailru' => [
                'client_id' => Settings::get('oauth.mailru_client_id'),
                'client_secret' => Settings::get('oauth.mailru_client_secret'),
                'redirect' => url('/auth/mailru/callback'),
            ],
        ]);
    }
}
