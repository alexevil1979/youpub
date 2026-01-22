<?php
/**
 * Тестовый файл для проверки работы IconHelper
 * Удалите этот файл после проверки
 */

require_once __DIR__ . '/vendor/autoload.php';

try {
    $icon = \App\Helpers\IconHelper::render('youtube', 24);
    echo "IconHelper работает!<br>";
    echo $icon;
    echo "<br><br>Все иконки:<br>";
    
    $icons = ['youtube', 'telegram', 'tiktok', 'instagram', 'pinterest', 'star', 'pause', 'play', 'delete', 'view'];
    foreach ($icons as $iconName) {
        echo $iconName . ": " . \App\Helpers\IconHelper::render($iconName, 20) . "<br>";
    }
} catch (\Throwable $e) {
    echo "ОШИБКА: " . $e->getMessage() . "<br>";
    echo "Файл: " . $e->getFile() . "<br>";
    echo "Строка: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
