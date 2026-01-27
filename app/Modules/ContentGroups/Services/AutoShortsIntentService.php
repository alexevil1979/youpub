<?php

namespace App\Modules\ContentGroups\Services;

/**
 * Отвечает только за разбор идеи и определение intent/контекста.
 * Вырезан из монолитного AutoShortsGenerator для разделения ответственности.
 */
class AutoShortsIntentService
{
    public function detectIntent(string $idea): array
    {
        $normalized = mb_strtolower($idea);

        // Простейший, но изолированный анализ intent: в будущем сюда можно добавить ML/LLM
        $isEmotional = (bool)preg_match('/(пла[чу]|слез|сердце|душа|трогательн|heart|cry|emotional)/u', $normalized);
        $isAesthetic = (bool)preg_match('/(неон|эстетик|aesthetic|neon|beautiful|красив)/u', $normalized);
        $isDance     = (bool)preg_match('/(танц|dance|choreo|хореограф)/ui', $normalized);
        $isComedy    = (bool)preg_match('/(шутк|прикол|юмор|funny|lol|rofl)/ui', $normalized);

        $contentType = 'generic';
        if ($isDance) {
            $contentType = 'dance';
        } elseif ($isComedy) {
            $contentType = 'comedy';
        } elseif ($isAesthetic) {
            $contentType = 'aesthetic';
        } elseif ($isEmotional) {
            $contentType = 'emotional';
        }

        $mood = $isEmotional ? 'emotional' : ($isAesthetic ? 'calm' : 'neutral');

        return [
            'content_type' => $contentType,
            'mood' => $mood,
            'raw' => $idea,
        ];
    }
}

