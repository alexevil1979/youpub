<?php
/**
 * Тест множественной генерации Shorts контента
 */

require_once 'vendor/autoload.php';
require_once 'config/env.php';

use App\Modules\ContentGroups\Services\AutoShortsGenerator;

// Инициализация
$generator = new AutoShortsGenerator();

$testIdeas = [
    "Девушка поёт под неоном",
    "Спокойный голос ночью",
    "Атмосферная музыка в баре"
];

echo "=== ТЕСТИРОВАНИЕ МНОЖЕСТВЕННОЙ ГЕНЕРАЦИИ SHORTS ===\n\n";

foreach ($testIdeas as $idea) {
    echo "🎯 Идея: \"$idea\"\n";
    echo str_repeat("-", 50) . "\n";

    try {
        // Генерируем 25 вариантов
        $variants = $generator->generateMultipleVariants($idea, 25);

        echo "✅ Сгенерировано вариантов: " . count($variants) . "\n\n";

        // Собираем статистику уникальности
        $titles = [];
        $descriptions = [];
        $tags = [];
        $emojis = [];
        $pinnedComments = [];

        foreach ($variants as $variant) {
            $content = $variant['content'];

            if (!empty($content['title'])) $titles[] = $content['title'];
            if (!empty($content['description'])) $descriptions[] = $content['description'];

            if (!empty($content['tags']) && is_array($content['tags'])) {
                $tags = array_merge($tags, $content['tags']);
            }

            if (!empty($content['emoji'])) {
                $emojiList = array_filter(explode(',', $content['emoji']));
                $emojis = array_merge($emojis, $emojiList);
            }

            if (!empty($content['pinned_comment'])) $pinnedComments[] = $content['pinned_comment'];
        }

        $uniqueTitles = array_unique($titles);
        $uniqueDescriptions = array_unique($descriptions);
        $uniqueTags = array_unique($tags);
        $uniqueEmojis = array_unique($emojis);
        $uniqueComments = array_unique($pinnedComments);

        echo "📊 СТАТИСТИКА УНИКАЛЬНОСТИ:\n";
        echo "   Заголовки: " . count($uniqueTitles) . " уникальных из " . count($titles) . "\n";
        echo "   Описания: " . count($uniqueDescriptions) . " уникальных из " . count($descriptions) . "\n";
        echo "   Теги: " . count($uniqueTags) . " уникальных из " . count($tags) . "\n";
        echo "   Emoji: " . count($uniqueEmojis) . " уникальных из " . count($emojis) . "\n";
        echo "   Закреп.комментарии: " . count($uniqueComments) . " уникальных из " . count($pinnedComments) . "\n\n";

        // Показываем первые 3 варианта как пример
        echo "🎨 ПРИМЕРЫ ВАРИАНТОВ:\n";
        for ($i = 0; $i < min(3, count($variants)); $i++) {
            $variant = $variants[$i];
            echo "   Вариант " . ($i + 1) . ":\n";
            echo "     Заголовок: \"" . ($variant['content']['title'] ?? 'N/A') . "\"\n";
            echo "     Описание: \"" . ($variant['content']['description'] ?? 'N/A') . "\"\n";
            echo "     Теги: " . implode(', ', $variant['content']['tags'] ?? []) . "\n";
            echo "     Emoji: " . ($variant['content']['emoji'] ?? 'N/A') . "\n";
            echo "     Настроение: " . ($variant['intent']['mood'] ?? 'N/A') . "\n";
            echo "\n";
        }

    } catch (Exception $e) {
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n\n";
}

echo "=== ТЕСТ ЗАВЕРШЕН ===\n";