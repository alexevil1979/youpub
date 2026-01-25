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

        // Проверяем, не публикуется ли уже это расписание
        // Проверяем, есть ли уже успешная публикация для этого расписания
        $existingPublication = $this->publicationRepo->findByScheduleId($scheduleId);
        if ($existingPublication && $existingPublication['status'] === 'success') {
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
        
        // Проверяем, не публикуется ли уже это видео для этого расписания
        if ($schedule['status'] === 'processing') {
            // Проверяем, есть ли другие активные публикации для этого видео в последние 2 минуты
            $stmt = $this->db->prepare("
                SELECT id 
                FROM publications 
                WHERE video_id = ? 
                AND platform = 'youtube'
                AND status = 'success'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                LIMIT 1
            ");
            $stmt->execute([$schedule['video_id']]);
            if ($stmt->fetch()) {
                error_log("YoutubeService::publishVideo: Video {$schedule['video_id']} was recently published to YouTube");
                return [
                    'success' => false,
                    'message' => 'This video was recently published to YouTube'
                ];
            }
        }

        // Используем данные из видео (могут быть обновлены шаблоном)
        $title = $video['title'] ?? 'Untitled Video';
        $description = $video['description'] ?? '';
        $tags = $video['tags'] ?? '';

        error_log("YoutubeService::publishVideo: Publishing with title: " . mb_substr($title, 0, 100));
        error_log("YoutubeService::publishVideo: Publishing with description: " . mb_substr($description, 0, 100));
        error_log("YoutubeService::publishVideo: Publishing with tags: " . mb_substr($tags, 0, 200));

        // Обновление статуса расписания только если он еще не 'processing'
        if ($schedule['status'] !== 'processing') {
            $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);
        }

        try {
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
