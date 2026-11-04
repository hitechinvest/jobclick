<?php

namespace App\Livewire\Auth;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\LoginService;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.guest')]
#[Title('Вход')]
class Login extends Component
{
    public string $email = '';

    public string $code = '';

    public bool $sent = false;

    public function send(LoginService $login): void
    {
        $this->validate(['email' => 'required|email:rfc|max:190'], [], ['email' => 'e-mail']);

        try {
            $login->sendCode($this->email, request()->ip());
            $this->sent = true;
            $this->resetErrorBag();
        } catch (InvalidArgumentException $e) {
            $this->addError('email', $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->addError('email', 'Не удалось отправить письмо. Попробуйте позже.');
        }
    }

    public function verify(LoginService $login)
    {
        $this->validate(['code' => 'required|digits:6'], [], ['code' => 'код']);

        try {
            $login->verify($this->email, $this->code);
        } catch (InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return null;
        }

        return redirect()->intended(route('cabinet.dashboard'));
    }

    public function back(): void
    {
        $this->sent = false;
        $this->code = '';
    }

    public function render()
    {
        return view('livewire.auth.login', [
            'providers' => collect(AuthController::PROVIDERS)->filter(fn ($_, $key) => AuthController::providerEnabled($key)),
        ]);
    }
}
