<?php

namespace App\Services\Integrations;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Генерация изображений Qwen Image 3 (обычная и Pro). URL и ключ для каждой версии — в админке.
 * Поддерживает два формата API:
 *  - OpenAI-совместимый: POST .../images/generations {model, prompt, size, n} → data[0].url | data[0].b64_json
 *  - DashScope: POST .../multimodal-generation/generation {model, input.messages, parameters.size} → output.choices[0].message.content[0].image
 */
class QwenImageClient
{
    public const SIZES = [
        '1:1' => '1328*1328',
        '16:9' => '1664*928',
        '4:5' => '1140*1472',
    ];

    public const NEGATIVE = 'текст, буквы, надписи, водяные знаки, логотипы, искажённые лица, лишние пальцы, размытость';

    public function isConfigured(bool $pro = false): bool
    {
        return Settings::has(($pro ? 'qwen_pro' : 'qwen').'.api_key');
    }

    /** @return string бинарное содержимое картинки */
    public function generate(string $prompt, string $format = '1:1', bool $pro = false): string
    {
        $prefix = $pro ? 'qwen_pro' : 'qwen';
        if (! $this->isConfigured($pro)) {
            throw IntegrationException::notConfigured($pro ? 'Qwen Image Pro' : 'Qwen Image');
        }

        $url = (string) Settings::get($prefix.'.url');
        $model = (string) Settings::get($prefix.'.model');
        $size = self::SIZES[$format] ?? self::SIZES['1:1'];
        $openAi = str_contains($url, '/images/generations');

        $payload = $openAi
            ? ['model' => $model, 'prompt' => $prompt, 'n' => 1, 'size' => str_replace('*', 'x', $size), 'negative_prompt' => self::NEGATIVE]
            : [
                'model' => $model,
                'input' => ['messages' => [['role' => 'user', 'content' => [['text' => $prompt]]]]],
                'parameters' => ['size' => $size, 'negative_prompt' => self::NEGATIVE, 'prompt_extend' => true, 'watermark' => false],
            ];

        try {
            $response = Http::withToken(Settings::get($prefix.'.api_key'))->acceptJson()->timeout(120)->post($url, $payload);
        } catch (Throwable $e) {
            Log::warning('Qwen request failed', ['error' => $e->getMessage()]);
            throw new IntegrationException('Сервис генерации картинок недоступен');
        }

        if (! $response->successful()) {
            Log::warning('Qwen error', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new IntegrationException('Генерация картинки не удалась (ошибка '.$response->status().')');
        }

        $b64 = $response->json('data.0.b64_json');
        if ($b64) {
            return base64_decode($b64);
        }

        $imageUrl = $response->json('data.0.url') ?? $response->json('output.choices.0.message.content.0.image')
            ?? $response->json('output.results.0.url');

        if (! $imageUrl) {
            throw new IntegrationException('Сервис генерации вернул ответ без картинки');
        }

        // Ссылки у генератора временные — скачиваем сразу
        $image = Http::timeout(60)->get($imageUrl);
        if (! $image->successful()) {
            throw new IntegrationException('Не удалось скачать сгенерированную картинку');
        }

        return $image->body();
    }
}
