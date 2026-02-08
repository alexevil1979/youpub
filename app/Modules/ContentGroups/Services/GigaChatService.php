<?php

namespace App\Modules\ContentGroups\Services;

/**
 * GigaChatService — генерация контента через GigaChat API (Сбер).
 *
 * Использует GigaChat REST API с OAuth2-авторизацией.
 * Ключ авторизации (Base64 clientId:clientSecret) читается из файла gigachat.key.
 *
 * Возвращает данные в том же формате, что AutoShortsGenerator / GroqService,
 * чтобы быть полностью совместимым с существующими контроллерами и view.
 */
class GigaChatService
{
    private const OAUTH_URL = 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth';
    private const API_URL   = 'https://gigachat.devices.sberbank.ru/api/v1/chat/completions';
    private const SCOPE     = 'GIGACHAT_API_PERS';
    private const MODEL     = 'GigaChat';
    private const KEY_FILE  = 'gigachat.key';

    private string $authCredentials;
    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct()
    {
        $this->authCredentials = $this->loadAuthKey();
    }

    /**
     * Проверить, доступен ли GigaChat API (есть ключ).
     */
    public static function isAvailable(): bool
    {
        $keyPath = self::resolveKeyPath();
        if (!$keyPath || !file_exists($keyPath)) {
            return false;
        }
        $key = trim(file_get_contents($keyPath));
        return !empty($key) && strlen($key) > 20;
    }

    /**
     * Сгенерировать множественные варианты контента из идеи.
     *
     * @param string $idea       Базовая идея видео
     * @param int    $count      Сколько вариантов (1–10)
     * @param string $language   Язык: 'ru' или 'en'
     * @return array  совместимый с AutoShortsGenerator
     * @throws \RuntimeException при ошибке API
     */
    public function generateMultipleVariants(string $idea, int $count = 5, string $language = ''): array
    {
        $idea = trim($idea);
        if (empty($idea) || mb_strlen($idea) < 3) {
            throw new \InvalidArgumentException('Идея должна содержать минимум 3 символа');
        }

        $count = max(1, min($count, 10));
        $language = $language ?: $this->detectLanguage($idea);

        $prompt = $this->buildPrompt($idea, $count, $language);
        $rawResponse = $this->callApi($prompt);
        $parsed = $this->parseResponse($rawResponse, $idea, $language);

        if (empty($parsed)) {
            throw new \RuntimeException('GigaChat не вернул валидные варианты контента');
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
            throw new \RuntimeException('GigaChat не смог сгенерировать контент для идеи: ' . $idea);
        }
        return $variants[0];
    }

    // ─── Авторизация ─────────────────────────────────────────────────

    /**
     * Получить access token через OAuth2.
     * Токен кешируется в памяти на 29 минут (действителен 30).
     */
    private function getAccessToken(): string
    {
        // Проверяем кеш
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $rqUID = $this->generateUuid4();

        error_log('GigaChatService::getAccessToken: Requesting new access token');

        $ch = curl_init(self::OAUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['scope' => self::SCOPE]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'Authorization: Basic ' . $this->authCredentials,
                'RqUID: ' . $rqUID,
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // Сертификаты Минцифры
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("GigaChatService::getAccessToken: cURL error: {$error}");
            throw new \RuntimeException('Ошибка подключения к GigaChat OAuth: ' . $error);
        }

        if ($httpCode !== 200) {
            error_log("GigaChatService::getAccessToken: HTTP {$httpCode}, response: " . mb_substr($response, 0, 500));
            throw new \RuntimeException('GigaChat OAuth вернул ошибку (HTTP ' . $httpCode . ')');
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['access_token'])) {
            error_log("GigaChatService::getAccessToken: No access_token in response: " . mb_substr($response, 0, 500));
            throw new \RuntimeException('GigaChat OAuth не вернул access_token');
        }

        $this->accessToken = $decoded['access_token'];
        // Токен действителен 30 минут, кешируем на 29
        $this->tokenExpiresAt = time() + 29 * 60;

        error_log('GigaChatService::getAccessToken: Token obtained, expires_at: ' . ($decoded['expires_at'] ?? 'unknown'));

        return $this->accessToken;
    }

    // ─── Вызов API ───────────────────────────────────────────────────

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
    "tags": ["tag1", "tag2"],
    "emoji": "🎵✨",
    "pinned_comment": "...",
    "content_type": "...",
    "mood": "..."
  }
]
PROMPT;
    }

    private function callApi(string $prompt): string
    {
        $token = $this->getAccessToken();

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
            'top_p' => 0.9,
            'repetition_penalty' => 1.1,
        ], JSON_UNESCAPED_UNICODE);

        error_log('GigaChatService::callApi: Sending request to GigaChat, model: ' . self::MODEL);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // Сертификаты Минцифры
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("GigaChatService::callApi: cURL error: {$error}");
            throw new \RuntimeException('Ошибка подключения к GigaChat API: ' . $error);
        }

        if ($httpCode === 401) {
            // Токен мог истечь — сбрасываем и пробуем ещё раз
            error_log("GigaChatService::callApi: 401 Unauthorized, refreshing token");
            $this->accessToken = null;
            $this->tokenExpiresAt = null;
            return $this->callApiRetry($payload);
        }

        if ($httpCode !== 200) {
            error_log("GigaChatService::callApi: HTTP {$httpCode}, response: " . mb_substr($response, 0, 500));
            $errorMsg = 'GigaChat API вернул ошибку (HTTP ' . $httpCode . ')';
            $decoded = json_decode($response, true);
            if (isset($decoded['message'])) {
                $errorMsg .= ': ' . $decoded['message'];
            }
            throw new \RuntimeException($errorMsg);
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            error_log("GigaChatService::callApi: Unexpected response: " . mb_substr($response, 0, 500));
            throw new \RuntimeException('GigaChat вернул неожиданный формат ответа');
        }

        $content = $decoded['choices'][0]['message']['content'];
        error_log('GigaChatService::callApi: Response received, length: ' . strlen($content));

        if (isset($decoded['usage'])) {
            error_log('GigaChatService::callApi: Tokens used - prompt: ' .
                ($decoded['usage']['prompt_tokens'] ?? '?') .
                ', completion: ' . ($decoded['usage']['completion_tokens'] ?? '?') .
                ', total: ' . ($decoded['usage']['total_tokens'] ?? '?'));
        }

        return $content;
    }

    /**
     * Повторный вызов с обновлённым токеном.
     */
    private function callApiRetry(string $payload): string
    {
        $token = $this->getAccessToken();

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Ошибка подключения к GigaChat API (retry): ' . $error);
        }

        if ($httpCode !== 200) {
            error_log("GigaChatService::callApiRetry: HTTP {$httpCode}, response: " . mb_substr($response, 0, 500));
            throw new \RuntimeException('GigaChat API вернул ошибку после обновления токена (HTTP ' . $httpCode . ')');
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new \RuntimeException('GigaChat вернул неожиданный формат ответа (retry)');
        }

        return $decoded['choices'][0]['message']['content'];
    }

    // ─── Парсинг ответа ──────────────────────────────────────────────

    private function parseResponse(string $raw, string $idea, string $language): array
    {
        // Очищаем от markdown-обёрток
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
            error_log('GigaChatService::parseResponse: Failed to parse JSON. Raw: ' . mb_substr($raw, 0, 500));
            // Пробуем извлечь JSON из текста
            if (preg_match('/\[[\s\S]*\]/u', $raw, $matches)) {
                $items = json_decode($matches[0], true);
            }
            if (!is_array($items) || empty($items)) {
                throw new \RuntimeException('Не удалось разобрать ответ GigaChat');
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

            if (empty($title) && empty($description)) {
                continue;
            }

            // Защита от дубликатов
            if (in_array($title, $usedTitles, true)) {
                $title .= ' #' . ($i + 1);
            }
            $usedTitles[] = $title;

            if (mb_strlen($title) > 95) {
                $title = mb_substr($title, 0, 94) . '…';
            }
            if (mb_strlen($description) > 4500) {
                $description = mb_substr($description, 0, 4499) . '…';
            }

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
                'angle' => sprintf('GigaChat AI • Тип: %s • Настроение: %s', $contentType, $mood),
            ];

            $variants[] = [
                'idea' => $idea,
                'intent' => $intent,
                'content' => $content,
                'variant_number' => $i + 1,
                'generated_at' => date('Y-m-d H:i:s'),
                'source' => 'gigachat_ai',
            ];
        }

        error_log('GigaChatService::parseResponse: Parsed ' . count($variants) . ' variants');
        return $variants;
    }

    // ─── Утилиты ─────────────────────────────────────────────────────

    private function loadAuthKey(): string
    {
        $path = self::resolveKeyPath();
        if (!$path || !file_exists($path)) {
            throw new \RuntimeException(
                'Файл gigachat.key не найден. Положите файл с ключом авторизации GigaChat в корень проекта.'
            );
        }
        $key = trim(file_get_contents($path));
        if (empty($key)) {
            throw new \RuntimeException('Файл gigachat.key пуст. Укажите ключ авторизации GigaChat.');
        }
        return $key;
    }

    private static function resolveKeyPath(): ?string
    {
        $candidates = [
            __DIR__ . '/../../../../gigachat.key',
            $_SERVER['DOCUMENT_ROOT'] . '/../gigachat.key',
        ];

        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real && file_exists($real)) {
                return $real;
            }
        }

        $cwd = getcwd();
        if ($cwd) {
            $path = $cwd . '/gigachat.key';
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
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

    /**
     * Генерирует UUID v4 для RqUID.
     */
    private function generateUuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
