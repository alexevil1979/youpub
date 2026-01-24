<?php
/**
 * Тест JavaScript синтаксиса в create_v2.php
 */

// Извлекаем JavaScript код между <script> и </script>
$content = file_get_contents('views/content_groups/templates/create_v2.php');
preg_match('/<script>(.*?)<\/script>/s', $content, $matches);

if (isset($matches[1])) {
    $jsCode = $matches[1];

    // Проверяем баланс скобок try-catch
    $tryCount = substr_count($jsCode, 'try {');
    $catchCount = substr_count($jsCode, '} catch');

    echo "=== ПРОВЕРКА JAVASCRIPT СИНТАКСИСА ===\n\n";
    echo "Блоков try: $tryCount\n";
    echo "Блоков catch: $catchCount\n\n";

    if ($tryCount === $catchCount) {
        echo "✅ Количество try и catch блоков совпадает\n";
    } else {
        echo "❌ Несбалансированные блоки try-catch!\n";
        echo "Нужно: $tryCount catch блоков, найдено: $catchCount\n";
    }

    // Проверяем незакрытые try блоки
    $lines = explode("\n", $jsCode);
    $openTryBlocks = 0;

    foreach ($lines as $lineNum => $line) {
        if (strpos($line, 'try {') !== false) {
            $openTryBlocks++;
            echo "Строка " . ($lineNum + 1) . ": try {\n";
        }
        if (strpos($line, '} catch') !== false) {
            $openTryBlocks--;
            echo "Строка " . ($lineNum + 1) . ": } catch\n";
        }
    }

    echo "\nОткрытых try блоков в конце: $openTryBlocks\n";

    if ($openTryBlocks === 0) {
        echo "✅ Все try блоки закрыты\n";
    } else {
        echo "❌ Есть незакрытые try блоки!\n";
    }

} else {
    echo "❌ Не удалось извлечь JavaScript код\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";