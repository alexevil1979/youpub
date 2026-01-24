<?php
/**
 * Тест доступности ресурсов
 */

$resources = [
    '/assets/css/style.css',
    '/assets/js/main.js',
    '/assets/js/icons.js',
    '/assets/js/search.js',
    '/content-groups/templates/create'
];

echo "=== ТЕСТИРОВАНИЕ ДОСТУПНОСТИ РЕСУРСОВ ===\n\n";

foreach ($resources as $resource) {
    $url = 'https://you.1tlt.ru' . $resource;
    echo "🔗 Проверяем: $url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        echo "✅ Доступен (HTTP $httpCode)\n";
    } else {
        echo "❌ Недоступен (HTTP $httpCode)\n";
    }
    echo "\n";
}

echo "=== ТЕСТ ЗАВЕРШЕН ===\n";