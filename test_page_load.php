<?php
/**
 * Тест загрузки страницы создания шаблона
 */

echo "=== ТЕСТИРОВАНИЕ ЗАГРУЗКИ СТРАНИЦЫ ===\n\n";

$url = 'https://you.1tlt.ru/content-groups/templates/create-shorts';

echo "🌐 Проверяем: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

echo "⏳ Загружаем страницу...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

curl_close($ch);

echo "📡 HTTP Код: $httpCode\n";
echo "📦 Размер ответа: " . number_format($contentLength) . " байт\n";

if ($httpCode == 200) {
    echo "✅ Страница загружается успешно\n";

    // Проверяем наличие ключевых элементов
    $checks = [
        'use_auto_generation' => strpos($response, 'id="use_auto_generation"') !== false,
        'manual_fields' => strpos($response, 'id="manual_fields"') !== false,
        'idea_field' => strpos($response, 'id="idea_field"') !== false,
        'toggleAutoGeneration' => strpos($response, 'function toggleAutoGeneration') !== false,
        'fillFormWithSuggestion' => strpos($response, 'function fillFormWithSuggestion') !== false,
    ];

    echo "\n🔍 Проверка элементов:\n";
    foreach ($checks as $element => $exists) {
        echo "  " . ($exists ? "✅" : "❌") . " $element\n";
    }

    $allElementsPresent = !in_array(false, $checks, true);
    echo "\n" . ($allElementsPresent ? "✅ Все элементы найдены" : "❌ Некоторые элементы отсутствуют") . "\n";

} else {
    echo "❌ Ошибка загрузки страницы (HTTP $httpCode)\n";
    echo "📄 Начало ответа:\n" . substr($response, 0, 200) . "...\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";