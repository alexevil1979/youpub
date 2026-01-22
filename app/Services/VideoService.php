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
        
        // Нормализация пути (убираем относительные пути)
        $uploadDir = realpath(dirname($uploadDir)) . '/' . basename($uploadDir);
        
        // Логирование для отладки
        error_log('Video Upload: Upload dir = ' . $uploadDir);
        error_log('Video Upload: Upload dir exists = ' . (is_dir($uploadDir) ? 'yes' : 'no'));
        error_log('Video Upload: Base dir = ' . dirname($uploadDir));
        error_log('Video Upload: Base dir writable = ' . (is_writable(dirname($uploadDir)) ? 'yes' : 'no'));
        
        // Создаем базовую директорию, если не существует
        $baseDir = dirname($uploadDir);
        if (!is_dir($baseDir)) {
            $created = @mkdir($baseDir, 0755, true);
            if (!$created) {
                error_log('Video Upload: Failed to create base directory: ' . $baseDir);
                $error = error_get_last();
                error_log('Video Upload: Error: ' . ($error['message'] ?? 'Unknown error'));
                return ['success' => false, 'message' => 'Failed to create upload directory. Please contact administrator to create: ' . $baseDir];
            }
        }
        
        // Создаем директорию пользователя
        if (!is_dir($uploadDir)) {
            $created = @mkdir($uploadDir, 0755, true);
            if (!$created) {
                error_log('Video Upload: Failed to create user directory: ' . $uploadDir);
                $error = error_get_last();
                error_log('Video Upload: Error: ' . ($error['message'] ?? 'Unknown error'));
                return ['success' => false, 'message' => 'Failed to create user upload directory. Please contact administrator.'];
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
     * Опубликовать видео сейчас
     */
    public function publishNow(int $videoId, int $userId): array
    {
        $video = $this->getVideo($videoId, $userId);
        
        if (!$video) {
            return ['success' => false, 'message' => 'Video not found'];
        }

        if (!file_exists($video['file_path'])) {
            return ['success' => false, 'message' => 'Video file not found'];
        }

        // Проверяем подключенные интеграции
        $youtubeRepo = new \App\Repositories\YoutubeIntegrationRepository();
        $telegramRepo = new \App\Repositories\TelegramIntegrationRepository();
        $scheduleRepo = new \App\Repositories\ScheduleRepository();
        $publicationRepo = new \App\Repositories\PublicationRepository();

        $youtubeIntegration = $youtubeRepo->findByUserId($userId);
        $telegramIntegration = $telegramRepo->findByUserId($userId);

        $results = [];
        $hasIntegration = false;

        // Публикация на YouTube
        if ($youtubeIntegration && $youtubeIntegration['status'] === 'connected') {
            $hasIntegration = true;
            $youtubeService = new \App\Services\YoutubeService();
            
            // Создаем временное расписание для публикации
            $scheduleId = $scheduleRepo->create([
                'user_id' => $userId,
                'video_id' => $videoId,
                'platform' => 'youtube',
                'publish_at' => date('Y-m-d H:i:s'),
                'status' => 'pending',
            ]);

            $result = $youtubeService->publishVideo($scheduleId);
            $results['youtube'] = $result;
        }

        // Публикация в Telegram
        if ($telegramIntegration && $telegramIntegration['status'] === 'connected') {
            $hasIntegration = true;
            $telegramService = new \App\Services\TelegramService();
            
            // Создаем временное расписание для публикации
            $scheduleId = $scheduleRepo->create([
                'user_id' => $userId,
                'video_id' => $videoId,
                'platform' => 'telegram',
                'publish_at' => date('Y-m-d H:i:s'),
                'status' => 'pending',
            ]);

            $result = $telegramService->publishVideo($scheduleId);
            $results['telegram'] = $result;
        }

        if (!$hasIntegration) {
            return ['success' => false, 'message' => 'No integrations connected. Please connect YouTube or Telegram first.'];
        }

        // Проверяем результаты
        $allSuccess = true;
        $messages = [];
        
        foreach ($results as $platform => $result) {
            if (!$result['success']) {
                $allSuccess = false;
                $messages[] = ucfirst($platform) . ': ' . $result['message'];
            }
        }

        if ($allSuccess) {
            return [
                'success' => true,
                'message' => 'Video published successfully on all connected platforms',
                'data' => $results
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Some publications failed: ' . implode('; ', $messages),
                'data' => $results
            ];
        }
    }

    /**
     * Обновить видео
     */
    public function updateVideo(int $id, int $userId, array $data): array
    {
        $video = $this->getVideo($id, $userId);
        
        if (!$video) {
            return ['success' => false, 'message' => 'Video not found'];
        }

        $updateData = [];
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['tags'])) {
            $updateData['tags'] = $data['tags'];
        }

        if (empty($updateData)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        $this->videoRepo->update($id, $updateData);

        return [
            'success' => true,
            'message' => 'Video updated successfully'
        ];
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
