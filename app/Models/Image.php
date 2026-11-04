<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    protected $guarded = ['id'];

    public const SOURCES = ['upload' => 'Загружена', 'extracted' => 'Со страницы вакансии', 'generated' => 'Сгенерирована ИИ'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** Подходит ли картинка для рекламных сетей (минимальные размеры из настроек). */
    public function isSuitable(): bool
    {
        return \App\Services\ImageService::isSuitable($this->width, $this->height);
    }
}
