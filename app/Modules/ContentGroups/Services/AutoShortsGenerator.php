<?php

namespace App\Modules\ContentGroups\Services;

/**
 * AutoShortsGenerator
 *
 * Единая точка входа для автогенерации Shorts‑контента.
 *
 * Отвечает за:
 * - анализ идеи (через AutoShortsIntentService)
 * - генерацию заголовка / описания / тегов (через AutoShortsTemplateService)
 * - формирование результата в формате, ожидаемом AutoShortsController
 * - простую защиту от технических артефактов в идее (normalizeIdeaText)
 */
class AutoShortsGenerator
{
    private AutoShortsIntentService $intentService;
    private AutoShortsTemplateService $templateService;

    public function __construct()
    {
        $this->intentService   = new AutoShortsIntentService();
        $this->templateService = new AutoShortsTemplateService();
    }

    /**
     * Генерировать один вариант контента из идеи (формат для AutoShortsController).
     * 
     * @throws \RuntimeException если генерация не удалась
     */
    public function generateFromIdea(string $idea): array
    {
        $variants = $this->generateMultipleVariants($idea, 1);
        
        if (empty($variants) || !isset($variants[0])) {
            throw new \RuntimeException('Не удалось сгенерировать контент из идеи: ' . htmlspecialchars($idea));
        }
        
        return $variants[0];
    }

    /**
    * Сгенерировать несколько вариантов оформления для одной идеи.
    *
    * Возвращает массив структур:
    * [
    *   [
    *     'idea'   => string,
    *     'intent' => [
    *         'content_type'  => string,
    *         'mood'          => string,
    *         'visual_focus'  => string,
    *         'language'      => 'ru'|'en',
    *         'platform'      => 'shorts',
    *         'idea'          => string, // нормализованная
    *         'raw_idea'      => string, // исходная
    *     ],
    *     'content' => [
    *         'title'          => string,
    *         'description'    => string,
    *         'emoji'          => string,
    *         'tags'           => string[] ,
    *         'pinned_comment' => string,
    *         'angle'          => string,
    *     ],
    *   ],
    *   ...
    * ]
    */
    public function generateMultipleVariants(string $idea, int $count = 5): array
    {
        if (empty($idea) || !is_string($idea)) {
            throw new \InvalidArgumentException('Идея должна быть непустой строкой');
        }
        
        $originalIdea   = trim($idea);
        $normalizedIdea = $this->normalizeIdeaText($originalIdea);

        if ($normalizedIdea === '' || mb_strlen($normalizedIdea) < 3) {
            throw new \RuntimeException('Идея должна содержать как минимум 3 значащих символа после нормализации. Получено: "' . htmlspecialchars($originalIdea) . '"');
        }

        $count = max(1, min($count, 20));

        // Базовый intent: тип контента и настроение
        $baseIntent = $this->intentService->detectIntent($normalizedIdea);
        $language   = $this->detectLanguage($normalizedIdea);
        $visual     = $this->detectVisualFocus($normalizedIdea);

        $baseIntent['language']     = $language;
        $baseIntent['visual_focus'] = $visual;
        $baseIntent['platform']     = 'shorts';
        $baseIntent['idea']         = $normalizedIdea;
        $baseIntent['raw_idea']     = $originalIdea;

        $variants = [];
        $usedTitles = [];
        $usedDescriptions = [];

        for ($i = 0; $i < $count; $i++) {
            // Небольшая вариативность настроения для разных вариантов
            $intent = $this->tweakIntentForVariant($baseIntent, $i);

            $title       = $this->templateService->buildTitle($normalizedIdea, $intent);
            $description = $this->templateService->buildDescription($normalizedIdea, $intent);
            $tagsString  = $this->templateService->buildTags($normalizedIdea, $intent);
            $tags        = $this->splitTags($tagsString);

            // Простая защита от дубликатов заголовков/описаний внутри одной генерации
            if (in_array($title, $usedTitles, true)) {
                $title .= ' #' . ($i + 1);
            }
            if (in_array($description, $usedDescriptions, true)) {
                $description .= ' 🔁';
            }
            $usedTitles[]       = $title;
            $usedDescriptions[] = $description;

            $content = [
                'title'          => $title,
                'description'    => $description,
                'emoji'          => $this->buildEmojiForIntent($intent),
                'tags'           => $tags,
                'pinned_comment' => $this->buildPinnedComment($normalizedIdea, $intent),
                'angle'          => $this->buildAngleDescription($intent),
            ];

            $variants[] = [
                'idea'    => $normalizedIdea,
                'intent'  => $intent,
                'content' => $content,
                'variant_number' => $i + 1,
                'generated_at'   => date('Y-m-d H:i:s'),
            ];
        }

        return $variants;
    }

    /**
     * Нормализация текста идеи:
     * - убираем путь и расширение файла
     * - заменяем подчёркивания/дефисы на пробелы
     * - схлопываем пробелы
     */
    private function normalizeIdeaText(string $idea): string
    {
        $idea = trim($idea);
        if ($idea === '') {
            return '';
        }

        // убираем путь
        if (strpos($idea, '/') !== false || strpos($idea, '\\') !== false) {
            $idea = preg_replace('~^.*[\\\\/]~u', '', $idea);
        }

        // убираем расширение файла
        $idea = preg_replace('~\.[a-z0-9]{2,4}$~iu', '', $idea);

        // подчёркивания и дефисы -> пробел
        $idea = str_replace(['_', '-'], ' ', $idea);

        // убрать служебные символы по краям
        $idea = trim($idea, " \t\n\r\0\x0B\"'`()[]{}#@");

        // схлопнуть пробелы
        $idea = preg_replace('/\s+/u', ' ', $idea);

        return $idea;
    }

    private function detectLanguage(string $text): string
    {
        $hasLatin    = (bool)preg_match('/[a-z]/i', $text);
        $hasCyrillic = (bool)preg_match('/[а-яё]/iu', $text);

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
        if (preg_match('/ноч[ьи]|night|dark|moon|moonlight|ночной/i', $t)) {
            return 'night';
        }
        if (preg_match('/голос|voice|vocal|sing|поёт|пою/i', $t)) {
            return 'voice';
        }

        return 'default';
    }

    /**
     * Лёгкая модификация intent для разных вариантов (например, чередуем настроение).
     */
    private function tweakIntentForVariant(array $baseIntent, int $index): array
    {
        $intent = $baseIntent;

        // для части вариантов меняем настроение на более «эмоциональное» / «атмосферное»
        if ($baseIntent['mood'] === 'calm') {
            if ($index % 3 === 1) {
                $intent['mood'] = 'emotional';
            }
        } elseif ($baseIntent['mood'] === 'emotional') {
            if ($index % 3 === 1) {
                $intent['mood'] = 'calm';
            }
        }

        return $intent;
    }

    /**
     * Преобразовать строку тегов в массив.
     */
    private function splitTags(string $tags): array
    {
        $parts = preg_split('/\s*,\s*/u', $tags);
        $parts = array_filter($parts, static fn($t) => $t !== null && $t !== '');
        return array_values($parts);
    }

    private function buildEmojiForIntent(array $intent): string
    {
        $type = $intent['content_type'] ?? 'generic';
        $mood = $intent['mood'] ?? 'neutral';

        $pool = ['✨', '🎬', '🎵', '🎧', '🎥', '🎉', '🔥'];

        if ($type === 'dance') {
            $pool = ['💃', '🕺', '🎶', '🔥'];
        } elseif ($type === 'comedy') {
            $pool = ['😂', '🤣', '😜', '🤡'];
        } elseif ($type === 'aesthetic') {
            $pool = ['✨', '🌙', '💡', '🎨'];
        } elseif ($type === 'emotional') {
            $pool = ['😱', '😢', '❤️', '🥹'];
        }

        if ($mood === 'calm') {
            $pool[] = '🌙';
            $pool[] = '💤';
        }

        $pool = array_unique($pool);

        if (empty($pool)) {
            return '';
        }

        // 1–3 emoji в строке
        shuffle($pool);
        $take = rand(1, min(3, count($pool)));
        return implode(' ', array_slice($pool, 0, $take));
    }

    private function buildPinnedComment(string $idea, array $intent): string
    {
        $type = $intent['content_type'] ?? 'generic';

        switch ($type) {
            case 'dance':
                return 'Какой момент из танца зацепил сильнее всего? 💃🕺';
            case 'comedy':
                return 'С какого секунды ты начал(а) смеяться? 😂 Пиши в комментариях!';
            case 'aesthetic':
                return 'Если бы у этого момента была подпись — какой бы она была? 🎨';
            case 'emotional':
                return 'Опиши это видео одним словом в комментариях ❤️';
            default:
                return 'Досмотрел(а) до конца? Оставь любой смайл в комментариях, я всё читаю 👇';
        }
    }

    private function buildAngleDescription(array $intent): string
    {
        $type  = $intent['content_type']  ?? 'generic';
        $mood  = $intent['mood']          ?? 'neutral';
        $focus = $intent['visual_focus']  ?? 'default';

        return sprintf(
            'Тип: %s • Настроение: %s • Визуал: %s',
            $type,
            $mood,
            $focus
        );
    }
}

