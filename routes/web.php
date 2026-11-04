<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CreativesZipController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\YooKassaWebhookController;
use App\Livewire\Auth\Login;
use App\Livewire\Cabinet;
use App\Livewire\Wizard\CampaignWizard;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/offer', [PageController::class, 'offer'])->name('offer');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

// Трекинг-ссылка из объявлений: без сессий и CSRF
Route::get('/go/{slug}', TrackingController::class)->name('track')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class,
        VerifyCsrfToken::class]);

Route::post('/webhooks/yookassa', YooKassaWebhookController::class)->name('webhooks.yookassa')
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/login/link', [AuthController::class, 'link'])->name('login.link')->middleware('signed');
    Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/auth/{provider}/callback', [AuthController::class, 'callback'])->name('oauth.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->prefix('cabinet')->name('cabinet.')->group(function () {
    Route::get('/', Cabinet\Dashboard::class)->name('dashboard');
    Route::get('/campaigns', Cabinet\Campaigns::class)->name('campaigns');
    Route::get('/campaigns/new', CampaignWizard::class)->name('campaigns.create');
    Route::get('/campaigns/{campaign}/edit', CampaignWizard::class)->name('campaigns.edit');
    Route::get('/campaigns/{campaign}', Cabinet\CampaignShow::class)->name('campaigns.show');
    Route::get('/finance', Cabinet\Finance::class)->name('finance');
    Route::get('/library', Cabinet\Library::class)->name('library');
    Route::get('/profile', Cabinet\Profile::class)->name('profile');
    Route::get('/invoices/{invoice}/pdf', InvoiceController::class)->name('invoices.pdf');
});

Route::get('/payments/{payment}/return', PaymentReturnController::class)->name('payments.return')->middleware('auth');

Route::get('/admin-tools/campaigns/{campaign}/creatives.zip', CreativesZipController::class)
    ->name('admin.campaigns.creatives')->middleware('auth');
