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

    private const CONTENT_TYPES_EN = [
        'vocal' => ['voice', 'vocal', 'vocals', 'sing', 'singing', 'singer', 'song'],
        'music' => ['music', 'melody', 'track', 'beat', 'audio', 'sound'],
        'aesthetic' => ['neon', 'aesthetic', 'visual', 'colors', 'beautiful', 'pretty'],
        'ambience' => ['ambience', 'atmosphere', 'mood', 'vibe', 'calm', 'night', 'relax']
    ];

    private const MOODS = [
        'calm' => ['спокойно', 'тихо', 'плавно', 'мягко', 'нежно', 'умиротворение'],
        'emotional' => ['эмоционально', 'чувства', 'душа', 'сердце', 'глубоко', 'трогательно'],
        'romantic' => ['романтично', 'любовь', 'нежность', 'чувственно', 'интимно'],
        'mysterious' => ['загадочно', 'тайна', 'мистика', 'непонятно', 'интрига', 'секрет']
    ];

    private const MOODS_EN = [
        'calm' => ['calm', 'soft', 'gentle', 'smooth', 'chill'],
        'emotional' => ['emotional', 'touching', 'deep', 'heartfelt'],
        'romantic' => ['romantic', 'love', 'tender', 'sweet'],
        'mysterious' => ['mysterious', 'secret', 'enigmatic', 'intriguing']
    ];

    private const VISUAL_FOCUS = [
        'neon' => ['неон', 'свет', 'ярко', 'цвета', 'разноцветный', 'переливы'],
        'night' => ['ночь', 'темно', 'тень', 'луна', 'звёзды', 'тёмный'],
        'closeup' => ['близко', 'крупно', 'лицо', 'глаза', 'взгляд', 'детали'],
        'atmosphere' => ['атмосфера', 'окружение', 'пространство', 'воздух', 'погружение']
    ];

    private const VISUAL_FOCUS_EN = [
        'neon' => ['neon', 'glow', 'bright', 'colors', 'lights'],
        'night' => ['night', 'dark', 'moon', 'stars', 'shadow'],
        'closeup' => ['closeup', 'close', 'face', 'eyes', 'details'],
        'atmosphere' => ['atmosphere', 'space', 'ambient', 'surroundings']
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

    private const TITLE_TEMPLATES_EN = [
        'vocal' => [
            '{visual} {content} feels {emotion}',
            '{emotion} {content} in {visual}',
            'This {content} is so {emotion}',
            'Can’t stop listening to this {content}',
            'She’s SO FLEXIBLE!',
            'Who did it BEST?'
        ],
        'music' => [
            '{emotion} {content} with {visual}',
            'This {content} hits different',
            '{visual} {content} vibes',
            'Who did it BEST?'
        ],
        'aesthetic' => [
            '{visual} {content} moment',
            'So {emotion} in this {visual} scene',
            'Who did it BEST?',
            'She’s SO FLEXIBLE!'
        ],
        'ambience' => [
            '{emotion} {visual} atmosphere',
            'Lost in the {visual} {content}',
            'Who did it BEST?'
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

    private const DESCRIPTION_TEMPLATES_EN = [
        'question' => [
            '{emotion_emoji} {question} {cta_emoji}',
            'Did you feel that? {emotion_emoji}',
            'Who did it BEST? {cta_emoji}',
            'Would you watch again? {emotion_emoji}'
        ],
        'emotional' => [
            'Nothing extra. Just {emotion} vibes {emotion_emoji}',
            'This {content} feels {emotion} {emotion_emoji}',
            'So {emotion}. Just watch {emotion_emoji}'
        ],
        'mysterious' => [
            'Something special here {emotion_emoji}',
            'Can’t explain it {emotion_emoji}',
            'Just watch {cta_emoji}'
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

    private const TAG_SETS_EN = [
        'vocal' => ['#Shorts', '#Singing', '#Vocal', '#Voice', '#Music'],
        'music' => ['#Shorts', '#Music', '#Melody', '#Sound', '#Audio'],
        'aesthetic' => ['#Shorts', '#Aesthetic', '#Visual', '#Beautiful', '#Art'],
        'ambience' => ['#Shorts', '#Atmosphere', '#Mood', '#Calm', '#Relax']
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

    private const ENGAGEMENT_QUESTIONS_EN = [
        'vocal' => [
            'How is the voice?',
            'Did the vocals hook you?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'music' => [
            'How is the melody?',
            'This track hits?',
            'Want more like this?',
            'Who did it BEST?'
        ],
        'aesthetic' => [
            'How’s the visual?',
            'Does this look amazing?',
            'Want more like this?'
        ],
        'ambience' => [
            'Feel the atmosphere?',
            'Do you like the vibe?',
            'Want more like this?'
        ]
    ];

    // История генераций для защиты от дубликатов
    private static array $generationHistory = [];

    /**
     * Генерировать полный Shorts контент из одной идеи
     */
    /**
     * Генерация одного варианта контента (legacy method)
     */
    public function generateFromIdea(string $idea): array
    {
        $variants = $this->generateMultipleVariants($idea, 1);
        return $variants[0] ?? [];
    }

    /**
     * Генерация 20 различных вариантов оформления видео
     */
    public function generateMultipleVariants(string $idea, int $count = 20): array
    {
        try {
            error_log('AutoShortsGenerator::generateMultipleVariants: Starting generation for idea: "' . $idea . '" with ' . $count . ' variants');

            // 1. Анализ intent
            error_log('AutoShortsGenerator::generateMultipleVariants: Analyzing intent');
            $intent = $this->analyzeIntent($idea);
            error_log('AutoShortsGenerator::generateMultipleVariants: Intent analyzed - ' . json_encode($intent));

            // 2. Генерация смысловых углов
            error_log('AutoShortsGenerator::generateMultipleVariants: Generating content angles');
            $angles = $this->generateContentAngles($intent, $idea);
            error_log('AutoShortsGenerator::generateMultipleVariants: Angles generated - ' . count($angles) . ' angles');

            $variants = [];
            $usedTitles = [];
            $usedDescriptions = [];

            // 3. Генерация множества вариантов
            for ($i = 0; $i < $count; $i++) {
                error_log('AutoShortsGenerator::generateMultipleVariants: Generating variant ' . ($i + 1));

                // Создаем уникальный вариант с разными параметрами
                $variantIntent = $this->modifyIntentForVariant($intent, $i);
                $variantAngles = $this->selectAnglesForVariant($angles, $i);

                // Генерируем контент для этого варианта
                $content = $this->generateContent($variantIntent, $variantAngles);

                // Убеждаемся в уникальности
                $content = $this->ensureVariantUniqueness($content, $usedTitles, $usedDescriptions);

                // Добавляем в историю для защиты от глобальных дубликатов
                $this->addToHistory($content);

                $variant = [
                    'idea' => $idea,
                    'intent' => $variantIntent,
                    'content' => $content,
                    'variant_number' => $i + 1,
                    'generated_at' => date('Y-m-d H:i:s')
                ];

                $variants[] = $variant;

                // Сохраняем использованные заголовки и описания для уникальности
                if (isset($content['title'])) {
                    $usedTitles[] = $content['title'];
                }
                if (isset($content['description'])) {
                    $usedDescriptions[] = $content['description'];
                }
            }

            error_log('AutoShortsGenerator::generateMultipleVariants: Generated ' . count($variants) . ' variants successfully');
            return $variants;

        } catch (Exception $e) {
            error_log('AutoShortsGenerator::generateMultipleVariants: Exception: ' . $e->getMessage());
            error_log('AutoShortsGenerator::generateMultipleVariants: Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Анализ intent из текста идеи
     */
    private function analyzeIntent(string $idea): array
    {
        $language = $this->detectLanguage($idea);
        $idea = mb_strtolower($idea);

        // Определение типа контента
        $contentType = 'vocal'; // дефолт
        $maxWeight = 0;

        $contentTypes = $language === 'en' ? self::CONTENT_TYPES_EN : self::CONTENT_TYPES;
        foreach ($contentTypes as $type => $keywords) {
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

        $moods = $language === 'en' ? self::MOODS_EN : self::MOODS;
        foreach ($moods as $moodType => $keywords) {
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

        $visuals = $language === 'en' ? self::VISUAL_FOCUS_EN : self::VISUAL_FOCUS;
        foreach ($visuals as $focus => $keywords) {
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
            'language' => $language,
            'platform' => 'shorts'
        ];
    }

    private function detectLanguage(string $idea): string
    {
        $hasLatin = (bool)preg_match('/[a-z]/i', $idea);
        $hasCyrillic = (bool)preg_match('/[а-яё]/iu', $idea);
        if ($hasLatin && !$hasCyrillic) {
            return 'en';
        }
        return 'ru';
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
     * Модификация интента для варианта (для разнообразия)
     */
    private function modifyIntentForVariant(array $baseIntent, int $variantIndex): array
    {
        $intent = $baseIntent;

        // Циклически меняем настроение для разнообразия
        $moods = ['calm', 'emotional', 'atmospheric', 'intense', 'dreamy'];
        $intent['mood'] = $moods[$variantIndex % count($moods)];

        // Циклически меняем визуальный фокус
        $visualFocuses = ['neon', 'lights', 'shadows', 'colors', 'silhouette'];
        $intent['visual_focus'] = $visualFocuses[$variantIndex % count($visualFocuses)];

        return $intent;
    }

    /**
     * Выбор углов для варианта
     */
    private function selectAnglesForVariant(array $allAngles, int $variantIndex): array
    {
        // Для каждого варианта выбираем разные комбинации углов
        $angleCount = count($allAngles);
        $startIndex = $variantIndex * 3 % $angleCount; // Сдвиг на 3 угла для каждого варианта
        $selectedCount = rand(4, 6); // 4-6 углов на вариант

        $selectedAngles = [];
        for ($i = 0; $i < $selectedCount; $i++) {
            $index = ($startIndex + $i) % $angleCount;
            $selectedAngles[] = $allAngles[$index];
        }

        return $selectedAngles;
    }

    /**
     * Обеспечение уникальности варианта внутри батча
     */
    private function ensureVariantUniqueness(array $content, array &$usedTitles, array &$usedDescriptions): array
    {
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $isUnique = true;

            // Проверяем уникальность заголовка
            if (isset($content['title']) && in_array($content['title'], $usedTitles)) {
                // Регенерируем заголовок
                $content['title'] = $this->generateTitle(['content_type' => 'vocal', 'mood' => 'calm'], 'альтернативный_угол');
                $isUnique = false;
            }

            // Проверяем уникальность описания
            if (isset($content['description']) && in_array($content['description'], $usedDescriptions)) {
                // Регенерируем описание
                $content['description'] = $this->generateDescription(['content_type' => 'vocal', 'mood' => 'calm']);
                $isUnique = false;
            }

            if ($isUnique) {
                break;
            }

            $attempt++;
        }

        return $content;
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
                'angle' => $angle,
                'language' => $intent['language'] ?? 'ru'
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
            $language = $intent['language'] ?? 'ru';
            $templates = $language === 'en'
                ? (self::TITLE_TEMPLATES_EN[$contentType] ?? self::TITLE_TEMPLATES_EN['vocal'])
                : (self::TITLE_TEMPLATES[$contentType] ?? self::TITLE_TEMPLATES['vocal']);

            error_log("AutoShortsGenerator::generateTitle: Content type: {$contentType}, available templates: " . count($templates));

            // Замены для шаблонов
            $replacements = [
                '{content}' => $this->getContentWord($contentType, $language),
                '{emotion}' => $this->getEmotionWord($intent['mood'] ?? 'calm', $language),
                '{visual}' => $this->getVisualWord($intent['visual_focus'] ?? 'neon', $language),
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
            return $language === 'en' ? ucfirst($title) : ucfirst($title);

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
            $language = $intent['language'] ?? 'ru';
            $descType = ['question', 'emotional', 'mysterious'][array_rand(['question', 'emotional', 'mysterious'])];
            $templates = $language === 'en'
                ? (self::DESCRIPTION_TEMPLATES_EN[$descType] ?? self::DESCRIPTION_TEMPLATES_EN['question'])
                : self::DESCRIPTION_TEMPLATES[$descType];

            error_log("AutoShortsGenerator::generateDescription: Desc type: {$descType}, available templates: " . count($templates));

            $template = $templates[array_rand($templates)];
            error_log("AutoShortsGenerator::generateDescription: Selected template: '{$template}'");

            $replacements = [
                '{emotion}' => $this->getEmotionWord($intent['mood'] ?? 'calm', $language),
                '{content}' => $this->getContentWord($intent['content_type'] ?? 'vocal', $language),
                '{visual}' => $this->getVisualWord($intent['visual_focus'] ?? 'neon', $language),
                '{question}' => $this->getQuestionWord($intent['content_type'] ?? 'vocal', $language),
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
        $language = $intent['language'] ?? 'ru';
        $baseTags = $language === 'en'
            ? (self::TAG_SETS_EN[$intent['content_type']] ?? self::TAG_SETS_EN['vocal'])
            : (self::TAG_SETS[$intent['content_type']] ?? self::TAG_SETS['vocal']);

        // Добавляем mood-специфичные теги
        $moodTags = $language === 'en'
            ? [
                'calm' => ['#Calm', '#Relax'],
                'emotional' => ['#Emotions', '#Feelings'],
                'romantic' => ['#Romance', '#Love'],
                'mysterious' => ['#Mystery', '#Vibes']
            ]
            : [
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
        $language = $intent['language'] ?? 'ru';
        $questions = $language === 'en'
            ? (self::ENGAGEMENT_QUESTIONS_EN[$intent['content_type']] ?? self::ENGAGEMENT_QUESTIONS_EN['vocal'])
            : (self::ENGAGEMENT_QUESTIONS[$intent['content_type']] ?? self::ENGAGEMENT_QUESTIONS['vocal']);
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

    private function getContentWord(string $contentType, string $language = 'ru'): string
    {
        $words = $language === 'en'
            ? [
                'vocal' => ['voice', 'vocals', 'singing', 'song'],
                'music' => ['melody', 'music', 'track', 'sound'],
                'aesthetic' => ['visual', 'beauty', 'aesthetic', 'light'],
                'ambience' => ['atmosphere', 'mood', 'vibe', 'ambience']
            ]
            : [
                'vocal' => ['голос', 'вокал', 'пение', 'звук'],
                'music' => ['мелодия', 'музыка', 'композиция', 'звук'],
                'aesthetic' => ['визуал', 'красота', 'эстетика', 'свет'],
                'ambience' => ['атмосфера', 'настроение', 'погружение', 'ощущение']
            ];
        $list = $words[$contentType] ?? $words['vocal'];
        return $list[array_rand($list)];
    }

    private function getEmotionWord(string $mood, string $language = 'ru'): string
    {
        $words = $language === 'en'
            ? [
                'calm' => ['calm', 'soft', 'gentle', 'peaceful'],
                'emotional' => ['emotional', 'touching', 'deep', 'heartfelt'],
                'romantic' => ['romantic', 'tender', 'sweet', 'dreamy'],
                'mysterious' => ['mysterious', 'enigmatic', 'secret', 'haunting']
            ]
            : [
                'calm' => ['спокойный', 'мягкий', 'нежный', 'умиротворяющий'],
                'emotional' => ['эмоциональный', 'трогательный', 'глубокий', 'душевный'],
                'romantic' => ['романтический', 'нежный', 'чувственный', 'лирический'],
                'mysterious' => ['загадочный', 'мистический', 'таинственный', 'непонятный']
            ];
        $list = $words[$mood] ?? $words['calm'];
        return $list[array_rand($list)];
    }

    private function getVisualWord(string $visualFocus, string $language = 'ru'): string
    {
        $words = $language === 'en'
            ? [
                'neon' => ['neon', 'bright', 'colorful', 'glowing'],
                'night' => ['night', 'dark', 'moonlit', 'starry'],
                'closeup' => ['close', 'intimate', 'detailed', 'tight'],
                'atmosphere' => ['atmospheric', 'spacious', 'immersive', 'ambient']
            ]
            : [
                'neon' => ['неоновый', 'яркий', 'цветной', 'светящийся'],
                'night' => ['ночной', 'тёмный', 'лунный', 'звёздный'],
                'closeup' => ['крупный', 'близкий', 'детальный', 'интимный'],
                'atmosphere' => ['атмосферный', 'пространственный', 'объёмный', 'погружающий']
            ];
        $list = $words[$visualFocus] ?? $words['neon'];
        return $list[array_rand($list)];
    }

    private function getQuestionWord(string $contentType, string $language = 'ru'): string
    {
        $questions = $language === 'en'
            ? [
                'vocal' => ['How is the voice?', 'Did the vocals hook you?', 'Loved the singing?'],
                'music' => ['How is the melody?', 'Does the music hit?', 'Sound good?'],
                'aesthetic' => ['Love the visuals?', 'Looks amazing?', 'Aesthetic on point?'],
                'ambience' => ['Feel the atmosphere?', 'Did the vibe land?', 'Immersive enough?']
            ]
            : [
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

        $language = $content['language'] ?? 'ru';
        $templates = $language === 'en'
            ? (self::DESCRIPTION_TEMPLATES_EN[$newType] ?? self::DESCRIPTION_TEMPLATES_EN['question'])
            : self::DESCRIPTION_TEMPLATES[$newType];
        return $templates[array_rand($templates)];
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