<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Справочник регионов = дерево регионов Яндекс Вордстата (те же коды, что в гео Директа)
        Schema::create('regions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('parent_name')->nullable();
            $table->unsignedTinyInteger('depth');
            $table->unsignedInteger('sort')->default(1000);
        });

        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort')->default(100);
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('inn', 12)->index();
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();
            $table->string('type', 16)->default('LEGAL'); // LEGAL | INDIVIDUAL
            $table->string('name_full', 500);
            $table->string('name_short')->nullable();
            $table->string('legal_address', 500)->nullable();
            $table->string('okved', 16)->nullable();
            $table->string('management_name')->nullable();
            $table->string('state', 32)->nullable(); // ACTIVE | LIQUIDATING | LIQUIDATED ...
            $table->json('dadata')->nullable();
            $table->timestamps();
        });

        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('source', 16); // upload | extracted | generated
            $table->string('source_url', 2000)->nullable();
            $table->text('prompt')->nullable();
            $table->string('model')->nullable();
            $table->string('sha256', 64)->index();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->string('slug', 16)->unique();
            // Шаг 1 — вакансия
            $table->string('source_url', 2000)->nullable();
            $table->string('source_site', 32)->nullable();
            $table->string('parse_status', 16)->nullable(); // pending | done | failed
            $table->string('parse_error')->nullable();
            $table->json('vacancy')->nullable();
            // Шаг 2 — объявление
            $table->string('title', 100)->nullable();
            $table->string('text', 120)->nullable();
            $table->string('long_text', 300)->nullable();
            // Шаг 4 — аудитория
            $table->json('regions')->nullable();
            $table->string('gender', 8)->default('any');
            $table->unsignedTinyInteger('age_from')->default(18);
            $table->unsignedTinyInteger('age_to')->default(65);
            $table->boolean('age_unknown')->default(true);
            $table->string('income', 8)->default('any');
            $table->json('interests')->nullable();
            $table->string('interests_custom', 500)->nullable();
            // Шаг 5–6 — площадки и бюджет
            $table->json('platforms')->nullable();
            $table->decimal('total_budget', 12, 2)->nullable();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->unsignedInteger('forecast_clicks')->nullable();
            $table->unsignedInteger('wordstat_count')->nullable();
            // Деньги и счётчики
            $table->decimal('reserved', 12, 2)->default(0);
            $table->decimal('spent', 12, 2)->default(0);
            $table->unsignedInteger('clicks')->default(0);
            // Модерация и жизненный цикл
            $table->text('moderation_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('stop_reason', 32)->nullable();
            $table->timestamp('alert_80_at')->nullable();
            $table->timestamp('vacancy_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->string('format', 8); // 1:1 | 16:9 | 4:5
            $table->string('focus', 8)->default('center');
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
        });

        Schema::create('campaign_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 24);
            $table->string('status', 16)->default('planned'); // planned | placed | paused | stopped | rejected
            $table->string('external_id')->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->string('erid')->nullable();
            $table->decimal('daily_limit', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'platform']);
        });

        Schema::create('campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16); // topup | reserve | release | charge | adjust
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->decimal('reserved_after', 12, 2);
            $table->string('comment')->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 32)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('status', 16)->default('issued'); // issued | paid | cancelled
            $table->json('payer');
            $table->json('payee');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_doc')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 16)->default('yookassa');
            $table->string('provider_id')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->string('status', 24)->default('pending'); // pending | succeeded | canceled
            $table->string('confirmation_url', 2000)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('click_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 24)->nullable();
            $table->string('ip_hash', 64);
            $table->string('user_agent', 500)->nullable();
            $table->string('referer', 1000)->nullable();
            $table->boolean('billable')->default(false);
            $table->decimal('charged', 8, 2)->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['campaign_id', 'ip_hash', 'created_at']);
        });

        Schema::create('network_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 24);
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('spend', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'platform', 'date']);
        });

        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('message', 500);
            $table->string('url', 1000)->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'admin_alerts', 'network_stats', 'click_events', 'payments', 'invoices', 'transactions',
            'campaign_events', 'campaign_platforms', 'creatives', 'campaigns', 'images', 'companies', 'interests',
            'regions', 'settings', 'login_codes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
