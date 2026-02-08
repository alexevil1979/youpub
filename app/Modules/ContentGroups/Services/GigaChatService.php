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
    private const MODEL     = 'GigaChat-Plus';
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

        $allVariants = [];
        $usedTitles = [];
        $maxAttempts = 4; // Максимум 4 запроса к API
        $batchSize = min($count, 5); // Просим по 5 за раз — модели проще

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $remaining = $count - count($allVariants);
            if ($remaining <= 0) {
                break;
            }

            $requestCount = min($batchSize, $remaining);
            error_log("GigaChatService: Attempt " . ($attempt + 1) . ", requesting {$requestCount} variants (have " . count($allVariants) . "/{$count})");

            try {
                $prompt = $this->buildPrompt($idea, $requestCount, $language, $usedTitles);
                $rawResponse = $this->callApi($prompt);
                $parsed = $this->parseResponse($rawResponse, $idea, $language);

                if (!empty($parsed)) {
                    foreach ($parsed as $variant) {
                        $title = $variant['content']['title'] ?? '';
                        // Пропускаем дубликаты заголовков
                        if (!empty($title) && in_array($title, $usedTitles, true)) {
                            error_log("GigaChatService: Skipping duplicate title: {$title}");
                            continue;
                        }
                        $usedTitles[] = $title;
                        $variant['variant_number'] = count($allVariants) + 1;
                        $allVariants[] = $variant;

                        if (count($allVariants) >= $count) {
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("GigaChatService: Attempt " . ($attempt + 1) . " failed: " . $e->getMessage());
                // Если первая попытка — пробрасываем ошибку, иначе возвращаем что есть
                if ($attempt === 0 && empty($allVariants)) {
                    throw $e;
                }
                break;
            }
        }

        error_log("GigaChatService: Total variants collected: " . count($allVariants) . "/{$count}");

        if (empty($allVariants)) {
            throw new \RuntimeException('GigaChat не вернул валидные варианты контента');
        }

        return $allVariants;
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

    private function buildPrompt(string $idea, int $count, string $language, array $usedTitles = []): string
    {
        $langInstructions = $language === 'en'
            ? 'Generate ALL content in English.'
            : 'Генерируй ВЕСЬ контент на русском языке.';

        $avoidSection = '';
        if (!empty($usedTitles)) {
            $titlesList = implode("\n", array_map(fn($t) => "- \"{$t}\"", $usedTitles));
            $avoidSection = "\n\nСледующие заголовки УЖЕ ИСПОЛЬЗОВАНЫ, НЕ повторяй их и не создавай похожие:\n{$titlesList}\n";
        }

        return <<<PROMPT
Ты — профессиональный SMM-менеджер и копирайтер для YouTube Shorts.

Задача: сгенерировать РОВНО {$count} уникальных вариантов оформления для YouTube Shorts видео.

Базовая идея видео: "{$idea}"

{$langInstructions}
{$avoidSection}
Для КАЖДОГО из {$count} вариантов сгенерируй:
1. "title" — цепляющий заголовок (до 95 символов). Должен вызывать желание кликнуть. БЕЗ нумерации.
2. "description" — описание видео (2-4 предложения, до 500 символов). Включи CTA.
3. "tags" — массив из 8-12 тегов (без #).
4. "emoji" — 2-3 подходящих emoji.
5. "pinned_comment" — вовлекающий комментарий (вопрос к аудитории).
6. "content_type" — одно из: dance, comedy, aesthetic, emotional, educational, motivation, music, cooking, fitness, beauty, gaming, travel, generic.
7. "mood" — одно из: calm, emotional, neutral, romantic, mysterious, energetic.

ВАЖНО: верни РОВНО {$count} вариантов! Каждый с уникальным стилем и подачей.

Ответь ТОЛЬКО валидным JSON массивом, без пояснений, без markdown:
[{"title":"...","description":"...","tags":["tag1","tag2"],"emoji":"🎵✨","pinned_comment":"...","content_type":"...","mood":"..."}]
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
        error_log('GigaChatService::parseResponse: Raw response (first 2000 chars): ' . mb_substr($raw, 0, 2000));

        $items = $this->extractJsonFromText($raw);

        if (!is_array($items) || empty($items)) {
            error_log('GigaChatService::parseResponse: All JSON extraction methods failed');
            // Последняя попытка — сформировать вариант из сырого текста
            $items = $this->buildFallbackVariant($raw, $idea);
        }

        if (!is_array($items) || empty($items)) {
            throw new \RuntimeException('Не удалось разобрать ответ GigaChat');
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

    // ─── Извлечение JSON ───────────────────────────────────────────

    /**
     * Пробует множество способов извлечь JSON-массив из ответа GigaChat.
     */
    private function extractJsonFromText(string $raw): ?array
    {
        // 1. Прямой парсинг с очисткой markdown
        $clean = trim($raw);

        // Убираем BOM и прочие невидимые символы в начале
        $clean = preg_replace('/^\x{FEFF}/u', '', $clean);

        // Убираем ```json ... ``` (разные варианты)
        if (preg_match('/^```(?:json)?\s*\n?([\s\S]*?)\n?\s*```$/u', $clean, $m)) {
            $clean = trim($m[1]);
        } else {
            // Убираем только открывающие/закрывающие ```
            $clean = preg_replace('/^```(?:json)?\s*\n?/u', '', $clean);
            $clean = preg_replace('/\n?\s*```$/u', '', $clean);
            $clean = trim($clean);
        }

        $items = json_decode($clean, true);
        if (is_array($items) && !empty($items)) {
            // Если вернулся объект, а не массив вариантов — оборачиваем
            if (isset($items['title'])) {
                error_log('GigaChatService::extractJson: Got single object, wrapping in array');
                return [$items];
            }
            error_log('GigaChatService::extractJson: Direct parse OK, ' . count($items) . ' items');
            return $items;
        }

        // 2. Ищем JSON-массив [...] в тексте (самый большой)
        if (preg_match_all('/\[[\s\S]*?\](?=[^]]*$|\s*$)/u', $raw, $allMatches)) {
            // Пробуем от самого длинного совпадения
            $candidates = $allMatches[0];
            usort($candidates, fn($a, $b) => strlen($b) - strlen($a));
            foreach ($candidates as $candidate) {
                $parsed = json_decode($candidate, true);
                if (is_array($parsed) && !empty($parsed)) {
                    error_log('GigaChatService::extractJson: Found array in text, ' . count($parsed) . ' items');
                    return $parsed;
                }
            }
        }

        // 3. Ищем первый [ и последний ] и пробуем всё между ними
        $firstBracket = strpos($raw, '[');
        $lastBracket = strrpos($raw, ']');
        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            $jsonCandidate = substr($raw, $firstBracket, $lastBracket - $firstBracket + 1);
            $parsed = json_decode($jsonCandidate, true);
            if (is_array($parsed) && !empty($parsed)) {
                error_log('GigaChatService::extractJson: Bracket extraction OK, ' . count($parsed) . ' items');
                return $parsed;
            }

            // 3b. Возможно, в JSON есть невалидные trailing commas — пробуем почистить
            $fixedJson = preg_replace('/,\s*([\]}])/u', '$1', $jsonCandidate);
            $parsed = json_decode($fixedJson, true);
            if (is_array($parsed) && !empty($parsed)) {
                error_log('GigaChatService::extractJson: Bracket extraction with trailing comma fix OK');
                return $parsed;
            }
        }

        // 4. Ищем JSON-объект {...} — может быть один вариант вместо массива
        if (preg_match('/\{[\s\S]*"title"[\s\S]*\}/u', $raw, $objMatch)) {
            $parsed = json_decode($objMatch[0], true);
            if (is_array($parsed) && isset($parsed['title'])) {
                error_log('GigaChatService::extractJson: Found single object with title');
                return [$parsed];
            }
        }

        // 5. GigaChat иногда возвращает несколько JSON-объектов через запятую без обёртки в массив
        $wrappedRaw = '[' . $clean . ']';
        $parsed = json_decode($wrappedRaw, true);
        if (is_array($parsed) && !empty($parsed) && isset($parsed[0]['title'])) {
            error_log('GigaChatService::extractJson: Wrapped objects as array OK');
            return $parsed;
        }

        error_log('GigaChatService::extractJson: All methods failed. JSON error: ' . json_last_error_msg());
        return null;
    }

    /**
     * Формирует fallback-вариант из сырого текста, если JSON не удалось извлечь.
     */
    private function buildFallbackVariant(string $raw, string $idea): ?array
    {
        // Пробуем вытащить хотя бы заголовок и описание regex-ом
        $title = '';
        $description = '';
        $tags = [];

        // "title": "...", "description": "..."
        if (preg_match('/"title"\s*:\s*"([^"]+)"/u', $raw, $m)) {
            $title = $m[1];
        }
        if (preg_match('/"description"\s*:\s*"([^"]+)"/u', $raw, $m)) {
            $description = $m[1];
        }
        if (preg_match_all('/"tags"\s*:\s*\[([^\]]+)\]/u', $raw, $m)) {
            foreach ($m[1] as $tagStr) {
                if (preg_match_all('/"([^"]+)"/u', $tagStr, $tagMatches)) {
                    $tags = array_merge($tags, $tagMatches[1]);
                }
            }
        }

        if (empty($title)) {
            error_log('GigaChatService::buildFallbackVariant: Could not extract title from raw text');
            return null;
        }

        error_log('GigaChatService::buildFallbackVariant: Extracted title="' . $title . '"');

        return [[
            'title' => $title,
            'description' => $description ?: $idea,
            'tags' => array_slice(array_unique($tags), 0, 12),
            'emoji' => '🎬',
            'pinned_comment' => '',
            'content_type' => 'generic',
            'mood' => 'neutral',
        ]];
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
