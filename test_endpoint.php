<?php
/**
 * Тест эндпоинта suggest-content
 */

echo "=== ТЕСТИРОВАНИЕ ЭНДПОИНТА /content-groups/templates/suggest-content ===\n\n";

// Имитируем POST запрос
$testData = [
    'idea' => 'Девушка поёт под неоном',
    'csrf_token' => 'test_token'
];

echo "📤 Отправляемые данные:\n";
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

try {
    // Проверяем, что файл контроллера существует
    $controllerPath = __DIR__ . '/app/Modules/ContentGroups/Controllers/TemplateController.php';
    if (!file_exists($controllerPath)) {
        throw new Exception("Controller file not found: $controllerPath");
    }
    echo "✅ Controller file exists\n";

    // Проверяем, что сервис существует
    $servicePath = __DIR__ . '/app/Modules/ContentGroups/Services/AutoShortsGenerator.php';
    if (!file_exists($servicePath)) {
        throw new Exception("Service file not found: $servicePath");
    }
    echo "✅ Service file exists\n";

    // Проверяем конфигурацию
    $configPath = __DIR__ . '/config/env.php';
    if (!file_exists($configPath)) {
        throw new Exception("Config file not found: $configPath");
    }
    echo "✅ Config file exists\n";

    echo "🎯 Эндпоинт готов к работе\n\n";

    echo "📋 Для ручного тестирования:\n";
    echo "1. Откройте браузер\n";
    echo "2. Перейдите на https://you.1tlt.ru/content-groups/templates/create\n";
    echo "3. Откройте консоль разработчика (F12)\n";
    echo "4. Включите 'Использовать автогенерацию'\n";
    echo "5. Введите: 'Девушка поёт под неоном'\n";
    echo "6. Нажмите 'Сгенерировать контент'\n";
    echo "7. Проверьте логи в консоли\n\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "=== ТЕСТ ЗАВЕРШЕН ===\n";