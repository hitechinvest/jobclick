<?php

namespace App\Services\Integrations;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DeepSeek (OpenAI-совместимый chat completions) — генерация текста объявления по вакансии.
 */
class DeepSeekClient
{
    public const SYSTEM_PROMPT = <<<'TXT'
Ты — опытный копирайтер рекламы вакансий для Яндекс Директа и VK Рекламы.
По данным вакансии напиши объявление, которое хочется кликнуть: конкретика (зарплата, график, город, главные плюсы), без воды.
Строгие правила:
- title: до 56 символов, без точки в конце, название должности + главный плюс (часто зарплата).
- text: до 81 символа, 1–2 коротких предложения, призыв откликнуться.
- long_text: до 220 символов для VK, 2–3 предложения с условиями и плюсами.
- Запрещено указывать требования к полу, возрасту, национальности, внешности, месту жительства, семейному положению (закон о занятости).
- Запрещено обещать гарантированный доход, «заработок без вложений», превосходные степени без подтверждения («лучшая компания»).
- Не выдумывай цифры, которых нет в вакансии. Нет зарплаты — не пиши зарплату.
- Пиши по-русски, без эмодзи, без кавычек-ёлочек вокруг всего текста.
Ответь строго JSON: {"title": "...", "text": "...", "long_text": "..."}
TXT;

    public function isConfigured(): bool
    {
        return Settings::has('deepseek.api_key');
    }

    /**
     * @param  array<string, mixed>  $vacancy  нормализованная вакансия (title, salary, city, employer, description)
     * @return array{title:string, text:string, long_text:string}
     */
    public function generateAd(array $vacancy, ?string $url = null, ?string $rawPage = null, string $tone = 'нейтральный'): array
    {
        $parts = [];
        foreach (['title' => 'Должность', 'salary' => 'Зарплата', 'city' => 'Город', 'employer' => 'Работодатель',
            'schedule' => 'График', 'description' => 'Описание'] as $key => $label) {
            if (! empty($vacancy[$key])) {
                $parts[] = $label.': '.mb_substr(strip_tags((string) $vacancy[$key]), 0, 4000);
            }
        }

        if ($parts === [] && $rawPage) {
            $parts[] = "Текст страницы вакансии (может содержать лишнее):\n".mb_substr($rawPage, 0, 6000);
        }
        if ($url) {
            $parts[] = 'Ссылка на вакансию: '.$url;
        }

        $user = "Тон: {$tone}.\nДанные вакансии:\n".implode("\n", $parts);
        $json = $this->chatJson(self::SYSTEM_PROMPT, $user);

        return [
            'title' => mb_substr(trim((string) ($json['title'] ?? '')), 0, 56),
            'text' => mb_substr(trim((string) ($json['text'] ?? '')), 0, 81),
            'long_text' => mb_substr(trim((string) ($json['long_text'] ?? '')), 0, 220),
        ];
    }

    /** Промпт для генерации картинки из заголовка. */
    public function imagePrompt(string $title, ?string $city = null): string
    {
        return trim('Реалистичное рекламное фото для вакансии «'.$title.'»'.($city ? ', '.$city : '')
            .'. Довольный сотрудник за работой, светлое современное рабочее место, дружелюбная атмосфера, естественный свет, без текста и логотипов на изображении.');
    }

    public function chatJson(string $system, string $user): array
    {
        if (! $this->isConfigured()) {
            throw IntegrationException::notConfigured('DeepSeek');
        }

        try {
            $response = Http::withToken(Settings::get('deepseek.api_key'))
                ->acceptJson()->timeout(60)
                ->post(rtrim(Settings::get('deepseek.base_url'), '/').'/chat/completions', [
                    'model' => Settings::get('deepseek.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.8,
                    'max_tokens' => 600,
                ]);
        } catch (Throwable $e) {
            Log::warning('DeepSeek request failed', ['error' => $e->getMessage()]);
            throw new IntegrationException('DeepSeek недоступен, попробуйте ещё раз');
        }

        if (! $response->successful()) {
            Log::warning('DeepSeek error', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new IntegrationException('DeepSeek вернул ошибку '.$response->status());
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $content = preg_replace('/^```(?:json)?|```$/m', '', trim($content));
        $json = json_decode((string) $content, true);

        if (! is_array($json)) {
            throw new IntegrationException('DeepSeek вернул неожиданный ответ');
        }

        return $json;
    }
}
