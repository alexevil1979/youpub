<?php
/**
 * Тестовый скрипт для проверки генерации превью видео
 */

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;
use App\Services\ThumbnailService;

$config = require __DIR__ . '/config/env.php';
Database::init($config);

echo "=== Тест генерации превью ===\n\n";

// Инициализируем сервис
$thumbnailService = new ThumbnailService();

// Тест 1: Проверка доступности FFmpeg
echo "1. Проверка FFmpeg:\n";
$ffmpegAvailable = $thumbnailService->isFfmpegAvailable();
echo "   FFmpeg доступен: " . ($ffmpegAvailable ? "✅ ДА" : "❌ НЕТ") . "\n\n";

// Тест 2: Поиск существующего видео
echo "2. Поиск видео для тестирования:\n";
$db = Database::getInstance();
$stmt = $db->query("SELECT id, file_path, title FROM videos WHERE file_path IS NOT NULL AND file_path != '' LIMIT 1");
$video = $stmt->fetch();

if ($video) {
    echo "   Найдено видео ID {$video['id']}: {$video['title']}\n";
    echo "   Путь к файлу: {$video['file_path']}\n";

    // Проверяем существование файла
    if (file_exists($video['file_path'])) {
        echo "   Файл существует: ✅ ДА\n";

        // Тест 3: Генерация превью
        echo "\n3. Генерация превью:\n";
        $thumbnailPath = $thumbnailService->generateThumbnail($video['file_path'], $video['id']);

        if ($thumbnailPath) {
            echo "   Превью сгенерировано: ✅ {$thumbnailPath}\n";

            // Проверяем, что файл создан
            $fullPath = __DIR__ . '/storage/uploads/' . $thumbnailPath;
            if (file_exists($fullPath)) {
                echo "   Файл превью существует: ✅ ДА\n";
                $fileSize = filesize($fullPath);
                echo "   Размер файла: " . number_format($fileSize / 1024, 2) . " KB\n";

                // Получаем информацию о видео
                $videoInfo = $thumbnailService->getVideoInfo($video['file_path']);
                if ($videoInfo) {
                    echo "   Информация о видео:\n";
                    if (isset($videoInfo['duration'])) {
                        echo "     Длительность: " . gmdate("i:s", $videoInfo['duration']) . "\n";
                    }
                    if (isset($videoInfo['width']) && isset($videoInfo['height'])) {
                        echo "     Разрешение: {$videoInfo['width']}x{$videoInfo['height']}\n";
                    }
                    if (isset($videoInfo['bitrate'])) {
                        echo "     Битрейт: {$videoInfo['bitrate']} kb/s\n";
                    }
                }
            } else {
                echo "   Файл превью НЕ существует: ❌ НЕТ\n";
            }
        } else {
            echo "   Ошибка генерации превью: ❌\n";
        }

    } else {
        echo "   Файл НЕ существует: ❌ НЕТ\n";
    }

} else {
    echo "   Видео не найдено в базе данных\n";
    echo "   Создайте видео через интерфейс загрузки\n";
}

echo "\n=== Тест завершен ===\n";