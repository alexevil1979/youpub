<?php

namespace App\Modules\ContentGroups\Services;

/**
 * GroqService — генерация контента через Groq AI API (LLM).
 *
 * Использует Groq Cloud API (совместим с OpenAI chat/completions формат).
 * Ключ API читается из файла local.key в корне проекта.
 *
 * Возвращает данные в том же формате, что AutoShortsGenerator::generateMultipleVariants(),
 * чтобы быть полностью совместимым с существующими контроллерами и view.
 */
class GroqService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';
    private const KEY_FILE = 'local.key';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = $this->loadApiKey();
    }

    /**
     * Проверить, доступен ли Groq API (есть ключ).
     */
    public static function isAvailable(): bool
    {
        $keyPath = self::resolveKeyPath();
        if (!$keyPath || !file_exists($keyPath)) {
            return false;
        }
        $key = trim(file_get_contents($keyPath));
        return !empty($key) && str_starts_with($key, 'gsk_');
    }

    /**
     * Сгенерировать множественные варианты контента из идеи.
     *
     * Возвращает массив в формате, совместимом с AutoShortsGenerator::generateMultipleVariants().
     *
     * @param string $idea       Базовая идея видео
     * @param int    $count      Сколько вариантов сгенерировать (1–10)
     * @param string $language   Язык: 'ru' или 'en'
     * @return array
     * @throws \RuntimeException при ошибке API
     */
    public function generateMultipleVariants(string $idea, int $count = 5, string $language = ''): array
    {
        $idea = trim($idea);
        if (empty($idea) || mb_strlen($idea) < 3) {
            throw new \InvalidArgumentException('Идея должна содержать минимум 3 символа');
        }

        $count = max(1, min($count, 10)); // Groq ограничиваем до 10, чтобы не перегружать
        $language = $language ?: $this->detectLanguage($idea);

        $prompt = $this->buildPrompt($idea, $count, $language);
        $rawResponse = $this->callApi($prompt);
        $parsed = $this->parseResponse($rawResponse, $idea, $language);

        if (empty($parsed)) {
            throw new \RuntimeException('Groq AI не вернул валидные варианты контента');
        }

        return $parsed;
    }

    /**
     * Сгенерировать один вариант контента.
     */
    public function generateFromIdea(string $idea): array
    {
        $variants = $this->generateMultipleVariants($idea, 1);
        if (empty($variants)) {
            throw new \RuntimeException('Groq AI не смог сгенерировать контент для идеи: ' . $idea);
        }
        return $variants[0];
    }

    // ─── Внутренние методы ───────────────────────────────────────────

    private function loadApiKey(): string
    {
        $path = self::resolveKeyPath();
        if (!$path || !file_exists($path)) {
            throw new \RuntimeException(
                'Файл local.key не найден. Положите файл с ключом Groq API в корень проекта.'
            );
        }
        $key = trim(file_get_contents($path));
        if (empty($key)) {
            throw new \RuntimeException('Файл local.key пуст. Укажите ключ Groq API.');
        }
        return $key;
    }

    private static function resolveKeyPath(): ?string
    {
        // Ищем local.key относительно корня проекта
        $candidates = [
            __DIR__ . '/../../../../local.key',            // app/Modules/ContentGroups/Services -> root
            $_SERVER['DOCUMENT_ROOT'] . '/../local.key',   // web root -> root
        ];

        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real && file_exists($real)) {
                return $real;
            }
        }

        // Попробуем getcwd
        $cwd = getcwd();
        if ($cwd) {
            $path = $cwd . '/local.key';
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Собрать промпт для генерации контента.
     */
    private function buildPrompt(string $idea, int $count, string $language): string
    {
        $langInstructions = $language === 'en'
            ? 'Generate ALL content in English.'
            : 'Генерируй ВЕСЬ контент на русском языке.';

        return <<<PROMPT
Ты — профессиональный SMM-менеджер и копирайтер для YouTube Shorts.

Задача: сгенерировать {$count} уникальных вариантов оформления для YouTube Shorts видео.

Базовая идея видео: "{$idea}"

{$langInstructions}

Для КАЖДОГО варианта сгенерируй:
1. **title** — цепляющий заголовок (до 95 символов). Должен вызывать желание кликнуть. БЕЗ нумерации, БЕЗ слов "Часть", "Серия", "Эпизод".
2. **description** — описание видео (2-4 предложения, до 500 символов). Включи CTA (призыв к действию).
3. **tags** — массив из 8-12 релевантных тегов/хештегов (без #).
4. **emoji** — строка из 2-3 подходящих emoji.
5. **pinned_comment** — вовлекающий закреплённый комментарий (вопрос к аудитории).
6. **content_type** — тип контента: одно из [dance, comedy, aesthetic, emotional, educational, motivation, music, cooking, fitness, beauty, gaming, travel, generic].
7. **mood** — настроение: одно из [calm, emotional, neutral, romantic, mysterious, energetic].

Каждый вариант должен быть УНИКАЛЬНЫМ по стилю и подаче. Не повторяй заголовки и описания.

Верни ТОЛЬКО валидный JSON массив (без markdown-обёрток, без ```json):
[
  {
    "title": "...",
    "description": "...",
    "tags": ["tag1", "tag2", ...],
    "emoji": "🎵✨",
    "pinned_comment": "...",
    "content_type": "...",
    "mood": "..."
  }
]
PROMPT;
    }

    /**
     * Вызвать Groq API.
     */
    private function callApi(string $prompt): string
    {
        $payload = json_encode([
            'model' => self::MODEL,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Ты — эксперт по YouTube Shorts. Отвечай ТОЛЬКО валидным JSON. Никаких пояснений, только JSON массив.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.9,
            'max_tokens' => 4096,
            'top_p' => 1.0,
        ], JSON_UNESCAPED_UNICODE);

        error_log('GroqService::callApi: Sending request to Groq API, model: ' . self::MODEL);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("GroqService::callApi: cURL error: {$error}");
            throw new \RuntimeException('Ошибка подключения к Groq API: ' . $error);
        }

        if ($httpCode !== 200) {
            error_log("GroqService::callApi: HTTP {$httpCode}, response: " . mb_substr($response, 0, 500));
            $errorMsg = 'Groq API вернул ошибку (HTTP ' . $httpCode . ')';
            // Попробуем извлечь сообщение об ошибке
            $decoded = json_decode($response, true);
            if (isset($decoded['error']['message'])) {
                $errorMsg .= ': ' . $decoded['error']['message'];
            }
            throw new \RuntimeException($errorMsg);
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            error_log("GroqService::callApi: Unexpected response structure: " . mb_substr($response, 0, 500));
            throw new \RuntimeException('Groq API вернул неожиданный формат ответа');
        }

        $content = $decoded['choices'][0]['message']['content'];
        error_log('GroqService::callApi: Response received, length: ' . strlen($content));

        // Логируем использование токенов
        if (isset($decoded['usage'])) {
            error_log('GroqService::callApi: Tokens used - prompt: ' .
                ($decoded['usage']['prompt_tokens'] ?? '?') .
                ', completion: ' . ($decoded['usage']['completion_tokens'] ?? '?') .
                ', total: ' . ($decoded['usage']['total_tokens'] ?? '?'));
        }

        return $content;
    }

    /**
     * Разобрать ответ Groq API и привести к стандартному формату.
     */
    private function parseResponse(string $raw, string $idea, string $language): array
    {
        // Очищаем от markdown-обёрток если есть
        $clean = trim($raw);
        if (str_starts_with($clean, '```json')) {
            $clean = substr($clean, 7);
        } elseif (str_starts_with($clean, '```')) {
            $clean = substr($clean, 3);
        }
        if (str_ends_with($clean, '```')) {
            $clean = substr($clean, 0, -3);
        }
        $clean = trim($clean);

        $items = json_decode($clean, true);

        if (!is_array($items) || empty($items)) {
            error_log('GroqService::parseResponse: Failed to parse JSON. Raw: ' . mb_substr($raw, 0, 500));
            // Попробуем извлечь JSON из текста
            if (preg_match('/\[[\s\S]*\]/u', $raw, $matches)) {
                $items = json_decode($matches[0], true);
            }
            if (!is_array($items) || empty($items)) {
                throw new \RuntimeException('Не удалось разобрать ответ Groq AI');
            }
        }

        $variants = [];
        $usedTitles = [];

        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim($item['title'] ?? '');
            $description = trim($item['description'] ?? '');
            $tags = $item['tags'] ?? [];
            $emoji = trim($item['emoji'] ?? '');
            $pinnedComment = trim($item['pinned_comment'] ?? '');
            $contentType = trim($item['content_type'] ?? 'generic');
            $mood = trim($item['mood'] ?? 'neutral');

            // Пропускаем пустые варианты
            if (empty($title) && empty($description)) {
                continue;
            }

            // Защита от дубликатов
            if (in_array($title, $usedTitles, true)) {
                $title .= ' #' . ($i + 1);
            }
            $usedTitles[] = $title;

            // Обрезаем title до 95 символов
            if (mb_strlen($title) > 95) {
                $title = mb_substr($title, 0, 94) . '…';
            }

            // Обрезаем description до 4500
            if (mb_strlen($description) > 4500) {
                $description = mb_substr($description, 0, 4499) . '…';
            }

            // Нормализуем теги
            if (is_string($tags)) {
                $tags = array_filter(array_map('trim', explode(',', $tags)));
            }
            if (!is_array($tags)) {
                $tags = [];
            }

            $intent = [
                'content_type' => $contentType,
                'mood' => $mood,
                'visual_focus' => $this->detectVisualFocus($idea),
                'language' => $language,
                'platform' => 'shorts',
                'idea' => $idea,
                'raw_idea' => $idea,
            ];

            $content = [
                'title' => $title,
                'description' => $description,
                'emoji' => $emoji,
                'tags' => $tags,
                'pinned_comment' => $pinnedComment,
                'angle' => sprintf('AI Groq • Тип: %s • Настроение: %s', $contentType, $mood),
            ];

            $variants[] = [
                'idea' => $idea,
                'intent' => $intent,
                'content' => $content,
                'variant_number' => $i + 1,
                'generated_at' => date('Y-m-d H:i:s'),
                'source' => 'groq_ai',
            ];
        }

        error_log('GroqService::parseResponse: Parsed ' . count($variants) . ' variants');
        return $variants;
    }

    private function detectLanguage(string $text): string
    {
        $hasLatin = (bool) preg_match('/[a-z]/i', $text);
        $hasCyrillic = (bool) preg_match('/[а-яё]/iu', $text);

        if ($hasLatin && !$hasCyrillic) {
            return 'en';
        }
        return 'ru';
    }

    private function detectVisualFocus(string $text): string
    {
        $t = mb_strtolower($text);
        if (preg_match('/неон|neon|glow|огни|lights|свет/i', $t)) {
            return 'neon';
        }
        if (preg_match('/ноч[ьи]|night|dark|moon|ночной/i', $t)) {
            return 'night';
        }
        if (preg_match('/голос|voice|vocal|sing|поёт|пою/i', $t)) {
            return 'voice';
        }
        return 'default';
    }
}
