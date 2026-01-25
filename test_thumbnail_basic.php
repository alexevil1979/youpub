<?php
/**
 * Базовый тест для проверки создания превью
 */

echo "=== Базовый тест превью ===\n\n";

$thumbnailsDir = __DIR__ . '/storage/uploads/thumbnails/';

echo "1. Проверка директории превью:\n";
if (!is_dir($thumbnailsDir)) {
    echo "   Создание директории...\n";
    if (mkdir($thumbnailsDir, 0755, true)) {
        echo "   ✅ Директория создана\n";
    } else {
        echo "   ❌ Ошибка создания директории\n";
        exit(1);
    }
} else {
    echo "   ✅ Директория существует\n";
}

if (is_writable($thumbnailsDir)) {
    echo "   ✅ Директория доступна для записи\n";
} else {
    echo "   ❌ Директория НЕ доступна для записи\n";
}

echo "\n2. Создание тестового fallback превью:\n";

if (!extension_loaded('gd')) {
    echo "   SKIP: Расширение GD не установлено\n";
} else {
// Создаем простое превью с текстом "VIDEO TEST"
$testFilename = 'test_fallback_' . time() . '.png';
$testPath = $thumbnailsDir . $testFilename;

$image = imagecreatetruecolor(320, 180);
if (!$image) {
    echo "   ❌ Ошибка создания изображения\n";
    exit(1);
}

// Градиентный фон
for ($y = 0; $y < 180; $y++) {
    $color = imagecolorallocate($image, 45 - ($y * 15 / 180), 45 - ($y * 15 / 180), 45 - ($y * 15 / 180));
    imageline($image, 0, $y, 319, $y, $color);
}

// Иконка видео
$playColor = imagecolorallocate($image, 255, 255, 255);
$shadowColor = imagecolorallocate($image, 0, 0, 0);

imagefilledellipse($image, 160 + 2, 90 + 2, 60, 60, $shadowColor);
imagefilledellipse($image, 160, 90, 60, 60, $playColor);

$triangleColor = imagecolorallocate($image, 255, 0, 0);
$points = [150, 75, 150, 105, 175, 90];
imagefilledpolygon($image, $points, 3, $triangleColor);

// Текст
$textColor = imagecolorallocate($image, 200, 200, 200);
imagestring($image, 5, 100, 140, "VIDEO TEST", $textColor);

// Сохраняем
if (imagepng($image, $testPath)) {
    imagedestroy($image);
    echo "   ✅ Тестовое превью создано: {$testFilename}\n";
    echo "   📁 Размер файла: " . filesize($testPath) . " байт\n";
    echo "   📍 Путь: {$testPath}\n";
} else {
    imagedestroy($image);
    echo "   ❌ Ошибка сохранения изображения\n";
}
}

echo "\n3. Проверка FFmpeg:\n";
$ffmpegPaths = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'C:\\ffmpeg\\bin\\ffmpeg.exe'];

$ffmpegFound = false;
foreach ($ffmpegPaths as $path) {
    $command = escapeshellarg($path) . ' -version 2>&1';
    exec($command, $output, $returnCode);
    if ($returnCode === 0 && !empty($output) && strpos($output[0], 'ffmpeg') !== false) {
        echo "   ✅ FFmpeg найден: {$path}\n";
        $ffmpegFound = true;
        break;
    }
}

if (!$ffmpegFound) {
    echo "   ❌ FFmpeg НЕ найден. Будет использоваться только fallback превью.\n";
}

echo "\n=== Тест завершен ===\n";

if (isset($testPath) && file_exists($testPath)) {
    echo "Тестовое превью можно найти: {$testPath}\n";
}
