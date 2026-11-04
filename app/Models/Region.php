<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    public const RUSSIA = 225;

    public const MOSCOW = 213;

    public const SPB = 2;

    public function label(): string
    {
        return $this->parent_name && $this->depth >= 3 ? $this->name.' ('.$this->parent_name.')' : $this->name;
    }

    /** Угадать регион по адресу из вакансии: «Курская область, г Курчатов» → Курчатов или Курская область. */
    public static function guess(?string $text): ?self
    {
        if (! $text) {
            return null;
        }
        $parts = collect(preg_split('/[,;]/u', $text))
            ->map(fn ($p) => trim(preg_replace('/^(г\.?|город|пос\.?|с\.)\s+/u', '', trim($p))))
            ->filter(fn ($p) => mb_strlen($p) >= 3)->values();

        foreach ($parts->reverse() as $part) {
            if ($region = static::where('name', $part)->orderByDesc('depth')->first()) {
                return $region;
            }
        }

        return null;
    }

    public static function search(string $q, int $limit = 12)
    {
        $q = trim($q);

        return static::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderBy('sort')->orderBy('depth')->orderBy('name')
            ->limit($limit)->get();
    }
}
