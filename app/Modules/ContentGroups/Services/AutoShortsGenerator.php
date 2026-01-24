<?php

/**
 * AutoShortsGenerator - Автоматическая генерация контента для YouTube Shorts
 *
 * Принимает только базовую идею и генерирует полный набор элементов:
 * - title, description, emoji, tags, pinned comment
 * - с защитой от дубликатов и Shorts-оптимизацией
 */

namespace App\Modules\ContentGroups\Services;

class AutoShortsGenerator
{
    // Словари для анализа intent
    private const CONTENT_TYPES = [
        'vocal' => ['голос', 'вокал', 'поёт', 'пение', 'певец', 'певица', 'голосом', 'песня', 'пою'],
        'music' => ['музыка', 'мелодия', 'звук', 'аудио', 'трек', 'композиция', 'мелодия', 'песня', 'мотив'],
        'aesthetic' => ['неон', 'свет', 'красиво', 'эстетика', 'визуал', 'цвета', 'ярко', 'картинка'],
        'ambience' => ['атмосфера', 'настроение', 'спокойно', 'тихо', 'ночь', 'вечер', 'погружение', 'релакс']
    ];

    private const MOODS = [
        'calm' => ['спокойно', 'тихо', 'плавно', 'мягко', 'нежно', 'умиротворение'],
        'emotional' => ['эмоционально', 'чувства', 'душа', 'сердце', 'глубоко', 'трогательно'],
        'romantic' => ['романтично', 'любовь', 'нежность', 'чувственно', 'интимно'],
        'mysterious' => ['загадочно', 'тайна', 'мистика', 'непонятно', 'интрига', 'секрет']
    ];

    private const VISUAL_FOCUS = [
        'neon' => ['неон', 'свет', 'ярко', 'цвета', 'разноцветный', 'переливы'],
        'night' => ['ночь', 'темно', 'тень', 'луна', 'звёзды', 'тёмный'],
        'closeup' => ['близко', 'крупно', 'лицо', 'глаза', 'взгляд', 'детали'],
        'atmosphere' => ['атмосфера', 'окружение', 'пространство', 'воздух', 'погружение']
    ];

    // Шаблоны генерации
    private const TITLE_TEMPLATES = [
        'vocal' => [
            '{visual} + {emotion} {content}',
            '{emotion} {content} {visual}',
            'Когда {content} {emotion}',
            '{content} который {emotion}',
            '{visual} {content} {emotion}',
            'Этот {content} просто {emotion}',
            'Не могу перестать слушать {content}',
            '{visual} делает {content} {emotion}'
        ],
        'music' => [
            '{visual} {content} {emotion}',
            '{emotion} {content} в {visual}',
            '{content} которое {emotion}',
            'Просто {content} и {visual}',
            '{emotion} мелодия {visual}',
            '{content} {visual} {emotion}'
        ],
        'aesthetic' => [
            '{visual} {content} {emotion}',
            '{emotion} {visual} {content}',
            'Когда {visual} {emotion}',
            '{content} в {visual} {emotion}',
            'Это {visual} {content}',
            '{emotion} {visual} момент'
        ],
        'ambience' => [
            '{visual} {content} {emotion}',
            '{emotion} {visual} атмосфера',
            'Погружение в {visual} {content}',
            '{content} {visual} {emotion}',
            'Чувствую {emotion} {visual}',
            '{visual} {content} внутри'
        ]
    ];

    private const DESCRIPTION_TEMPLATES = [
        'question' => [
            '{emotion_emoji} {question} {cta_emoji}',
            'Как тебе {content}? {emotion_emoji}',
            'Залип? {emotion_emoji}',
            'Стоит продолжать? {cta_emoji}',
            '{question} {emotion_emoji}',
            'Досмотрел до конца? {cta_emoji}'
        ],
        'emotional' => [
            'Ничего лишнего. Просто {emotion} {emotion_emoji}',
            'Чувствую {emotion} {emotion_emoji}',
            '{content} {visual} {emotion_emoji}',
            'Момент {emotion} {emotion_emoji}',
            'Это {emotion} {content} {emotion_emoji}'
        ],
        'mysterious' => [
            'Что-то особенное {emotion_emoji}',
            'Загадочная {emotion} {emotion_emoji}',
            'Не могу объяснить {emotion_emoji}',
            'Просто посмотри {cta_emoji}',
            'Особенная {emotion} {emotion_emoji}'
        ]
    ];

    // Emoji по настроениям
    private const EMOJI_SETS = [
        'calm' => ['✨', '🌙', '💫', '🌌', '🌠', '🌸'],
        'emotional' => ['💖', '🫶', '😢', '🥺', '💕', '❤️'],
        'romantic' => ['💕', '❤️', '💫', '🌹', '🌙', '🫶'],
        'mysterious' => ['🌌', '👁️', '🌑', '🔮', '🌙', '❓']
    ];

    // Теги по типам контента
    private const TAG_SETS = [
        'vocal' => ['#Shorts', '#Вокал', '#Голос', '#Пение', '#Музыка'],
        'music' => ['#Shorts', '#Музыка', '#Мелодия', '#Звук', '#Аудио'],
        'aesthetic' => ['#Shorts', '#Красиво', '#Эстетика', '#Визуал', '#Арт'],
        'ambience' => ['#Shorts', '#Атмосфера', '#Настроение', '#Спокойно', '#Релакс']
    ];

    // Вопросы для вовлечённости
    private const ENGAGEMENT_QUESTIONS = [
        'vocal' => [
            'Как тебе голос?',
            'Залип на голос?',
            'Хочешь ещё такого вокала?',
            'Голос зацепил?',
            'Стоит продолжать петь?'
        ],
        'music' => [
            'Как тебе мелодия?',
            'Музыка зацепила?',
            'Хочешь ещё такой музыки?',
            'Залип на звук?',
            'Стоит продолжать?'
        ],
        'aesthetic' => [
            'Как тебе визуал?',
            'Красиво, да?',
            'Залип на картинку?',
            'Хочешь ещё такого?',
            'Стоит продолжать снимать?'
        ],
        'ambience' => [
            'Чувствуешь атмосферу?',
            'Залип на настроение?',
            'Как тебе погружение?',
            'Хочешь ещё такой атмосферы?',
            'Стоит продолжать?'
        ]
    ];

    // История генераций для защиты от дубликатов
    private static array $generationHistory = [];

    /**
     * Генерировать полный Shorts контент из одной идеи
     */
    public function generateFromIdea(string $idea): array
    {
        try {
            error_log('AutoShortsGenerator::generateFromIdea: Starting generation for idea: "' . $idea . '"');

            // 1. Анализ intent
            error_log('AutoShortsGenerator::generateFromIdea: Analyzing intent');
            $intent = $this->analyzeIntent($idea);
            error_log('AutoShortsGenerator::generateFromIdea: Intent analyzed - ' . json_encode($intent));

            // 2. Генерация смысловых углов
            error_log('AutoShortsGenerator::generateFromIdea: Generating content angles');
            $angles = $this->generateContentAngles($intent, $idea);
            error_log('AutoShortsGenerator::generateFromIdea: Angles generated - ' . count($angles) . ' angles');

            // 3. Генерация контента
            error_log('AutoShortsGenerator::generateFromIdea: Generating content');
            $content = $this->generateContent($intent, $angles);
            error_log('AutoShortsGenerator::generateFromIdea: Content generated successfully');

            // 4. Проверка на дубликаты
            error_log('AutoShortsGenerator::generateFromIdea: Ensuring uniqueness');
            $content = $this->ensureUniqueness($content);
            error_log('AutoShortsGenerator::generateFromIdea: Uniqueness ensured');

            // 5. Сохранение в истории
            error_log('AutoShortsGenerator::generateFromIdea: Adding to history');
            $this->addToHistory($content);

            $result = [
                'idea' => $idea,
                'intent' => $intent,
                'content' => $content,
                'generated_at' => date('Y-m-d H:i:s')
            ];

            error_log('AutoShortsGenerator::generateFromIdea: Generation completed successfully');
            return $result;

        } catch (Exception $e) {
            error_log('AutoShortsGenerator::generateFromIdea: Exception: ' . $e->getMessage());
            error_log('AutoShortsGenerator::generateFromIdea: Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Анализ intent из текста идеи
     */
    private function analyzeIntent(string $idea): array
    {
        $idea = mb_strtolower($idea);

        // Определение типа контента
        $contentType = 'vocal'; // дефолт
        $maxWeight = 0;

        foreach (self::CONTENT_TYPES as $type => $keywords) {
            $weight = 0;
            foreach ($keywords as $keyword) {
                if (strpos($idea, $keyword) !== false) {
                    $weight += 1;
                }
            }
            if ($weight > $maxWeight) {
                $maxWeight = $weight;
                $contentType = $type;
            }
        }

        // Определение настроения
        $mood = 'calm'; // дефолт
        $maxWeight = 0;

        foreach (self::MOODS as $moodType => $keywords) {
            $weight = 0;
            foreach ($keywords as $keyword) {
                if (strpos($idea, $keyword) !== false) {
                    $weight += 1;
                }
            }
            if ($weight > $maxWeight) {
                $maxWeight = $weight;
                $mood = $moodType;
            }
        }

        // Определение визуального фокуса
        $visualFocus = 'neon'; // дефолт
        $maxWeight = 0;

        foreach (self::VISUAL_FOCUS as $focus => $keywords) {
            $weight = 0;
            foreach ($keywords as $keyword) {
                if (strpos($idea, $keyword) !== false) {
                    $weight += 1;
                }
            }
            if ($weight > $maxWeight) {
                $maxWeight = $weight;
                $visualFocus = $focus;
            }
        }

        return [
            'content_type' => $contentType,
            'mood' => $mood,
            'visual_focus' => $visualFocus,
            'language' => 'ru',
            'platform' => 'shorts'
        ];
    }

    /**
     * Генерация смысловых углов для разнообразия
     */
    private function generateContentAngles(array $intent, string $idea): array
    {
        $angles = [];

        // Разные углы в зависимости от типа контента
        switch ($intent['content_type']) {
            case 'vocal':
                $angles = [
                    'голос', 'вокал', 'пение', 'тембр', 'интонация',
                    'эмоция_голоса', 'чистота_звука', 'манера_пения',
                    'внутренний_мир', 'чувства_певца'
                ];
                break;
            case 'music':
                $angles = [
                    'мелодия', 'ритм', 'звук', 'композиция', 'инструменты',
                    'музыкальное_настроение', 'звуковое_пространство',
                    'музыкальная_ткань', 'звучание', 'музыкальная_атмосфера'
                ];
                break;
            case 'aesthetic':
                $angles = [
                    'визуал', 'цвета', 'свет', 'композиция', 'эстетика',
                    'визуальная_гармония', 'цветовые_переходы',
                    'световые_эффекты', 'визуальный_ритм', 'эстетическое_наслаждение'
                ];
                break;
            case 'ambience':
                $angles = [
                    'атмосфера', 'настроение', 'погружение', 'окружение',
                    'эмоциональный_фон', 'пространственное_ощущение',
                    'атмосферное_погружение', 'эмоциональная_аура',
                    'окружающая_среда', 'атмосферное_настроение'
                ];
                break;
        }

        // Перемешиваем и выбираем 6-8 углов
        shuffle($angles);
        return array_slice($angles, 0, rand(6, 8));
    }

    /**
     * Генерация полного контента
     */
    private function generateContent(array $intent, array $angles): array
    {
        try {
            $angle = $angles[array_rand($angles)]; // Случайный угол
            error_log("AutoShortsGenerator::generateContent: Selected angle: {$angle}");

            // Генерация названия
            error_log("AutoShortsGenerator::generateContent: Generating title...");
            $title = $this->generateTitle($intent, $angle);
            error_log("AutoShortsGenerator::generateContent: Title generated: '{$title}'");

            // Генерация описания
            error_log("AutoShortsGenerator::generateContent: Generating description...");
            $description = $this->generateDescription($intent);
            error_log("AutoShortsGenerator::generateContent: Description generated: '{$description}'");

            // Генерация emoji
            error_log("AutoShortsGenerator::generateContent: Generating emoji...");
            $emoji = $this->generateEmoji($intent);
            error_log("AutoShortsGenerator::generateContent: Emoji generated: '{$emoji}'");

            // Генерация тегов
            error_log("AutoShortsGenerator::generateContent: Generating tags...");
            $tags = $this->generateTags($intent);
            error_log("AutoShortsGenerator::generateContent: Tags generated: " . json_encode($tags));

            // Генерация закрепленного комментария
            error_log("AutoShortsGenerator::generateContent: Generating pinned comment...");
            $pinnedComment = $this->generatePinnedComment($intent);
            error_log("AutoShortsGenerator::generateContent: Pinned comment generated: '{$pinnedComment}'");

            $result = [
                'title' => $title,
                'description' => $description,
                'emoji' => $emoji,
                'tags' => $tags,
                'pinned_comment' => $pinnedComment,
                'angle' => $angle
            ];

            error_log("AutoShortsGenerator::generateContent: Content generation completed successfully");
            return $result;

        } catch (Exception $e) {
            error_log("AutoShortsGenerator::generateContent: Exception: " . $e->getMessage());
            error_log("AutoShortsGenerator::generateContent: Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Генерация уникального названия
     */
    private function generateTitle(array $intent, string $angle): string
    {
        try {
            $contentType = $intent['content_type'] ?? 'vocal';
            $templates = self::TITLE_TEMPLATES[$contentType] ?? self::TITLE_TEMPLATES['vocal'];

            error_log("AutoShortsGenerator::generateTitle: Content type: {$contentType}, available templates: " . count($templates));

            // Замены для шаблонов
            $replacements = [
                '{content}' => $this->getContentWord($contentType),
                '{emotion}' => $this->getEmotionWord($intent['mood'] ?? 'calm'),
                '{visual}' => $this->getVisualWord($intent['visual_focus'] ?? 'neon'),
                '{angle}' => $angle
            ];

            error_log("AutoShortsGenerator::generateTitle: Replacements: " . json_encode($replacements));

            // Выбираем случайный шаблон
            $template = $templates[array_rand($templates)];
            error_log("AutoShortsGenerator::generateTitle: Selected template: '{$template}'");

            // Применяем замены
            $title = str_replace(array_keys($replacements), array_values($replacements), $template);
            error_log("AutoShortsGenerator::generateTitle: After replacements: '{$title}'");

            // Ограничиваем длину
            if (mb_strlen($title) > 80) {
                $title = mb_substr($title, 0, 77) . '...';
            }

            error_log("AutoShortsGenerator::generateTitle: Final title: '{$title}'");
            return ucfirst($title);

        } catch (Exception $e) {
            error_log("AutoShortsGenerator::generateTitle: Exception: " . $e->getMessage());
            return "Автоматически сгенерированное название"; // fallback
        }
    }

    /**
     * Генерация описания
     */
    private function generateDescription(array $intent): string
    {
        try {
            $descType = ['question', 'emotional', 'mysterious'][array_rand(['question', 'emotional', 'mysterious'])];
            $templates = self::DESCRIPTION_TEMPLATES[$descType];

            error_log("AutoShortsGenerator::generateDescription: Desc type: {$descType}, available templates: " . count($templates));

            $template = $templates[array_rand($templates)];
            error_log("AutoShortsGenerator::generateDescription: Selected template: '{$template}'");

            $replacements = [
                '{emotion}' => $this->getEmotionWord($intent['mood'] ?? 'calm'),
                '{content}' => $this->getContentWord($intent['content_type'] ?? 'vocal'),
                '{visual}' => $this->getVisualWord($intent['visual_focus'] ?? 'neon'),
                '{question}' => $this->getQuestionWord($intent['content_type'] ?? 'vocal'),
                '{emotion_emoji}' => $this->getRandomEmoji($intent['mood'] ?? 'calm', 1),
                '{cta_emoji}' => ['▶️', '👆', '💬', '❤️'][array_rand(['▶️', '👆', '💬', '❤️'])]
            ];

            error_log("AutoShortsGenerator::generateDescription: Replacements: " . json_encode($replacements));

            $result = str_replace(array_keys($replacements), array_values($replacements), $template);
            error_log("AutoShortsGenerator::generateDescription: Final description: '{$result}'");

            return $result;

        } catch (Exception $e) {
            error_log("AutoShortsGenerator::generateDescription: Exception: " . $e->getMessage());
            return "Автоматически сгенерированное описание"; // fallback
        }
    }

    /**
     * Генерация emoji
     */
    private function generateEmoji(array $intent): string
    {
        // 0-2 emoji в зависимости от настроения
        $count = rand(0, 2);
        if ($count === 0) return '';

        return $this->getRandomEmoji($intent['mood'], $count);
    }

    /**
     * Генерация тегов
     */
    private function generateTags(array $intent): array
    {
        $baseTags = self::TAG_SETS[$intent['content_type']] ?? self::TAG_SETS['vocal'];

        // Добавляем mood-специфичные теги
        $moodTags = [
            'calm' => ['#Спокойно', '#Релакс'],
            'emotional' => ['#Эмоции', '#Чувства'],
            'romantic' => ['#Романтика', '#Любовь'],
            'mysterious' => ['#Загадка', '#Мистика']
        ];

        $tags = array_merge($baseTags, $moodTags[$intent['mood']] ?? []);

        // Перемешиваем и выбираем 3-5 тегов
        shuffle($tags);
        return array_slice($tags, 0, rand(3, 5));
    }

    /**
     * Генерация закрепленного комментария
     */
    private function generatePinnedComment(array $intent): string
    {
        $questions = self::ENGAGEMENT_QUESTIONS[$intent['content_type']] ?? self::ENGAGEMENT_QUESTIONS['vocal'];
        return $questions[array_rand($questions)];
    }

    /**
     * Проверка на дубликаты и обеспечение уникальности
     */
    private function ensureUniqueness(array $content): array
    {
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            if (!$this->isDuplicate($content)) {
                return $content;
            }

            // Перегенерация
            $content['title'] = $this->regenerateTitle($content);
            $content['description'] = $this->regenerateDescription($content);
            $content['emoji'] = $this->regenerateEmoji($content);

            $attempt++;
        }

        // Если не удалось сгенерировать уникальный, возвращаем как есть
        return $content;
    }

    /**
     * Проверка на дубликат
     */
    private function isDuplicate(array $content): bool
    {
        foreach (self::$generationHistory as $previous) {
            // Проверяем совпадение первых слов в названии
            $titleWords1 = explode(' ', mb_strtolower($previous['title']));
            $titleWords2 = explode(' ', mb_strtolower($content['title']));

            if (!empty($titleWords1) && !empty($titleWords2) &&
                $titleWords1[0] === $titleWords2[0]) {
                return true;
            }

            // Проверяем полное совпадение описания
            if ($previous['description'] === $content['description']) {
                return true;
            }
        }

        return false;
    }

    // Вспомогательные методы

    private function getContentWord(string $contentType): string
    {
        $words = [
            'vocal' => ['голос', 'вокал', 'пение', 'звук'],
            'music' => ['мелодия', 'музыка', 'композиция', 'звук'],
            'aesthetic' => ['визуал', 'красота', 'эстетика', 'свет'],
            'ambience' => ['атмосфера', 'настроение', 'погружение', 'ощущение']
        ];
        $list = $words[$contentType] ?? $words['vocal'];
        return $list[array_rand($list)];
    }

    private function getEmotionWord(string $mood): string
    {
        $words = [
            'calm' => ['спокойный', 'мягкий', 'нежный', 'умиротворяющий'],
            'emotional' => ['эмоциональный', 'трогательный', 'глубокий', 'душевный'],
            'romantic' => ['романтический', 'нежный', 'чувственный', 'лирический'],
            'mysterious' => ['загадочный', 'мистический', 'таинственный', 'непонятный']
        ];
        $list = $words[$mood] ?? $words['calm'];
        return $list[array_rand($list)];
    }

    private function getVisualWord(string $visualFocus): string
    {
        $words = [
            'neon' => ['неоновый', 'яркий', 'цветной', 'светящийся'],
            'night' => ['ночной', 'тёмный', 'лунный', 'звёздный'],
            'closeup' => ['крупный', 'близкий', 'детальный', 'интимный'],
            'atmosphere' => ['атмосферный', 'пространственный', 'объёмный', 'погружающий']
        ];
        $list = $words[$visualFocus] ?? $words['neon'];
        return $list[array_rand($list)];
    }

    private function getQuestionWord(string $contentType): string
    {
        $questions = [
            'vocal' => ['Как голос?', 'Залип на пение?', 'Вокал зацепил?'],
            'music' => ['Мелодия хороша?', 'Музыка цепляет?', 'Звук нравится?'],
            'aesthetic' => ['Визуал красивый?', 'Картинка зацепила?', 'Эстетика понравилась?'],
            'ambience' => ['Атмосфера чувствуется?', 'Настроение передалось?', 'Погружение удалось?']
        ];
        $list = $questions[$contentType] ?? $questions['vocal'];
        return $list[array_rand($list)];
    }

    private function getRandomEmoji(string $mood, int $count = 1): string
    {
        $emojis = self::EMOJI_SETS[$mood] ?? self::EMOJI_SETS['calm'];
        shuffle($emojis);
        return implode('', array_slice($emojis, 0, $count));
    }

    private function regenerateTitle(array $content): string
    {
        // Простая перегенерация - добавляем вариацию
        $variations = ['просто', 'очень', 'такой', 'этот', 'настоящий'];
        $variation = $variations[array_rand($variations)];

        return $variation . ' ' . lcfirst($content['title']);
    }

    private function regenerateDescription(array $content): string
    {
        // Меняем тип описания
        $types = ['question', 'emotional', 'mysterious'];
        $newType = $types[array_rand($types)];

        return self::DESCRIPTION_TEMPLATES[$newType][array_rand(self::DESCRIPTION_TEMPLATES[$newType])];
    }

    private function regenerateEmoji(array $content): string
    {
        return rand(0, 1) ? $this->getRandomEmoji('calm', rand(1, 2)) : '';
    }

    private function addToHistory(array $content): void
    {
        self::$generationHistory[] = $content;

        // Ограничиваем историю последними 100 генерациями
        if (count(self::$generationHistory) > 100) {
            array_shift(self::$generationHistory);
        }
    }
}