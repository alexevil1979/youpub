<?php

namespace App\Services;

use Core\Service;
use App\Repositories\VideoRepository;
use App\Repositories\UserRepository;
use App\Services\ThumbnailService;

/**
 * Сервис для работы с видео
 */
class VideoService extends Service
{
    private VideoRepository $videoRepo;
    private UserRepository $userRepo;
    private ThumbnailService $thumbnailService;

    public function __construct()
    {
        parent::__construct();
        $this->videoRepo = new VideoRepository();
        $this->userRepo = new UserRepository();
        $this->thumbnailService = new ThumbnailService();
    }

    /**
     * Загрузить видео
     */
    public function uploadVideo(int $userId, array $file, string $title, string $description, string $tags): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $userVideos = $this->videoRepo->findByUserId($userId);
        if (count($userVideos) >= $user['upload_limit']) {
            return ['success' => false, 'message' => 'Upload limit reached'];
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->getUploadErrorMessage((int)$file['error'])];
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid upload'];
        }

        $maxSize = (int)($this->config['UPLOAD_MAX_SIZE'] ?? 0);
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
        }

        $mimeType = $this->detectMimeType($file['tmp_name']);
        if (!$mimeType) {
            return ['success' => false, 'message' => 'Unable to detect file type'];
        }

        $extension = $this->mapMimeToExtension($mimeType);
        if (!$extension) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        $uploadDirResult = $this->prepareUploadDirectory($userId);
        if (!$uploadDirResult['success']) {
            return ['success' => false, 'message' => $uploadDirResult['message']];
        }
        $uploadDir = $uploadDirResult['path'];

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $filePath = $uploadDir . '/' . $fileName;

        if (!@move_uploaded_file($file['tmp_name'], $filePath)) {
            $error = error_get_last();
            error_log('Video Upload: Failed to move file: ' . ($error['message'] ?? 'Unknown error'));
            return ['success' => false, 'message' => 'Failed to save file. Check server logs for details.'];
        }

        $originalName = $this->sanitizeFileName((string)($file['name'] ?? 'video.' . $extension));
        $videoId = $this->videoRepo->create([
            'user_id' => $userId,
            'file_path' => $filePath,
            'file_name' => $originalName,
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
            'title' => $title !== '' ? $title : $originalName,
            'description' => $description,
            'tags' => $tags,
            'status' => 'uploaded',
        ]);

        $this->generateThumbnailAsync($videoId, $filePath);

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
        try {
            error_log("publishNow: Starting publication for video ID {$videoId}, user ID {$userId}");
            
            $video = $this->getVideo($videoId, $userId);
            
            if (!$video) {
                error_log("publishNow: Video not found - ID {$videoId}, user ID {$userId}");
                return ['success' => false, 'message' => 'Видео не найдено'];
            }

            if (!file_exists($video['file_path'])) {
                error_log("publishNow: Video file not found - {$video['file_path']}");
                return ['success' => false, 'message' => 'Файл видео не найден: ' . basename($video['file_path'])];
            }

            // Проверяем подключенные интеграции (поддержка мультиаккаунтов)
            $youtubeRepo = new \App\Repositories\YoutubeIntegrationRepository();
            $telegramRepo = new \App\Repositories\TelegramIntegrationRepository();
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $publicationRepo = new \App\Repositories\PublicationRepository();

            // Используем аккаунты по умолчанию
            $youtubeIntegration = $youtubeRepo->findDefaultByUserId($userId);
            $telegramIntegration = $telegramRepo->findDefaultByUserId($userId);

            error_log("publishNow: YouTube integration - " . ($youtubeIntegration ? "found, status: {$youtubeIntegration['status']}" : "not found"));
            error_log("publishNow: Telegram integration - " . ($telegramIntegration ? "found, status: {$telegramIntegration['status']}" : "not found"));

            $results = [];
            $hasIntegration = false;

            // Публикация на YouTube
            if ($youtubeIntegration && $youtubeIntegration['status'] === 'connected') {
                $hasIntegration = true;
                error_log("publishNow: Attempting YouTube publication");
                
                try {
                    $youtubeService = new \App\Services\YoutubeService();
                    
                    // Создаем временное расписание для публикации
                    $scheduleId = $scheduleRepo->create([
                        'user_id' => $userId,
                        'video_id' => $videoId,
                        'platform' => 'youtube',
                        'publish_at' => date('Y-m-d H:i:s'),
                        'status' => 'pending',
                    ]);

                    error_log("publishNow: Created schedule ID {$scheduleId} for YouTube");
                    $result = $youtubeService->publishVideo($scheduleId);
                    error_log("publishNow: YouTube publication result - success: " . ($result['success'] ? 'yes' : 'no') . ", message: " . ($result['message'] ?? 'no message'));
                    $results['youtube'] = $result;
                } catch (\Exception $e) {
                    error_log("publishNow: YouTube publication exception - " . $e->getMessage());
                    $results['youtube'] = [
                        'success' => false,
                        'message' => 'Ошибка публикации на YouTube: ' . $e->getMessage()
                    ];
                }
            } elseif ($youtubeIntegration) {
                error_log("publishNow: YouTube integration exists but status is '{$youtubeIntegration['status']}', not 'connected'");
            }

            // Публикация в Telegram
            if ($telegramIntegration && $telegramIntegration['status'] === 'connected') {
                $hasIntegration = true;
                error_log("publishNow: Attempting Telegram publication");
                
                try {
                    $telegramService = new \App\Services\TelegramService();
                    
                    // Создаем временное расписание для публикации
                    $scheduleId = $scheduleRepo->create([
                        'user_id' => $userId,
                        'video_id' => $videoId,
                        'platform' => 'telegram',
                        'publish_at' => date('Y-m-d H:i:s'),
                        'status' => 'pending',
                    ]);

                    error_log("publishNow: Created schedule ID {$scheduleId} for Telegram");
                    $result = $telegramService->publishVideo($scheduleId);
                    error_log("publishNow: Telegram publication result - success: " . ($result['success'] ? 'yes' : 'no') . ", message: " . ($result['message'] ?? 'no message'));
                    $results['telegram'] = $result;
                } catch (\Exception $e) {
                    error_log("publishNow: Telegram publication exception - " . $e->getMessage());
                    $results['telegram'] = [
                        'success' => false,
                        'message' => 'Ошибка публикации в Telegram: ' . $e->getMessage()
                    ];
                }
            } elseif ($telegramIntegration) {
                error_log("publishNow: Telegram integration exists but status is '{$telegramIntegration['status']}', not 'connected'");
            }

            if (!$hasIntegration) {
                error_log("publishNow: No connected integrations found");
                return [
                    'success' => false, 
                    'message' => 'Нет подключенных интеграций. Пожалуйста, подключите YouTube или Telegram в разделе "Интеграции".'
                ];
            }

            // Проверяем результаты
            $allSuccess = true;
            $messages = [];
            
            foreach ($results as $platform => $result) {
                if (!$result['success']) {
                    $allSuccess = false;
                    $messages[] = ucfirst($platform) . ': ' . ($result['message'] ?? 'Неизвестная ошибка');
                }
            }

            if ($allSuccess) {
                error_log("publishNow: All publications successful");
                return [
                    'success' => true,
                    'message' => 'Видео успешно опубликовано на всех подключенных платформах',
                    'data' => $results
                ];
            } else {
                error_log("publishNow: Some publications failed - " . implode('; ', $messages));
                return [
                    'success' => false,
                    'message' => 'Ошибка публикации: ' . implode('; ', $messages),
                    'data' => $results
                ];
            }
        } catch (\Exception $e) {
            error_log("publishNow: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return [
                'success' => false,
                'message' => 'Произошла ошибка при публикации: ' . $e->getMessage()
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
        if (array_key_exists('title', $data)) {
            $title = trim((string)$data['title']);
            if ($title === '') {
                return ['success' => false, 'message' => 'Title is required'];
            }
            if (mb_strlen($title) > 120) {
                return ['success' => false, 'message' => 'Title must be 120 characters or less'];
            }
            $updateData['title'] = $title;
        }
        if (array_key_exists('description', $data)) {
            $description = trim((string)$data['description']);
            if (mb_strlen($description) > 5000) {
                return ['success' => false, 'message' => 'Description is too long'];
            }
            $updateData['description'] = $description;
        }
        if (array_key_exists('tags', $data)) {
            $tags = trim((string)$data['tags']);
            if (mb_strlen($tags) > 500) {
                return ['success' => false, 'message' => 'Tags is too long'];
            }
            $updateData['tags'] = $tags;
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
     * Асинхронная генерация превью для видео
     */
    private function generateThumbnailAsync(int $videoId, string $videoPath): void
    {
        $scriptPath = realpath(__DIR__ . '/../../workers/thumbnail_worker.php');
        if ($scriptPath === false) {
            error_log('Thumbnail worker script not found');
            return;
        }

        $phpPath = PHP_BINARY;
        $videoPathArg = escapeshellarg($videoPath);
        $cmd = escapeshellarg($phpPath) . ' ' . escapeshellarg($scriptPath) .
            ' --video-id=' . (int)$videoId .
            ' --video-path=' . $videoPathArg;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'start /B ' . $cmd . ' > NUL 2>&1';
        } else {
            $cmd .= ' > /dev/null 2>&1 &';
        }

        exec($cmd);
        error_log("Thumbnail generation started for video ID {$videoId}");
    }

    /**
     * Загрузить несколько видео
     */
    public function uploadMultipleVideos(int $userId, array $files, ?int $groupId, string $titleTemplate, string $description, string $tags): array
    {
        try {
            $results = [];
            $uploadedVideoIds = [];
            
            if (empty($files)) {
                return ['success' => false, 'message' => 'Не выбрано ни одного файла для загрузки'];
            }
            
            error_log("uploadMultipleVideos: Processing " . count($files) . " files for user {$userId}");
            
            // Проверка лимитов
            $user = $this->userRepo->findById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'Пользователь не найден'];
            }
            
            $userVideos = $this->videoRepo->findByUserId($userId);
            $currentCount = count($userVideos);
            
            if ($currentCount >= $user['upload_limit']) {
                return ['success' => false, 'message' => 'Достигнут лимит загрузки'];
            }
            
            // Ограничение на количество файлов
            if (count($files) > 50) {
                return ['success' => false, 'message' => 'Максимум 50 файлов за раз'];
            }
            
            // Проверяем, не превысит ли загрузка лимит
            if ($currentCount + count($files) > $user['upload_limit']) {
                $allowed = $user['upload_limit'] - $currentCount;
                return ['success' => false, 'message' => "Лимит будет превышен. Можно загрузить только {$allowed} файлов."];
            }
        
            $uploadDirResult = $this->prepareUploadDirectory($userId);
            if (!$uploadDirResult['success']) {
                return ['success' => false, 'message' => $uploadDirResult['message']];
            }
            $uploadDir = $uploadDirResult['path'];
        
            // Обработка каждого файла
            foreach ($files as $index => $file) {
                $fileResult = [
                    'fileName' => $file['name'] ?? 'unknown',
                    'success' => false,
                    'message' => '',
                    'videoId' => null
                ];
                
                error_log("Processing file {$index}: {$fileResult['fileName']}");
                
                // Проверка ошибки загрузки
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errorMsg = $this->getUploadErrorMessage($file['error']);
                    error_log("File upload error for {$fileResult['fileName']}: {$errorMsg} (code: {$file['error']})");
                    $fileResult['message'] = 'Ошибка загрузки: ' . $errorMsg;
                    $results[] = $fileResult;
                    continue;
                }
                
                // Проверка существования временного файла
                if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                    error_log("Temporary file not found for {$fileResult['fileName']}");
                    $fileResult['message'] = 'Временный файл не найден';
                    $results[] = $fileResult;
                    continue;
                }
                
                // Проверка размера
                $maxSize = $this->config['UPLOAD_MAX_SIZE'] ?? (5 * 1024 * 1024 * 1024); // 5GB по умолчанию
                if ($file['size'] > $maxSize) {
                    $maxSizeMB = round($maxSize / 1024 / 1024, 2);
                    $fileSizeMB = round($file['size'] / 1024 / 1024, 2);
                    error_log("File size exceeds limit for {$fileResult['fileName']}: {$fileSizeMB}MB > {$maxSizeMB}MB");
                    $fileResult['message'] = "Размер файла ({$fileSizeMB}MB) превышает максимальный ({$maxSizeMB}MB)";
                    $results[] = $fileResult;
                    continue;
                }
                
                // Проверка типа файла
                $mimeType = $this->detectMimeType($file['tmp_name']);
                if (!$mimeType) {
                    $fileResult['message'] = 'Не удалось определить тип файла';
                    $results[] = $fileResult;
                    continue;
                }
                $extension = $this->mapMimeToExtension($mimeType);
                if (!$extension) {
                    error_log("Invalid file type for {$fileResult['fileName']}: {$mimeType}");
                    $fileResult['message'] = 'Неподдерживаемый тип файла: ' . $mimeType;
                    $results[] = $fileResult;
                    continue;
                }
            
                // Генерация имени файла
                $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
                $filePath = $uploadDir . '/' . $fileName;
            
                // Перемещение файла
                $moved = @move_uploaded_file($file['tmp_name'], $filePath);
                if (!$moved) {
                    error_log("Failed to move uploaded file {$fileResult['fileName']} to {$filePath}");
                    $fileResult['message'] = 'Не удалось сохранить файл на диск';
                    $results[] = $fileResult;
                    continue;
                }
            
                $originalName = $this->sanitizeFileName((string)($file['name'] ?? ('video.' . $extension)));
                $title = $originalName;
                if ($titleTemplate) {
                    $title = str_replace(['{index}', '{filename}'], [$index + 1, $originalName], $titleTemplate);
                }
            
            // Сохранение в БД
            try {
                $videoId = $this->videoRepo->create([
                    'user_id' => $userId,
                    'file_path' => $filePath,
                    'file_name' => $originalName,
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
                $fileResult['message'] = 'Failed to save video record';
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
            
            error_log("uploadMultipleVideos completed: {$successCount} of {$totalCount} files uploaded successfully");
            
            return [
                'success' => $successCount > 0,
                'message' => "Загружено {$successCount} из {$totalCount} файлов",
                'data' => [
                    'results' => $results,
                    'successCount' => $successCount,
                    'totalCount' => $totalCount,
                    'videoIds' => $uploadedVideoIds
                ]
            ];
        } catch (\Exception $e) {
            error_log('Exception in uploadMultipleVideos: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Произошла ошибка при загрузке.'
            ];
        }
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
     * Подготовить директорию загрузки для пользователя
     */
    private function prepareUploadDirectory(int $userId): array
    {
        $baseDir = rtrim($this->config['UPLOAD_DIR'] ?? '', '/\\');
        if ($baseDir === '') {
            return [
                'success' => false,
                'message' => 'UPLOAD_DIR не настроен. Укажите директорию для загрузки.'
            ];
        }

        $resolvedBase = is_dir($baseDir) ? (realpath($baseDir) ?: $baseDir) : $baseDir;
        $uploadDir = $resolvedBase . '/' . $userId;

        error_log('Video Upload: Upload dir = ' . $uploadDir);
        error_log('Video Upload: Base dir = ' . $resolvedBase);

        if (!is_dir($resolvedBase)) {
            if (!@mkdir($resolvedBase, 0755, true)) {
                $error = error_get_last();
                error_log('Video Upload: Failed to create base directory: ' . $resolvedBase);
                error_log('Video Upload: Error: ' . ($error['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'message' => 'Не удалось создать директорию для загрузки: ' . $resolvedBase
                ];
            }
        }

        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                $error = error_get_last();
                error_log('Video Upload: Failed to create user directory: ' . $uploadDir);
                error_log('Video Upload: Error: ' . ($error['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'message' => 'Не удалось создать директорию пользователя для загрузки'
                ];
            }
        }

        if (!is_writable($uploadDir)) {
            error_log('Video Upload: Directory not writable: ' . $uploadDir);
            return [
                'success' => false,
                'message' => 'Директория для загрузки недоступна для записи: ' . $uploadDir
            ];
        }

        return ['success' => true, 'path' => $uploadDir];
    }

    private function detectMimeType(string $tmpPath): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        return $mimeType ?: null;
    }

    private function mapMimeToExtension(string $mimeType): ?string
    {
        $allowedTypes = $this->config['ALLOWED_VIDEO_TYPES'] ?? [
            'video/mp4',
            'video/webm',
            'video/ogg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska'
        ];

        if (!in_array($mimeType, $allowedTypes, true)) {
            return null;
        }

        $map = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
        ];

        return $map[$mimeType] ?? null;
    }

    private function sanitizeFileName(string $name): string
    {
        $name = preg_replace('/[^\w\.\- ]+/u', '', $name);
        $name = trim($name);
        if ($name === '') {
            return 'video';
        }
        return $name;
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
