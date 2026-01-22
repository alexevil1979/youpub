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
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерация уникального имени файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('video_', true) . '.' . $extension;
        $filePath = $uploadDir . '/' . $fileName;

        // Перемещение файла
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => false, 'message' => 'Failed to save file'];
        }

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
