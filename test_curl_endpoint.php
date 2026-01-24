<?php
/**
 * Тест curl запроса к эндпоинту
 */

echo "=== ТЕСТ CURL ЗАПРОСА К ЭНДПОИНТУ ===\n\n";

$url = 'https://you.1tlt.ru/content-groups/templates/suggest-content';
$data = 'idea=' . urlencode('Девушка поёт под неоном') . '&csrf_token=test';

echo "🌐 URL: $url\n";
echo "📤 Данные: $data\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'X-Requested-With: XMLHttpRequest',
    'User-Agent: Test Script'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Для теста

echo "⏳ Отправляем запрос...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

echo "📡 HTTP Код: $httpCode\n";

if ($curlError) {
    echo "❌ Curl ошибка: $curlError\n";
} else {
    echo "✅ Ответ получен\n";
    echo "📦 Длина ответа: " . strlen($response) . " символов\n\n";

    // Пытаемся распарсить JSON
    $jsonData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON валиден\n";
        echo "📋 Содержимое ответа:\n";
        echo json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ JSON невалиден: " . json_last_error_msg() . "\n";
        echo "📄 Сырой ответ:\n";
        echo substr($response, 0, 500) . (strlen($response) > 500 ? "...\n" : "\n");
    }
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";