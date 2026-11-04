<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Creative;
use App\Models\Image;
use App\Models\User;
use App\Services\Integrations\IntegrationException;
use App\Services\Integrations\SafeHttp;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/** Библиотека картинок пользователя и нарезка креативов под форматы рекламных сетей. */
class ImageService
{
    /** Форматы креативов: Директ РСЯ (1:1, 16:9), VK Реклама (1:1, 16:9, 4:5). */
    public const FORMATS = [
        '1:1' => [1080, 1080],
        '16:9' => [1080, 607],
        '4:5' => [1080, 1350],
    ];

    public const FOCUS = ['center' => 'По центру', 'top' => 'Верх', 'bottom' => 'Низ', 'left' => 'Слева', 'right' => 'Справа'];

    public const MAX_BYTES = 10 * 1024 * 1024;

    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(Driver::class);
    }

    public static function isSuitable(int $width, int $height): bool
    {
        if ($width < Settings::int('tariff.image_min_width') || $height < Settings::int('tariff.image_min_height')) {
            return false;
        }
        $ratio = $width / max(1, $height);

        return $ratio >= 0.5 && $ratio <= 2.0;
    }

    /** Сохраняет картинку в библиотеку пользователя (пересохраняя в JPEG, без EXIF). Дубликаты не плодим. */
    public function store(User $user, string $binary, string $source, array $meta = []): Image
    {
        if (strlen($binary) > self::MAX_BYTES) {
            throw new IntegrationException('Картинка больше 10 МБ');
        }

        try {
            $image = $this->manager->decodeBinary($binary);
        } catch (Throwable) {
            throw new IntegrationException('Файл не является картинкой JPG, PNG или WebP');
        }

        $image->scaleDown(2560, 2560);
        $jpeg = $image->encode(new JpegEncoder(quality: 90, strip: true))->toString();
        $sha = hash('sha256', $jpeg);

        if ($existing = Image::where('user_id', $user->id)->where('sha256', $sha)->first()) {
            return $existing;
        }

        $path = 'images/'.$user->id.'/'.substr($sha, 0, 24).'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return Image::create([
            'user_id' => $user->id,
            'path' => $path,
            'width' => $image->width(),
            'height' => $image->height(),
            'source' => $source,
            'source_url' => $meta['source_url'] ?? null,
            'prompt' => $meta['prompt'] ?? null,
            'model' => $meta['model'] ?? null,
            'sha256' => $sha,
        ]);
    }

    /** Скачивает картинку со страницы вакансии в библиотеку. */
    public function importFromUrl(User $user, string $url): Image
    {
        $response = SafeHttp::get($url, ['Accept' => 'image/*'], 15);
        if (! $response->successful()) {
            throw new IntegrationException('Не удалось скачать картинку');
        }

        return $this->store($user, $response->body(), 'extracted', ['source_url' => $url]);
    }

    /** Размеры удалённой картинки без сохранения (для фильтра «подходит для сетей»). */
    public function probe(string $url): ?array
    {
        try {
            $response = SafeHttp::get($url, ['Accept' => 'image/*'], 8);
            if (! $response->successful()) {
                return null;
            }
            $info = @getimagesizefromstring($response->body());

            return $info ? ['width' => $info[0], 'height' => $info[1]] : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Пересобирает креативы кампании из выбранных картинок во все форматы. */
    public function buildCreatives(Campaign $campaign, array $imageIds, array $focus = []): void
    {
        $images = Image::where('user_id', $campaign->user_id)->whereIn('id', $imageIds)->get()->keyBy('id');

        foreach ($campaign->creatives as $old) {
            Storage::disk('public')->delete($old->path);
            $old->delete();
        }

        foreach ($imageIds as $id) {
            $img = $images->get($id);
            if (! $img) {
                continue;
            }
            $position = $focus[$id] ?? 'center';
            $source = Storage::disk('public')->get($img->path);

            foreach (self::FORMATS as $format => [$w, $h]) {
                $crop = $this->manager->decodeBinary($source)->cover($w, $h, $position)
                    ->encode(new JpegEncoder(quality: 88, strip: true))->toString();
                $path = 'creatives/'.$campaign->id.'/'.$img->id.'-'.str_replace(':', 'x', $format).'.jpg';
                Storage::disk('public')->put($path, $crop);

                Creative::create([
                    'campaign_id' => $campaign->id,
                    'image_id' => $img->id,
                    'format' => $format,
                    'focus' => $position,
                    'path' => $path,
                    'width' => $w,
                    'height' => $h,
                ]);
            }
        }
    }
}
