<?php

namespace App\Services;

use Core\Service;

/**
 * Сервис для генерации превью изображений из видеофайлов
 */
class ThumbnailService extends Service
{
    private string $thumbnailsDir;
    private string $ffmpegPath;

    public function __construct()
    {
        parent::__construct();
        $this->thumbnailsDir = __DIR__ . '/../../storage/uploads/thumbnails/';
        $this->ffmpegPath = $this->getFfmpegPath();

        // Создаем директорию для превью, если не существует
        if (!is_dir($this->thumbnailsDir)) {
            mkdir($this->thumbnailsDir, 0755, true);
        }
    }

    /**
     * Генерирует превью для видеофайла
     *
     * @param string $videoPath Полный путь к видеофайлу
     * @param string $videoId ID видео в БД
     * @param int $timeOffset Время в секундах для извлечения кадра (по умолчанию 1 сек)
     * @return string|null Путь к сгенерированному превью или null при ошибке
     */
    public function generateThumbnail(string $videoPath, string $videoId, int $timeOffset = 1): ?string
    {
        try {
            // Проверяем, существует ли видеофайл
            if (!file_exists($videoPath)) {
                $this->log("Видеофайл не найден: {$videoPath}");
                return null;
            }

            // Проверяем, доступен ли FFmpeg
            if (!$this->isFfmpegAvailable()) {
                $this->log("FFmpeg не доступен, используем fallback");
                return $this->generateFallbackThumbnail($videoPath, $videoId);
            }

            // Генерируем имя файла превью
            $thumbnailFilename = "thumb_{$videoId}_" . time() . ".jpg";
            $thumbnailPath = $this->thumbnailsDir . $thumbnailFilename;

            // Извлекаем кадр из видео с помощью FFmpeg
            $result = $this->extractFrameWithFfmpeg($videoPath, $thumbnailPath, $timeOffset);

            if ($result) {
                // Оптимизируем изображение
                $this->optimizeThumbnail($thumbnailPath);

                // Возвращаем относительный путь для сохранения в БД
                $relativePath = 'thumbnails/' . $thumbnailFilename;
                $this->log("Превью успешно сгенерировано: {$relativePath}");
                return $relativePath;
            } else {
                $this->log("Не удалось извлечь кадр, используем fallback");
                return $this->generateFallbackThumbnail($videoPath, $videoId);
            }

        } catch (\Exception $e) {
            $this->log("Ошибка генерации превью: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Извлекает кадр из видео с помощью FFmpeg
     */
    private function extractFrameWithFfmpeg(string $videoPath, string $thumbnailPath, int $timeOffset): bool
    {
        // Экранируем пути для безопасности
        $videoPath = escapeshellarg($videoPath);
        $thumbnailPath = escapeshellarg($thumbnailPath);

        // Команда FFmpeg для извлечения одного кадра
        $command = "{$this->ffmpegPath} -i {$videoPath} -ss {$timeOffset} -vframes 1 -q:v 2 -vf scale=320:180:force_original_aspect_ratio=decrease,pad=320:180:(ow-iw)/2:(oh-ih)/2 {$thumbnailPath} 2>&1";

        $this->log("Выполняем команду FFmpeg: {$command}");

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists(str_replace("'", "", $thumbnailPath))) {
            $this->log("FFmpeg успешно извлек кадр");
            return true;
        } else {
            $this->log("FFmpeg вернул ошибку (код: {$returnCode}): " . implode("\n", $output));
            return false;
        }
    }

    /**
     * Генерирует fallback превью (простое изображение-заглушку)
     */
    private function generateFallbackThumbnail(string $videoPath, string $videoId): ?string
    {
        try {
            // Создаем простое превью с текстом "Video"
            $thumbnailFilename = "thumb_{$videoId}_fallback_" . time() . ".png";
            $thumbnailPath = $this->thumbnailsDir . $thumbnailFilename;

            // Создаем изображение 320x180 с текстом
            $image = imagecreatetruecolor(320, 180);
            if (!$image) {
                return null;
            }

            // Фон (темно-серый градиент)
            $bgColor1 = imagecolorallocate($image, 45, 45, 45);
            $bgColor2 = imagecolorallocate($image, 30, 30, 30);

            // Заполняем градиентом
            for ($y = 0; $y < 180; $y++) {
                $color = imagecolorallocate($image,
                    45 - ($y * 15 / 180),
                    45 - ($y * 15 / 180),
                    45 - ($y * 15 / 180)
                );
                imageline($image, 0, $y, 319, $y, $color);
            }

            // Иконка видео (простой play button)
            $playColor = imagecolorallocate($image, 255, 255, 255);
            $shadowColor = imagecolorallocate($image, 0, 0, 0);

            // Тень play button
            imagefilledellipse($image, 160 + 2, 90 + 2, 60, 60, $shadowColor);
            // Play button
            imagefilledellipse($image, 160, 90, 60, 60, $playColor);

            // Треугольник play
            $triangleColor = imagecolorallocate($image, 255, 0, 0);
            $points = [
                150, 75,  // левый верхний
                150, 105, // левый нижний
                175, 90   // правый центр
            ];
            imagefilledpolygon($image, $points, 3, $triangleColor);

            // Текст "VIDEO"
            $textColor = imagecolorallocate($image, 200, 200, 200);
            $fontSize = 5; // GD font size
            imagestring($image, $fontSize, 120, 140, "VIDEO", $textColor);

            // Сохраняем изображение
            if (imagepng($image, $thumbnailPath)) {
                imagedestroy($image);
                $relativePath = 'thumbnails/' . $thumbnailFilename;
                $this->log("Fallback превью создано: {$relativePath}");
                return $relativePath;
            } else {
                imagedestroy($image);
                return null;
            }

        } catch (\Exception $e) {
            $this->log("Ошибка создания fallback превью: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Оптимизирует превью изображение
     */
    private function optimizeThumbnail(string $thumbnailPath): void
    {
        try {
            // Проверяем размер файла
            if (file_exists($thumbnailPath) && filesize($thumbnailPath) > 500 * 1024) { // > 500KB
                $this->log("Оптимизируем превью, размер: " . filesize($thumbnailPath) . " байт");

                // Используем GD для оптимизации
                $image = imagecreatefromjpeg($thumbnailPath);
                if ($image) {
                    // Сжимаем с качеством 85%
                    imagejpeg($image, $thumbnailPath, 85);
                    imagedestroy($image);
                    $this->log("Превью оптимизировано до: " . filesize($thumbnailPath) . " байт");
                }
            }
        } catch (\Exception $e) {
            $this->log("Ошибка оптимизации превью: " . $e->getMessage());
        }
    }

    /**
     * Удаляет превью файл
     */
    public function deleteThumbnail(string $thumbnailPath): bool
    {
        $fullPath = $this->thumbnailsDir . basename($thumbnailPath);
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return true;
    }

    /**
     * Получает путь к FFmpeg
     */
    private function getFfmpegPath(): string
    {
        // Возможные пути к FFmpeg
        $possiblePaths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'ffmpeg', // из PATH
            'C:\\ffmpeg\\bin\\ffmpeg.exe', // Windows
        ];

        foreach ($possiblePaths as $path) {
            if ($this->isValidFfmpegPath($path)) {
                return $path;
            }
        }

        return 'ffmpeg'; // fallback
    }

    /**
     * Проверяет, доступен ли FFmpeg
     */
    private function isFfmpegAvailable(): bool
    {
        return $this->isValidFfmpegPath($this->ffmpegPath);
    }

    /**
     * Проверяет корректность пути к FFmpeg
     */
    private function isValidFfmpegPath(string $path): bool
    {
        $command = escapeshellarg($path) . ' -version 2>&1';
        exec($command, $output, $returnCode);
        return $returnCode === 0 && !empty($output) && strpos($output[0], 'ffmpeg') !== false;
    }

    /**
     * Логирует сообщения
     */
    private function log(string $message): void
    {
        error_log("[ThumbnailService] {$message}");
    }

    /**
     * Получает информацию о видео (длительность, размер и т.д.)
     */
    public function getVideoInfo(string $videoPath): ?array
    {
        if (!$this->isFfmpegAvailable()) {
            return null;
        }

        $command = escapeshellarg($this->ffmpegPath) . ' -i ' . escapeshellarg($videoPath) . ' 2>&1';
        exec($command, $output, $returnCode);

        $info = [
            'duration' => null,
            'width' => null,
            'height' => null,
            'bitrate' => null,
            'size' => filesize($videoPath) ?? null,
        ];

        foreach ($output as $line) {
            // Ищем длительность
            if (preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/', $line, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
                $seconds = (float)$matches[3];
                $info['duration'] = $hours * 3600 + $minutes * 60 + $seconds;
            }

            // Ищем разрешение
            if (preg_match('/Stream.*Video.* (\d+)x(\d+)/', $line, $matches)) {
                $info['width'] = (int)$matches[1];
                $info['height'] = (int)$matches[2];
            }

            // Ищем битрейт
            if (preg_match('/bitrate: (\d+) kb\/s/', $line, $matches)) {
                $info['bitrate'] = (int)$matches[1];
            }
        }

        return $info;
    }
}