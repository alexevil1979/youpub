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
        $schedule = $this->scheduleRepo->findById($scheduleId);
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
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
            return ['success' => false, 'message' => 'YouTube integration not connected'];
        }

        // Получаем видео
        $video = $this->videoRepo->findById($schedule['video_id']);
        if (!$video || !file_exists($video['file_path'])) {
            return ['success' => false, 'message' => 'Video file not found'];
        }

        // СТРОГАЯ проверка на дубликаты перед публикацией с блокировкой
        // Используем транзакцию для атомарной проверки
        $this->db->beginTransaction();
        try {
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
            // Это гарантирует, что только один запрос сможет начать загрузку
            if ($schedule['status'] !== 'processing') {
                $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);
            }
            
            // Коммитим транзакцию ТОЛЬКО после обновления статуса
            $this->db->commit();
            error_log("YoutubeService::publishVideo: All duplicate checks passed for schedule {$scheduleId}, status set to processing, proceeding with publication");
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("YoutubeService::publishVideo: Error in duplicate check transaction: " . $e->getMessage());
            // Не продолжаем публикацию при ошибке проверки
            return [
                'success' => false,
                'message' => 'Error checking for duplicates: ' . $e->getMessage()
            ];
        }

        // Используем данные из видео (могут быть обновлены шаблоном)
        $title = $video['title'] ?? 'Untitled Video';
        $description = $video['description'] ?? '';
        $tags = $video['tags'] ?? '';

        error_log("YoutubeService::publishVideo: Publishing with title: " . mb_substr($title, 0, 100));
        error_log("YoutubeService::publishVideo: Publishing with description: " . mb_substr($description, 0, 100));
        error_log("YoutubeService::publishVideo: Publishing with tags: " . mb_substr($tags, 0, 200));

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
            'privacyStatus' => 'public', // или 'unlisted', 'private'
        ];

        $videoData = [
            'snippet' => $snippet,
            'status' => $status,
        ];

        // Загружаем видео через resumable upload
        // Шаг 1: Инициируем загрузку
        $initUrl = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';
        
        $ch = curl_init($initUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($videoData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'X-Upload-Content-Type: video/*',
                'X-Upload-Content-Length: ' . filesize($videoPath),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $location = null;

        if ($httpCode === 200) {
            // Получаем URL для загрузки из заголовка Location
            $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
            preg_match('/Location: (.+)/i', $response, $matches);
        }

        // Пытаемся получить из заголовков ответа
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        preg_match('/Location:\s*(.+)/i', $headers, $matches);
        
        if (empty($matches[1])) {
            // Альтернативный способ - используем заголовки из curl_getinfo
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            $fullResponse = curl_exec($ch);
            preg_match('/Location:\s*(.+?)(?:\r|\n)/i', $fullResponse, $matches);
        }

        curl_close($ch);

        if (empty($matches[1])) {
            // Используем альтернативный метод - простую загрузку
            return $this->uploadVideoSimple($accessToken, $videoPath, $title, $description, $tags);
        }

        $uploadUrl = trim($matches[1]);

        // Шаг 2: Загружаем файл
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_PUT => true,
            CURLOPT_INFILE => fopen($videoPath, 'rb'),
            CURLOPT_INFILESIZE => filesize($videoPath),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: video/*',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['id'])) {
                return [
                    'success' => true,
                    'video_id' => $data['id'],
                ];
            }
        }

        error_log('YouTube upload failed. HTTP Code: ' . $httpCode . ', Response: ' . substr($response, 0, 500));
        return ['success' => false, 'message' => 'Failed to upload video to YouTube'];
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
