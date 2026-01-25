<?php
/**
 * Тест функции toggleAutoGeneration
 */

echo "=== ТЕСТИРОВАНИЕ ФУНКЦИИ toggleAutoGeneration ===\n\n";

echo "🔍 Проверяем HTML структуру:\n";

$content = file_get_contents('views/content_groups/templates/create_v2.php');

// Проверяем наличие элементов
$checks = [
    'use_auto_generation checkbox' => strpos($content, 'id="use_auto_generation"') !== false,
    'manual_fields div' => strpos($content, 'id="manual_fields"') !== false,
    'idea_field div' => strpos($content, 'id="idea_field"') !== false,
    'toggleAutoGeneration function' => strpos($content, 'function toggleAutoGeneration') !== false,
    'DOMContentLoaded listener' => strpos($content, 'DOMContentLoaded') !== false,
    'change event listener' => strpos($content, 'addEventListener(\'change\'') !== false,
];

foreach ($checks as $element => $exists) {
    echo ($exists ? "✅" : "❌") . " $element\n";
}

echo "\n🎯 Ожидаемое поведение:\n";
echo "1. При загрузке страницы поле idea_field скрыто (display: none)\n";
echo "2. При клике на чекбокс вызывается toggleAutoGeneration()\n";
echo "3. Если чекбокс отмечен: manual_fields скрывается, idea_field показывается\n";
echo "4. Если чекбокс не отмечен: manual_fields показывается, idea_field скрывается\n";

echo "\n📋 Для отладки:\n";
echo "1. Откройте https://you.1tlt.ru/content-groups/templates/create-shorts\n";
echo "2. Откройте консоль разработчика (F12)\n";
echo "3. Посмотрите логи при загрузке страницы\n";
echo "4. Нажмите на чекбокс 'Использовать автогенерацию'\n";
echo "5. Проверьте логи в консоли\n";

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";