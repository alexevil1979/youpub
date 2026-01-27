<?php

namespace App\Modules\ContentGroups\Services;

/**
 * Отвечает за генерацию конкретных текстов (title/description/tags)
 * на основе intent и идеи. Вынесено из монолитного AutoShortsGenerator.
 */
class AutoShortsTemplateService
{
    public function buildTitle(string $idea, array $intent): string
    {
        $type = $intent['content_type'] ?? 'generic';
        $mood = $intent['mood'] ?? 'neutral';

        $templatesByType = [
            'dance' => [
                "Этот танец под \"{idea}\" просто разносит",
                "\"{idea}\" — тот самый момент, когда тело двигается само",
                "Когда бит падает, а ты уже танцуешь под \"{idea}\"",
            ],
            'comedy' => [
                "Это самый смешной момент с \"{idea}\"",
                "\"{idea}\" — шорт, над которым сложно не улыбнуться",
                "Если тебе нужно поднять настроение — просто включи \"{idea}\"",
            ],
            'aesthetic' => [
                "Неоновая эстетика под \"{idea}\"",
                "\"{idea}\" в картинках: чистая визуальная магия",
                "Когда хочется просто смотреть на красивое — \"{idea}\"",
            ],
            'emotional' => [
                "Когда музыка говорит за чувства: \"{idea}\"",
                "\"{idea}\" — тот момент, от которого мурашки",
                "Шорт, который попадает прямо в сердце: \"{idea}\"",
            ],
            'generic' => [
                "\"{idea}\" — тот самый момент, который хочется пересматривать",
                "Короткий шорт по идее \"{idea}\", который цепляет",
                "Если пролистаешь мимо \"{idea}\", можешь потерять лучший момент ленты",
            ],
        ];

        $pool = $templatesByType[$type] ?? $templatesByType['generic'];

        // Лёгкая адаптация под настроение
        if ($type === 'generic' && $mood === 'calm') {
            $pool[] = "Спокойный короткий момент под \"{idea}\"";
        }

        $pattern = $pool[array_rand($pool)];
        $title = str_replace('{idea}', $idea, $pattern);

        return $this->truncate($title, 95);
    }

    public function buildDescription(string $idea, array $intent): string
    {
        $type = $intent['content_type'] ?? 'generic';

        $base = "Короткий момент под идею \"{$idea}\".\n";

        $extrasByType = [
            'dance' => [
                "Танец, который хочется пересматривать на повторе. Сохраняй, чтобы не потерять.\n",
                "Если ноги сами в такт — значит, шорт удался. Танцуем под \"{$idea}\".\n",
            ],
            'comedy' => [
                "Если улыбнулся хотя бы раз — значит, не зря снимали. Отправь другу, чтобы тоже посмеялся.\n",
                "Короткий шорт, который лечит от плохого настроения. \"{$idea}\" в одном кадре.\n",
            ],
            'aesthetic' => [
                "Чистая эстетика и визуальное наслаждение. Включай в избранные шорты для настроения.\n",
                "Когда хочется просто смотреть и ни о чём не думать — этот шорт именно про это.\n",
            ],
            'emotional' => [
                "Иногда одной короткой сценой можно сказать больше, чем длинным видео. Сохрани этот момент.\n",
                "Если внутри стало теплее — значит, \"{$idea}\" сработала.\n",
            ],
            'generic' => [
                "Шорт, который идеально смотрится в ленте — коротко, понятно и до конца.\n",
                "Небольшой фрагмент по идее \"{$idea}\", который стоит посмотреть до конца.\n",
            ],
        ];

        $pool = $extrasByType[$type] ?? $extrasByType['generic'];
        $extra = $pool[array_rand($pool)];

        $cta = "\nЕсли зашло — поставь лайк и подпишись, чтобы не пропустить следующие выпуски.";

        return $this->truncate($base . $extra . $cta, 4500); // запас до лимита YouTube
    }

    public function buildTags(string $idea, array $intent): string
    {
        $type = $intent['content_type'] ?? 'generic';

        $baseTags = ['shorts', 'short', 'viral', 'trending'];

        switch ($type) {
            case 'dance':
                $spec = ['dance', 'танцы', 'dancechallenge'];
                break;
            case 'comedy':
                $spec = ['comedy', 'funny', 'приколы'];
                break;
            case 'aesthetic':
                $spec = ['aesthetic', 'neon', 'vibes'];
                break;
            case 'emotional':
                $spec = ['emotional', 'feelings', 'deep'];
                break;
            default:
                $spec = [];
                break;
        }

        // Пара тегов из идеи (слова длиной 3+)
        $ideaWords = preg_split('/\s+/u', mb_strtolower($idea));
        $ideaTags = array_slice(
            array_values(
                array_filter($ideaWords, static fn(string $w) => mb_strlen($w) >= 3)
            ),
            0,
            5
        );

        $all = array_unique(array_filter(array_merge($baseTags, $spec, $ideaTags)));

        return implode(', ', $all);
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 1) . '…';
    }
}

