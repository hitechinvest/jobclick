<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    protected $guarded = ['id', 'reserved', 'spent', 'clicks'];

    protected $attributes = [
        'status' => 'draft',
        'gender' => 'any',
        'income' => 'any',
        'age_from' => 18,
        'age_to' => 65,
        'age_unknown' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'vacancy' => 'array',
            'regions' => 'array',
            'interests' => 'array',
            'platforms' => 'array',
            'age_unknown' => 'boolean',
            'total_budget' => 'decimal:2',
            'daily_budget' => 'decimal:2',
            'cpc' => 'decimal:2',
            'reserved' => 'decimal:2',
            'spent' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'alert_80_at' => 'datetime',
            'vacancy_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            if (! $campaign->slug) {
                do {
                    $slug = Str::lower(Str::random(8));
                } while (static::where('slug', $slug)->exists());
                $campaign->slug = $slug;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(CampaignPlatform::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class)->latest('id');
    }

    public function clickEvents(): HasMany
    {
        return $this->hasMany(ClickEvent::class);
    }

    public function networkStats(): HasMany
    {
        return $this->hasMany(NetworkStat::class);
    }

    public function log(string $type, ?string $message = null, ?int $userId = null): CampaignEvent
    {
        return $this->events()->create([
            'type' => $type,
            'message' => $message,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->vacancy['title'] ?? 'Новая кампания #'.$this->id);
    }

    public function landingUrl(): ?string
    {
        return $this->source_url;
    }

    /** Ссылка, которую админ ставит в объявление на конкретной площадке. */
    public function trackingUrl(?string $platform = null): string
    {
        return route('track', ['slug' => $this->slug]).($platform ? '?p='.$platform : '');
    }

    /** @return array<int, Platform> */
    public function platformEnums(): array
    {
        return collect($this->platforms ?? [])->map(fn ($p) => Platform::tryFrom($p))->filter()->values()->all();
    }

    public function regionModels()
    {
        return Region::whereIn('id', $this->regions ?? [])->get();
    }

    public function regionNames(): string
    {
        return $this->regionModels()->pluck('name')->join(', ');
    }

    public function budgetLeft(): float
    {
        return round((float) $this->reserved, 2);
    }

    public function spentPercent(): int
    {
        $budget = (float) $this->total_budget;

        return $budget > 0 ? (int) min(100, round((float) $this->spent / $budget * 100)) : 0;
    }

    public function spentToday(): float
    {
        return (float) $this->clickEvents()->where('billable', true)->where('created_at', '>=', now()->startOfDay())->sum('charged');
    }
}
