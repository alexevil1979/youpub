<?php
/**
 * Упрощенный тест ThumbnailService без подключения к БД
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Упрощенный тест ThumbnailService ===\n\n";

$ffmpegPaths = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'C:\\ffmpeg\\bin\\ffmpeg.exe'];
$ffmpegAvailable = false;
$ffmpegPath = null;

foreach ($ffmpegPaths as $path) {
    $command = escapeshellarg($path) . ' -version 2>&1';
    exec($command, $output, $returnCode);
    if ($returnCode === 0 && !empty($output) && strpos($output[0], 'ffmpeg') !== false) {
        $ffmpegAvailable = true;
        $ffmpegPath = $path;
        break;
    }
}

echo "1. Проверка пути FFmpeg:\n";
echo "   Путь: " . ($ffmpegPath ?: 'не найден') . "\n";

echo "\n2. Проверка доступности FFmpeg:\n";
echo "   Доступен: " . ($ffmpegAvailable ? "✅ ДА" : "❌ НЕТ") . "\n";

if (!$ffmpegAvailable) {
    echo "   Примечание: FFmpeg не найден. Будет использоваться fallback превью.\n";
}

echo "\n3. Тест fallback превью:\n";
if (!extension_loaded('gd')) {
    echo "   SKIP: Расширение GD не установлено\n";
} else {
    $thumbnailsDir = __DIR__ . '/storage/uploads/thumbnails/';
    if (!is_dir($thumbnailsDir)) {
        mkdir($thumbnailsDir, 0755, true);
    }
    $testFilename = 'test_fallback_' . time() . '.png';
    $testPath = $thumbnailsDir . $testFilename;

    $image = imagecreatetruecolor(320, 180);
    if (!$image) {
        echo "   ❌ Ошибка создания изображения\n";
    } else {
        $bg = imagecolorallocate($image, 45, 45, 45);
        imagefilledrectangle($image, 0, 0, 319, 179, $bg);
        $textColor = imagecolorallocate($image, 200, 200, 200);
        imagestring($image, 5, 100, 140, "VIDEO TEST", $textColor);
        if (imagepng($image, $testPath)) {
            imagedestroy($image);
            echo "   Fallback превью создано: ✅ {$testFilename}\n";
            echo "   Файл существует: ✅ ДА (" . filesize($testPath) . " байт)\n";
        } else {
            imagedestroy($image);
            echo "   ❌ Ошибка сохранения изображения\n";
        }
    }
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
