<?php

/**
 * Worker для генерации превью видео.
 * Использование: php workers/thumbnail_worker.php --video-id=123 --video-path="/path/to/video.mp4"
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;
use App\Services\ThumbnailService;
use App\Repositories\VideoRepository;

set_time_limit(300);
ini_set('memory_limit', '512M');

$configPath = __DIR__ . '/../config/env.php';
if (!file_exists($configPath)) {
    error_log("Thumbnail worker: config not found at {$configPath}");
    exit(1);
}
$config = require $configPath;
if (empty($config['DB_HOST']) || empty($config['DB_NAME']) || empty($config['DB_USER'])) {
    error_log('Thumbnail worker: invalid DB config');
    exit(1);
}

Database::init($config);

$options = getopt('', ['video-id:', 'video-path:']);
$videoId = isset($options['video-id']) ? (int)$options['video-id'] : 0;
$videoPath = $options['video-path'] ?? '';

if ($videoId <= 0 || $videoPath === '') {
    error_log('Thumbnail worker: missing parameters');
    exit(1);
}

$resolvedPath = realpath($videoPath);
if ($resolvedPath === false || !file_exists($resolvedPath)) {
    error_log('Thumbnail worker: video file not found');
    exit(1);
}

$thumbnailService = new ThumbnailService();
$videoRepo = new VideoRepository();

$thumbnail = $thumbnailService->generateThumbnail($resolvedPath, (string)$videoId);
if ($thumbnail) {
    $videoRepo->update($videoId, ['thumbnail_path' => $thumbnail]);
    error_log("Thumbnail worker: thumbnail generated for video {$videoId}");
} else {
    error_log("Thumbnail worker: failed to generate thumbnail for video {$videoId}");
}

Database::close();
