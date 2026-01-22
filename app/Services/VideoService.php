<?php

namespace App\Services;

use Core\Service;
use App\Repositories\VideoRepository;
use App\Repositories\UserRepository;

/**
 * Сервис для работы с видео
 */
class VideoService extends Service
{
    private VideoRepository $videoRepo;
    private UserRepository $userRepo;

    public function __construct()
    {
        parent::__construct();
        $this->videoRepo = new VideoRepository();
        $this->userRepo = new UserRepository();
    }

    /**
     * Загрузить видео
     */
    public function uploadVideo(int $userId, array $file, string $title, string $description, string $tags): array
    {
        // Проверка лимитов
        $user = $this->userRepo->findById($userId);
        $userVideos = $this->videoRepo->findByUserId($userId);
        
        if (count($userVideos) >= $user['upload_limit']) {
            return ['success' => false, 'message' => 'Upload limit reached'];
        }

        // Проверка размера файла
        if ($file['size'] > $this->config['UPLOAD_MAX_SIZE']) {
            return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
        }

        // Проверка типа файла
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->config['ALLOWED_VIDEO_TYPES'])) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        // Создание директории для пользователя
        $uploadDir = $this->config['UPLOAD_DIR'] . '/' . $userId;
        
        // Логирование для отладки
        error_log('Video Upload: Upload dir = ' . $uploadDir);
        error_log('Video Upload: Upload dir exists = ' . (is_dir($uploadDir) ? 'yes' : 'no'));
        error_log('Video Upload: Upload dir writable = ' . (is_writable(dirname($uploadDir)) ? 'yes' : 'no'));
        
        if (!is_dir($uploadDir)) {
            $created = @mkdir($uploadDir, 0755, true);
            if (!$created) {
                error_log('Video Upload: Failed to create directory: ' . $uploadDir);
                error_log('Video Upload: Error: ' . error_get_last()['message'] ?? 'Unknown error');
                return ['success' => false, 'message' => 'Failed to create upload directory. Check permissions.'];
            }
        }

        // Проверка прав на запись
        if (!is_writable($uploadDir)) {
            error_log('Video Upload: Directory not writable: ' . $uploadDir);
            return ['success' => false, 'message' => 'Upload directory is not writable. Check permissions.'];
        }

        // Генерация уникального имени файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('video_', true) . '.' . $extension;
        $filePath = $uploadDir . '/' . $fileName;

        error_log('Video Upload: File path = ' . $filePath);
        error_log('Video Upload: Temp file = ' . $file['tmp_name']);
        error_log('Video Upload: Temp file exists = ' . (file_exists($file['tmp_name']) ? 'yes' : 'no'));

        // Перемещение файла
        $moved = @move_uploaded_file($file['tmp_name'], $filePath);
        if (!$moved) {
            $error = error_get_last();
            error_log('Video Upload: Failed to move file');
            error_log('Video Upload: Error: ' . ($error['message'] ?? 'Unknown error'));
            error_log('Video Upload: PHP upload_max_filesize = ' . ini_get('upload_max_filesize'));
            error_log('Video Upload: PHP post_max_size = ' . ini_get('post_max_size'));
            error_log('Video Upload: File size = ' . $file['size']);
            return ['success' => false, 'message' => 'Failed to save file. Check server logs for details.'];
        }

        error_log('Video Upload: File saved successfully to ' . $filePath);

        // Сохранение в БД
        $videoId = $this->videoRepo->create([
            'user_id' => $userId,
            'file_path' => $filePath,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
            'title' => $title ?: $file['name'],
            'description' => $description,
            'tags' => $tags,
            'status' => 'uploaded',
        ]);

        return [
            'success' => true,
            'message' => 'Video uploaded successfully',
            'data' => ['id' => $videoId]
        ];
    }

    /**
     * Получить видео пользователя
     */
    public function getUserVideos(int $userId): array
    {
        return $this->videoRepo->findByUserId($userId, ['created_at' => 'DESC']);
    }

    /**
     * Получить видео
     */
    public function getVideo(int $id, int $userId): ?array
    {
        $video = $this->videoRepo->findById($id);
        
        if (!$video || $video['user_id'] !== $userId) {
            return null;
        }

        return $video;
    }

    /**
     * Удалить видео
     */
    public function deleteVideo(int $id, int $userId): array
    {
        $video = $this->getVideo($id, $userId);
        
        if (!$video) {
            return ['success' => false, 'message' => 'Video not found'];
        }

        // Удаление файла
        if (file_exists($video['file_path'])) {
            unlink($video['file_path']);
        }

        // Удаление из БД
        $this->videoRepo->delete($id);

        return ['success' => true, 'message' => 'Video deleted successfully'];
    }
}
