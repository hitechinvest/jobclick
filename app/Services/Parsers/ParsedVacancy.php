<?php

namespace App\Services\Parsers;

/** Нормализованные данные вакансии с любого сайта. */
class ParsedVacancy
{
    public function __construct(
        public string $site,
        public ?string $title = null,
        public ?string $salary = null,
        public ?string $city = null,
        public ?string $employer = null,
        public ?string $schedule = null,
        public ?string $description = null,
        public ?string $logo = null,
        /** @var array<int, string> */
        public array $images = [],
        public ?string $rawText = null,
        public bool $blocked = false,
        public ?string $employerInn = null,
        public bool $archived = false,
    ) {}

    public function hasContent(): bool
    {
        return filled($this->title) || filled($this->description);
    }

    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'title' => $this->title,
            'salary' => $this->salary,
            'city' => $this->city,
            'employer' => $this->employer,
            'employer_inn' => $this->employerInn,
            'schedule' => $this->schedule,
            'description' => $this->description ? mb_substr($this->description, 0, 8000) : null,
            'logo' => $this->logo,
            'images' => array_values(array_unique(array_filter($this->images))),
            'raw_text' => $this->rawText ? mb_substr($this->rawText, 0, 8000) : null,
            'blocked' => $this->blocked,
            'archived' => $this->archived,
        ];
    }

    /** Дополняет пустые поля значениями из другого результата. */
    public function mergeMissing(ParsedVacancy $other): self
    {
        foreach (['title', 'salary', 'city', 'employer', 'schedule', 'description', 'logo', 'rawText', 'employerInn'] as $field) {
            if (blank($this->{$field}) && filled($other->{$field})) {
                $this->{$field} = $other->{$field};
            }
        }
        $this->images = array_values(array_unique(array_merge($this->images, $other->images)));

        return $this;
    }

    public static function salaryText(?float $from, ?float $to, ?string $currency = 'RUR', bool $gross = false): ?string
    {
        if (! $from && ! $to) {
            return null;
        }
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $cur = in_array(strtoupper((string) $currency), ['RUR', 'RUB', ''], true) ? '₽' : strtoupper((string) $currency);
        $text = match (true) {
            $from && $to && $from != $to => 'от '.$fmt($from).' до '.$fmt($to),
            (bool) $from => 'от '.$fmt($from),
            default => 'до '.$fmt($to),
        };

        return $text.' '.$cur.($gross ? ' до вычета налогов' : '');
    }
}
