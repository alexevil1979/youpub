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

        // Проверяем подключенные интеграции (поддержка мультиаккаунтов)
        $youtubeRepo = new \App\Repositories\YoutubeIntegrationRepository();
        $telegramRepo = new \App\Repositories\TelegramIntegrationRepository();
        $scheduleRepo = new \App\Repositories\ScheduleRepository();
        $publicationRepo = new \App\Repositories\PublicationRepository();

        // Используем аккаунты по умолчанию
        $youtubeIntegration = $youtubeRepo->findDefaultByUserId($userId);
        $telegramIntegration = $telegramRepo->findDefaultByUserId($userId);

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

    /**
     * Загрузить несколько видео
     */
    public function uploadMultipleVideos(int $userId, array $files, ?int $groupId, string $titleTemplate, string $description, string $tags): array
    {
        $results = [];
        $uploadedVideoIds = [];
        
        // Проверка лимитов
        $user = $this->userRepo->findById($userId);
        $userVideos = $this->videoRepo->findByUserId($userId);
        $currentCount = count($userVideos);
        
        if ($currentCount >= $user['upload_limit']) {
            return ['success' => false, 'message' => 'Upload limit reached'];
        }
        
        // Ограничение на количество файлов
        if (count($files) > 50) {
            return ['success' => false, 'message' => 'Maximum 50 files allowed'];
        }
        
        // Проверяем, не превысит ли загрузка лимит
        if ($currentCount + count($files) > $user['upload_limit']) {
            $allowed = $user['upload_limit'] - $currentCount;
            return ['success' => false, 'message' => "Upload limit will be exceeded. You can upload only {$allowed} more files."];
        }
        
        // Подготовка директории
        $uploadDir = $this->config['UPLOAD_DIR'] . '/' . $userId;
        $uploadDir = realpath(dirname($uploadDir)) . '/' . basename($uploadDir);
        
        $baseDir = dirname($uploadDir);
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }
        
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        
        if (!is_writable($uploadDir)) {
            return ['success' => false, 'message' => 'Upload directory is not writable'];
        }
        
        // Обработка каждого файла
        foreach ($files as $index => $file) {
            $fileResult = [
                'fileName' => $file['name'] ?? 'unknown',
                'success' => false,
                'message' => '',
                'videoId' => null
            ];
            
            // Проверка ошибки загрузки
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $fileResult['message'] = 'Upload error: ' . $this->getUploadErrorMessage($file['error']);
                $results[] = $fileResult;
                continue;
            }
            
            // Проверка размера
            if ($file['size'] > $this->config['UPLOAD_MAX_SIZE']) {
                $fileResult['message'] = 'File size exceeds maximum allowed size';
                $results[] = $fileResult;
                continue;
            }
            
            // Проверка типа файла
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $this->config['ALLOWED_VIDEO_TYPES'])) {
                $fileResult['message'] = 'Invalid file type: ' . $mimeType;
                $results[] = $fileResult;
                continue;
            }
            
            // Генерация имени файла
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('video_', true) . '.' . $extension;
            $filePath = $uploadDir . '/' . $fileName;
            
            // Перемещение файла
            $moved = @move_uploaded_file($file['tmp_name'], $filePath);
            if (!$moved) {
                $fileResult['message'] = 'Failed to save file';
                $results[] = $fileResult;
                continue;
            }
            
            // Генерация названия
            $title = $file['name'];
            if ($titleTemplate) {
                $title = str_replace(['{index}', '{filename}'], [$index + 1, $file['name']], $titleTemplate);
            }
            
            // Сохранение в БД
            try {
                $videoId = $this->videoRepo->create([
                    'user_id' => $userId,
                    'file_path' => $filePath,
                    'file_name' => $file['name'],
                    'file_size' => $file['size'],
                    'mime_type' => $mimeType,
                    'title' => $title,
                    'description' => $description,
                    'tags' => $tags,
                    'status' => 'uploaded',
                ]);
                
                $fileResult['success'] = true;
                $fileResult['message'] = 'Uploaded successfully';
                $fileResult['videoId'] = $videoId;
                $uploadedVideoIds[] = $videoId;
            } catch (\Exception $e) {
                error_log('Error creating video record: ' . $e->getMessage());
                $fileResult['message'] = 'Failed to save video record: ' . $e->getMessage();
                // Удаляем файл, если не удалось сохранить запись
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            $results[] = $fileResult;
        }
        
        // Добавление в группу, если указана
        if ($groupId && !empty($uploadedVideoIds)) {
            try {
                $groupService = new \App\Modules\ContentGroups\Services\GroupService();
                $addResult = $groupService->addVideosToGroup($groupId, $uploadedVideoIds, $userId);
                
                if (!$addResult['success']) {
                    error_log('Failed to add videos to group: ' . $addResult['message']);
                }
            } catch (\Exception $e) {
                error_log('Error adding videos to group: ' . $e->getMessage());
            }
        }
        
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);
        
        return [
            'success' => $successCount > 0,
            'message' => "Uploaded {$successCount} of {$totalCount} files",
            'data' => [
                'results' => $results,
                'successCount' => $successCount,
                'totalCount' => $totalCount,
                'videoIds' => $uploadedVideoIds
            ]
        ];
    }
    
    /**
     * Получить сообщение об ошибке загрузки
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];
        
        return $messages[$errorCode] ?? 'Unknown upload error';
    }

    /**
     * Переключить статус видео
     */
    public function toggleVideoStatus(int $id, int $userId): array
    {
        $video = $this->getVideo($id, $userId);
        
        if (!$video) {
            return ['success' => false, 'message' => 'Video not found'];
        }

        // Определяем новый статус
        // Допустимые значения: 'uploaded','processing','ready','error'
        // Используем простое переключение: ready <-> error (error как индикатор неактивности)
        $currentStatus = $video['status'];
        $newStatus = null;

        // Если видео активно (uploaded, ready) - делаем неактивным (error)
        if (in_array($currentStatus, ['uploaded', 'ready'])) {
            $newStatus = 'error'; // Используем error как индикатор неактивности
        } elseif ($currentStatus === 'error') {
            // Если в статусе error - возвращаем в ready
            $newStatus = 'ready';
        } elseif ($currentStatus === 'processing') {
            // Если обрабатывается - не меняем статус
            return ['success' => false, 'message' => 'Видео обрабатывается, нельзя изменить статус'];
        } else {
            // По умолчанию делаем ready
            $newStatus = 'ready';
        }

        try {
            $updated = $this->videoRepo->update($id, ['status' => $newStatus]);
            if (!$updated) {
                error_log('Toggle video status: Update returned false for video ID: ' . $id);
                return ['success' => false, 'message' => 'Не удалось обновить статус видео'];
            }
        } catch (\Exception $e) {
            error_log('Toggle video status error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'message' => 'Ошибка при обновлении статуса: ' . $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Статус видео изменен',
            'data' => ['status' => $newStatus]
        ];
    }
}
