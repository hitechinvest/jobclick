<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/** ZIP с креативами и текстами кампании для ручного размещения в сетях. */
class CreativesZipController extends Controller
{
    public function __invoke(Campaign $campaign)
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $path = tempnam(sys_get_temp_dir(), 'jc').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($campaign->creatives as $creative) {
            if (Storage::disk('public')->exists($creative->path)) {
                $zip->addFromString(str_replace(':', 'x', $creative->format).'/'.basename($creative->path), Storage::disk('public')->get($creative->path));
            }
        }

        $lines = [
            'Кампания #'.$campaign->id,
            'Заголовок: '.$campaign->title,
            'Текст: '.$campaign->text,
            'Длинный текст VK: '.$campaign->long_text,
            'ИНН рекламодателя: '.$campaign->user->company?->inn,
            'Рекламодатель: '.$campaign->user->company?->name_full,
            '',
            'Трекинг-ссылки:',
        ];
        foreach ($campaign->platformEnums() as $platform) {
            $lines[] = $platform->label().': '.$campaign->trackingUrl($platform->value);
        }
        $zip->addFromString('texts.txt', implode("\r\n", $lines));
        $zip->close();

        return response()->download($path, 'campaign-'.$campaign->id.'.zip')->deleteFileAfterSend();
    }
}
