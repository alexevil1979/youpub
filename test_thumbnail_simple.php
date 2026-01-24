<?php
/**
 * Упрощенный тест ThumbnailService без подключения к БД
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\ThumbnailService;

// Создаем экземпляр без наследования от Service (чтобы избежать БД)
class TestThumbnailService extends ThumbnailService {
    public function __construct() {
        // Пропускаем родительский конструктор
    }

    public function testFfmpeg() {
        return $this->isFfmpegAvailable();
    }

    public function testFfmpegPath() {
        return $this->getFfmpegPath();
    }

    public function testFallbackThumbnail($videoId) {
        // Создаем тестовое превью
        $testPath = __DIR__ . '/storage/uploads/thumbnails/test_' . $videoId . '.png';
        return $this->generateFallbackThumbnail('/fake/path.mp4', $videoId);
    }
}

echo "=== Упрощенный тест ThumbnailService ===\n\n";

$service = new TestThumbnailService();

echo "1. Проверка пути FFmpeg:\n";
$ffmpegPath = $service->testFfmpegPath();
echo "   Путь: {$ffmpegPath}\n";

echo "\n2. Проверка доступности FFmpeg:\n";
$ffmpegAvailable = $service->testFfmpeg();
echo "   Доступен: " . ($ffmpegAvailable ? "✅ ДА" : "❌ НЕТ") . "\n";

if (!$ffmpegAvailable) {
    echo "   Примечание: FFmpeg не найден. Будет использоваться fallback превью.\n";
}

echo "\n3. Тест fallback превью:\n";
$fallbackPath = $service->testFallbackThumbnail('test123');
if ($fallbackPath) {
    echo "   Fallback превью создано: ✅ {$fallbackPath}\n";
    $fullPath = __DIR__ . '/storage/uploads/' . $fallbackPath;
    if (file_exists($fullPath)) {
        echo "   Файл существует: ✅ ДА (" . filesize($fullPath) . " байт)\n";
    } else {
        echo "   Файл НЕ существует: ❌ НЕТ\n";
    }
} else {
    echo "   Ошибка создания fallback превью: ❌\n";
}

echo "\n4. Проверка директории превью:\n";
$thumbnailsDir = __DIR__ . '/storage/uploads/thumbnails/';
if (is_dir($thumbnailsDir)) {
    echo "   Директория существует: ✅ ДА\n";
    if (is_writable($thumbnailsDir)) {
        echo "   Директория доступна для записи: ✅ ДА\n";
    } else {
        echo "   Директория НЕ доступна для записи: ❌ НЕТ\n";
    }
} else {
    echo "   Директория НЕ существует: ❌ НЕТ\n";
}

echo "\n=== Тест завершен ===\n";