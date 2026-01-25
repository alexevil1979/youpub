<?php

namespace App\Services;

use Core\Service;
use App\Repositories\YoutubeIntegrationRepository;
use App\Repositories\PublicationRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\VideoRepository;

/**
 * Сервис для работы с YouTube API
 */
class YoutubeService extends Service
{
    private YoutubeIntegrationRepository $integrationRepo;
    private PublicationRepository $publicationRepo;
    private ScheduleRepository $scheduleRepo;
    private VideoRepository $videoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->integrationRepo = new YoutubeIntegrationRepository();
        $this->publicationRepo = new PublicationRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->videoRepo = new VideoRepository();
    }

    /**
     * Публикация видео на YouTube
     */
    public function publishVideo(int $scheduleId): array
    {
        error_log("YoutubeService::publishVideo: Called for schedule ID: {$scheduleId}");
        
        // КРИТИЧЕСКАЯ проверка: блокируем расписание сразу, чтобы предотвратить параллельные вызовы
        $this->db->beginTransaction();
        try {
            $schedule = $this->scheduleRepo->findById($scheduleId);
            if (!$schedule) {
                $this->db->rollBack();
                error_log("YoutubeService::publishVideo: Schedule {$scheduleId} not found");
                return ['success' => false, 'message' => 'Schedule not found'];
            }

            // Блокируем расписание для обновления
            $lockStmt = $this->db->prepare("SELECT * FROM schedules WHERE id = ? FOR UPDATE");
            $lockStmt->execute([$scheduleId]);
            $lockedSchedule = $lockStmt->fetch();
            
            if (!$lockedSchedule) {
                $this->db->rollBack();
                error_log("YoutubeService::publishVideo: Schedule {$scheduleId} not found after lock");
                return ['success' => false, 'message' => 'Schedule not found'];
            }

            // Проверяем, не обрабатывается ли уже это расписание
            if ($lockedSchedule['status'] === 'processing') {
                // Сначала проверяем, есть ли уже успешная публикация для этого расписания
                $existingPub = $this->publicationRepo->findByScheduleId($scheduleId);
                if ($existingPub && $existingPub['status'] === 'success') {
                    $this->db->rollBack();
                    error_log("YoutubeService::publishVideo: Schedule {$scheduleId} already has successful publication (ID: {$existingPub['id']})");
                    return [
                        'success' => true,
                        'message' => 'Video already published',
                        'data' => [
                            'publication_id' => $existingPub['id'],
                            'video_url' => $existingPub['platform_url'] ?? ''
                        ]
                    ];
                }
                
                // Проверяем, не зависло ли оно (используем updated_at если есть, иначе created_at)
                $updatedAt = !empty($lockedSchedule['updated_at']) ? strtotime($lockedSchedule['updated_at']) : strtotime($lockedSchedule['created_at']);
                $now = time();
                $timeSinceUpdate = $now - $updatedAt;
                
                // Если расписание в processing меньше 2 минут - разрешаем обработку (возможно, повторный вызов после ошибки)
                if ($timeSinceUpdate < 120) {
                    error_log("YoutubeService::publishVideo: Schedule {$scheduleId} is in processing but recent ({$timeSinceUpdate}s), allowing retry");
                    // Продолжаем обработку
                } elseif ($timeSinceUpdate < 600) { // 10 минут
                    // Расписание в processing, но не зависло - возможно, другой процесс обрабатывает
                    $this->db->rollBack();
                    error_log("YoutubeService::publishVideo: Schedule {$scheduleId} is already processing (updated {$timeSinceUpdate}s ago)");
                    return ['success' => false, 'message' => 'Schedule is already being processed'];
                } else {
                    // Расписание зависло (старше 10 минут), сбрасываем статус
                    error_log("YoutubeService::publishVideo: Schedule {$scheduleId} was stuck in processing ({$timeSinceUpdate}s), resetting");
                    $this->scheduleRepo->update($scheduleId, [
                        'status' => 'pending',
                        'error_message' => 'Previous processing timed out'
                    ]);
                }
            }

            // Проверяем, не опубликовано ли уже
            if ($lockedSchedule['status'] === 'published') {
                $existingPub = $this->publicationRepo->findByScheduleId($scheduleId);
                if ($existingPub && $existingPub['status'] === 'success') {
                    $this->db->rollBack();
                    error_log("YoutubeService::publishVideo: Schedule {$scheduleId} already published");
                    return [
                        'success' => true,
                        'message' => 'Video already published',
                        'data' => [
                            'publication_id' => $existingPub['id'],
                            'video_url' => $existingPub['platform_url'] ?? ''
                        ]
                    ];
                }
            }

            // Обновляем статус на processing ВНУТРИ транзакции
            if ($lockedSchedule['status'] !== 'processing') {
                $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);
            }
            
            // Продолжаем проверки в той же транзакции
            // 1. Проверяем, есть ли уже успешная публикация для этого расписания
            $existingPublication = $this->publicationRepo->findByScheduleId($scheduleId);
            if ($existingPublication && $existingPublication['status'] === 'success') {
                $this->db->rollBack();
                error_log("YoutubeService::publishVideo: Schedule {$scheduleId} already has successful publication (ID: {$existingPublication['id']})");
                return [
                    'success' => true,
                    'message' => 'Video already published',
                    'data' => [
                        'publication_id' => $existingPublication['id'],
                        'video_url' => $existingPublication['platform_url'] ?? ''
                    ]
                ];
            }
            
            // 2. Блокируем только активные расписания для этого видео (processing, pending)
            $scheduleStmt = $this->db->prepare("
                SELECT id, status, created_at 
                FROM schedules 
                WHERE video_id = ? 
                AND status IN ('processing', 'pending')
                AND id != ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                FOR UPDATE
            ");
            $scheduleStmt->execute([$schedule['video_id'], $scheduleId]);
            $otherActiveSchedules = $scheduleStmt->fetchAll();
            
            if (!empty($otherActiveSchedules)) {
                $this->db->rollBack();
                error_log("YoutubeService::publishVideo: Video {$schedule['video_id']} has other active schedule(s): " . count($otherActiveSchedules));
                foreach ($otherActiveSchedules as $os) {
                    error_log("YoutubeService::publishVideo: Other schedule ID: {$os['id']}, status: {$os['status']}, created: {$os['created_at']}");
                }
                // Отменяем это расписание
                $this->scheduleRepo->update($scheduleId, [
                    'status' => 'cancelled',
                    'error_message' => 'Another schedule is processing this video'
                ]);
                return [
                    'success' => false,
                    'message' => 'Another publication is already in progress for this video'
                ];
            }
            
            // 3. Проверяем успешные публикации только за последние 2 минуты
            $pubStmt = $this->db->prepare("
                SELECT id, platform_id, created_at 
                FROM publications 
                WHERE video_id = ? 
                AND platform = 'youtube'
                AND status = 'success'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $pubStmt->execute([$schedule['video_id']]);
            $recentPublication = $pubStmt->fetch();
            if ($recentPublication) {
                $this->db->rollBack();
                error_log("YoutubeService::publishVideo: Video {$schedule['video_id']} was just published to YouTube (publication ID: {$recentPublication['id']}, created: {$recentPublication['created_at']})");
                // Обновляем статус расписания на published
                $this->scheduleRepo->update($scheduleId, [
                    'status' => 'published',
                    'error_message' => 'Duplicate publication prevented - video was just published'
                ]);
                return [
                    'success' => true,
                    'message' => 'Video already published (duplicate prevented)',
                    'data' => [
                        'publication_id' => $recentPublication['id'],
                        'video_url' => 'https://youtube.com/watch?v=' . ($recentPublication['platform_id'] ?? '')
                    ]
                ];
            }
            
            // Все проверки пройдены, обновляем статус на 'processing' ВНУТРИ транзакции
            if ($lockedSchedule['status'] !== 'processing') {
                $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);
            }
            
            // Коммитим транзакцию ТОЛЬКО после всех проверок и обновления статуса
            $this->db->commit();
            $schedule = $lockedSchedule; // Используем заблокированную версию
            error_log("YoutubeService::publishVideo: All duplicate checks passed for schedule {$scheduleId}, status set to processing, proceeding with publication");
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("YoutubeService::publishVideo: Error in lock and duplicate check: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error checking for duplicates: ' . $e->getMessage()];
        }

        // Поддержка мультиаккаунтов: используем integration_id из расписания или аккаунт по умолчанию
        $integration = null;
        if (!empty($schedule['integration_id']) && !empty($schedule['integration_type']) && $schedule['integration_type'] === 'youtube') {
            $integration = $this->integrationRepo->findByIdAndUserId($schedule['integration_id'], $schedule['user_id']);
        }
        
        if (!$integration) {
            $integration = $this->integrationRepo->findDefaultByUserId($schedule['user_id']);
        }
        
        if (!$integration || $integration['status'] !== 'connected') {
            $this->scheduleRepo->update($scheduleId, [
                'status' => 'failed',
                'error_message' => 'YouTube integration not connected'
            ]);
            return ['success' => false, 'message' => 'YouTube integration not connected'];
        }

        // Получаем видео
        $video = $this->videoRepo->findById($schedule['video_id']);
        if (!$video || !file_exists($video['file_path'])) {
            $this->scheduleRepo->update($scheduleId, [
                'status' => 'failed',
                'error_message' => 'Video file not found'
            ]);
            return ['success' => false, 'message' => 'Video file not found'];
        }

        // Используем данные из видео (могут быть обновлены шаблоном)
        // ВАЖНО: Проверяем, что метаданные не пустые, иначе используем fallback
        $title = trim($video['title'] ?? '');
        if (empty($title) || $title === 'Untitled Video' || strtolower($title) === 'unknown') {
            $title = $video['file_name'] ?? 'Untitled Video';
            error_log("YoutubeService::publishVideo: Title was empty/unknown, using file_name: {$title}");
        }
        
        $description = trim($video['description'] ?? '');
        if (empty($description)) {
            $description = 'Watch this video! 🎬';
            error_log("YoutubeService::publishVideo: Description was empty, using fallback");
        }
        
        $tags = trim($video['tags'] ?? '');

        error_log("YoutubeService::publishVideo: Publishing with title: " . mb_substr($title, 0, 100));
        error_log("YoutubeService::publishVideo: Publishing with description: " . mb_substr($description, 0, 100));
        error_log("YoutubeService::publishVideo: Publishing with tags: " . mb_substr($tags, 0, 200));
        
        // Дополнительная проверка: если метаданные все еще пустые, это проблема
        if (empty($title) || strtolower($title) === 'unknown') {
            error_log("YoutubeService::publishVideo: WARNING - Title is still empty/unknown after fallback!");
        }
        if (empty($description)) {
            error_log("YoutubeService::publishVideo: WARNING - Description is still empty after fallback!");
        }

        try {
            // ФИНАЛЬНАЯ проверка перед загрузкой: убеждаемся, что расписание все еще в статусе 'processing'
            // и нет других активных расписаний (на случай, если что-то изменилось между проверкой и загрузкой)
            $finalCheckStmt = $this->db->prepare("
                SELECT id, status 
                FROM schedules 
                WHERE video_id = ? 
                AND status IN ('processing', 'pending')
                AND id != ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                LIMIT 1
            ");
            $finalCheckStmt->execute([$schedule['video_id'], $scheduleId]);
            $finalCheck = $finalCheckStmt->fetch();
            
            if ($finalCheck) {
                error_log("YoutubeService::publishVideo: Found other active schedule (ID: {$finalCheck['id']}) before upload, cancelling schedule {$scheduleId}");
                $this->scheduleRepo->update($scheduleId, [
                    'status' => 'cancelled',
                    'error_message' => 'Another schedule found before upload'
                ]);
                return [
                    'success' => false,
                    'message' => 'Another publication is already in progress for this video'
                ];
            }
            
            // Проверяем, что расписание все еще в статусе 'processing'
            $currentSchedule = $this->scheduleRepo->findById($scheduleId);
            if (!$currentSchedule || $currentSchedule['status'] !== 'processing') {
                error_log("YoutubeService::publishVideo: Schedule {$scheduleId} status changed to '{$currentSchedule['status']}', aborting upload");
                return [
                    'success' => false,
                    'message' => 'Schedule status changed, publication cancelled'
                ];
            }
            
            // Проверяем и обновляем токен при необходимости
            $accessToken = $this->getValidAccessToken($integration);
            if (!$accessToken) {
                throw new \Exception('Failed to get valid access token');
            }

            // Загружаем видео на YouTube
            $uploadResult = $this->uploadVideoToYouTube(
                $accessToken,
                $video['file_path'],
                $title,
                $description,
                $tags
            );

            if (!$uploadResult['success']) {
                throw new \Exception($uploadResult['message'] ?? 'Failed to upload video');
            }

            $videoId = $uploadResult['video_id'];
            $videoUrl = 'https://youtube.com/watch?v=' . $videoId;

            // Создание записи о публикации
            $publicationId = $this->publicationRepo->create([
                'schedule_id' => $scheduleId,
                'user_id' => $schedule['user_id'],
                'video_id' => $schedule['video_id'],
                'platform' => 'youtube',
                'platform_id' => $videoId,
                'platform_url' => $videoUrl,
                'status' => 'success',
                'published_at' => date('Y-m-d H:i:s'),
            ]);

            // Обновление статуса расписания
            $this->scheduleRepo->update($scheduleId, ['status' => 'published']);

            return [
                'success' => true,
                'message' => 'Video published successfully',
                'data' => ['publication_id' => $publicationId, 'video_url' => $videoUrl]
            ];

        } catch (\Exception $e) {
            // Обновление статуса расписания
            $this->scheduleRepo->update($scheduleId, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            // Создание записи о неудачной публикации
            $this->publicationRepo->create([
                'schedule_id' => $scheduleId,
                'user_id' => $schedule['user_id'],
                'video_id' => $schedule['video_id'],
                'platform' => 'youtube',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Получить валидный access token (обновить при необходимости)
     */
    private function getValidAccessToken(array $integration): ?string
    {
        // Проверяем, не истек ли токен
        if ($integration['token_expires_at'] && strtotime($integration['token_expires_at']) > time()) {
            return $integration['access_token'];
        }

        // Обновляем токен
        if (!$integration['refresh_token']) {
            error_log('YouTube: No refresh token available');
            return null;
        }

        $newToken = $this->refreshAccessToken($integration['refresh_token']);
        if ($newToken) {
            // Обновляем в БД
            $this->integrationRepo->update($integration['id'], [
                'access_token' => $newToken['access_token'],
                'token_expires_at' => isset($newToken['expires_in']) 
                    ? date('Y-m-d H:i:s', time() + $newToken['expires_in']) 
                    : null,
            ]);
            return $newToken['access_token'];
        }

        return null;
    }

    /**
     * Обновить access token
     */
    private function refreshAccessToken(string $refreshToken): ?array
    {
        $clientId = $this->config['YOUTUBE_CLIENT_ID'];
        $clientSecret = $this->config['YOUTUBE_CLIENT_SECRET'];

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $tokenData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($tokenData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        error_log('YouTube: Failed to refresh token. HTTP Code: ' . $httpCode);
        return null;
    }

    /**
     * Загрузить видео на YouTube
     */
    private function uploadVideoToYouTube(
        string $accessToken,
        string $videoPath,
        string $title,
        string $description,
        string $tags
    ): array {
        // Проверяем, что файл существует и доступен
        if (!file_exists($videoPath) || !is_readable($videoPath)) {
            error_log("YoutubeService::uploadVideoToYouTube: Video file not found or not readable: {$videoPath}");
            return ['success' => false, 'message' => 'Video file not found or not readable'];
        }

        $fileSize = filesize($videoPath);
        if ($fileSize === false || $fileSize === 0) {
            error_log("YoutubeService::uploadVideoToYouTube: Invalid file size for: {$videoPath}");
            return ['success' => false, 'message' => 'Invalid video file size'];
        }

        error_log("YoutubeService::uploadVideoToYouTube: Starting upload. File: {$videoPath}, Size: {$fileSize} bytes");

        // Создаем метаданные видео
        $categoryId = (string)($this->config['YOUTUBE_CATEGORY_ID'] ?? '22');
        if (!preg_match('/^\d+$/', $categoryId)) {
            $categoryId = '22';
        }
        $snippet = [
            'title' => $title,
            'description' => $description,
            'tags' => !empty($tags) ? explode(',', $tags) : [],
            'categoryId' => $categoryId,
        ];

        $status = [
            'privacyStatus' => 'public',
        ];

        $videoData = [
            'snippet' => $snippet,
            'status' => $status,
        ];

        // Используем multipart upload (более надежный метод)
        // Это предотвращает создание видео без файла
        return $this->uploadVideoMultipart($accessToken, $videoPath, $title, $description, $tags, $videoData);
    }

    /**
     * Загрузка через multipart (основной метод)
     */
    private function uploadVideoMultipart(
        string $accessToken,
        string $videoPath,
        string $title,
        string $description,
        string $tags,
        array $videoData
    ): array {
        $boundary = uniqid('boundary_');
        $delimiter = '-------------' . $boundary;

        // Читаем файл
        $fileHandle = fopen($videoPath, 'rb');
        if (!$fileHandle) {
            error_log("YoutubeService::uploadVideoMultipart: Failed to open file: {$videoPath}");
            return ['success' => false, 'message' => 'Failed to open video file'];
        }

        $fileSize = filesize($videoPath);
        $metadataJson = json_encode($videoData);

        // Формируем multipart данные
        $postData = '';
        $postData .= '--' . $delimiter . "\r\n";
        $postData .= 'Content-Disposition: form-data; name="metadata"' . "\r\n";
        $postData .= 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n";
        $postData .= $metadataJson . "\r\n";
        $postData .= '--' . $delimiter . "\r\n";
        $postData .= 'Content-Disposition: form-data; name="video"; filename="' . basename($videoPath) . '"' . "\r\n";
        $postData .= 'Content-Type: video/*' . "\r\n\r\n";

        // Вычисляем размер данных
        $metadataSize = strlen($postData);
        $footer = "\r\n--" . $delimiter . "--\r\n";
        $footerSize = strlen($footer);
        $totalSize = $metadataSize + $fileSize + $footerSize;

        $url = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=multipart&part=snippet,status';
        
        error_log("YoutubeService::uploadVideoMultipart: Uploading to YouTube. Total size: {$totalSize} bytes");

        $ch = curl_init($url);
        
        // Используем CURLFile для загрузки файла
        $cfile = new \CURLFile($videoPath, 'video/*', basename($videoPath));
        
        $postFields = [
            'metadata' => $metadataJson,
            'video' => $cfile
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 600, // 10 минут для больших файлов
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fileHandle);

        if ($curlError) {
            error_log("YoutubeService::uploadVideoMultipart: cURL error: {$curlError}");
            return ['success' => false, 'message' => 'cURL error: ' . $curlError];
        }

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['id'])) {
                error_log("YoutubeService::uploadVideoMultipart: Video uploaded successfully. Video ID: {$data['id']}");
                return [
                    'success' => true,
                    'video_id' => $data['id'],
                ];
            } else {
                error_log("YoutubeService::uploadVideoMultipart: Response missing video ID. Response: " . substr($response, 0, 500));
                return ['success' => false, 'message' => 'Response missing video ID'];
            }
        } else {
            error_log("YoutubeService::uploadVideoMultipart: Upload failed. HTTP Code: {$httpCode}, Response: " . substr($response, 0, 500));
            return ['success' => false, 'message' => 'Failed to upload video to YouTube. HTTP Code: ' . $httpCode];
        }
    }

    /**
     * Простая загрузка видео (альтернативный метод)
     */
    private function uploadVideoSimple(
        string $accessToken,
        string $videoPath,
        string $title,
        string $description,
        string $tags
    ): array {
        // Создаем multipart/form-data запрос
        $boundary = uniqid();
        $delimiter = '-------------' . $boundary;

        $snippet = [
            'title' => $title,
            'description' => $description,
            'tags' => !empty($tags) ? explode(',', $tags) : [],
            'categoryId' => '22',
        ];

        $status = ['privacyStatus' => 'public'];

        $videoData = [
            'snippet' => $snippet,
            'status' => $status,
        ];

        // Формируем multipart данные
        $postData = '';
        $postData .= '--' . $delimiter . "\r\n";
        $postData .= 'Content-Disposition: form-data; name="metadata"' . "\r\n";
        $postData .= 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n";
        $postData .= json_encode($videoData) . "\r\n";
        $postData .= '--' . $delimiter . "\r\n";
        $postData .= 'Content-Disposition: form-data; name="video"; filename="' . basename($videoPath) . '"' . "\r\n";
        $postData .= 'Content-Type: video/*' . "\r\n\r\n";
        $postData .= file_get_contents($videoPath) . "\r\n";
        $postData .= '--' . $delimiter . '--';

        $url = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=multipart&part=snippet,status';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: multipart/related; boundary=' . $delimiter,
                'Content-Length: ' . strlen($postData),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('YouTube upload cURL error: ' . $error);
            return ['success' => false, 'message' => 'cURL error: ' . $error];
        }

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['id'])) {
                return [
                    'success' => true,
                    'video_id' => $data['id'],
                ];
            }
        }

        error_log('YouTube upload failed. HTTP Code: ' . $httpCode . ', Response: ' . substr($response, 0, 1000));
        return ['success' => false, 'message' => 'Failed to upload video. HTTP Code: ' . $httpCode];
    }
}
