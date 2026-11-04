<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginService;
use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_by_email_code_creates_user(): void
    {
        Mail::fake();
        app(LoginService::class)->sendCode('HR@Company.ru', '1.1.1.1');

        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('hr@company.ru');
        });

        $user = app(LoginService::class)->verify('hr@company.ru', $code);
        $this->assertSame('hr@company.ru', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_code_and_rate_limit(): void
    {
        Mail::fake();
        $login = app(LoginService::class);
        $login->sendCode('a@b.ru', '1.1.1.1');

        try {
            $login->verify('a@b.ru', '000000');
            $this->fail('wrong code accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Неверный код', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $login->sendCode('a@b.ru', '1.1.1.1'); // второй раз за минуту
    }

    public function test_magic_link_logs_in(): void
    {
        Mail::fake();
        app(LoginService::class)->sendCode('link@b.ru', '1.1.1.1');
        $link = null;
        Mail::assertSent(LoginCodeMail::class, function ($mail) use (&$link) {
            $link = $mail->link;

            return true;
        });

        $this->get($link)->assertRedirect(route('cabinet.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_cabinet_requires_login_and_admin_requires_flag(): void
    {
        $this->get('/cabinet')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/cabinet')->assertOk();
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
