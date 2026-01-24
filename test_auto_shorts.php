<?php

/**
 * Тестовый скрипт для проверки автогенерации Shorts
 */

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;
use App\Modules\ContentGroups\Services\AutoShortsGenerator;

$config = require __DIR__ . '/config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

echo "=== Тест автогенерации Shorts ===\n\n";

// Тестовые идеи
$testIdeas = [
    'Девушка поёт под неоном',
    'Атмосферный вокал ночью',
    'Спокойный голос и неон',
    'Мистический шепот в темноте',
    'Романтическая мелодия под луной',
    'Эмоциональный вокал с эффектами'
];

$generator = new AutoShortsGenerator();

foreach ($testIdeas as $idea) {
    echo "🎯 Идея: \"$idea\"\n";

    try {
        $result = $generator->generateFromIdea($idea);

        echo "📊 Анализ:\n";
        echo "  - Тип контента: {$result['intent']['content_type']}\n";
        echo "  - Настроение: {$result['intent']['mood']}\n";
        echo "  - Визуальный фокус: {$result['intent']['visual_focus']}\n";

        echo "📝 Сгенерированный контент:\n";
        echo "  - Название: {$result['content']['title']}\n";
        echo "  - Описание: {$result['content']['description']}\n";
        echo "  - Emoji: " . (!empty($result['content']['emoji']) ? $result['content']['emoji'] : '(нет)') . "\n";
        echo "  - Теги: " . implode(', ', $result['content']['tags']) . "\n";
        echo "  - Комментарий: {$result['content']['pinned_comment']}\n";
        echo "  - Угол: {$result['content']['angle']}\n";

    } catch (Exception $e) {
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("-", 60) . "\n\n";
}

echo "=== Тест завершён ===\n";