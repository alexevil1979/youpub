<?php

namespace App\Services;

use Core\Service;
use App\Repositories\TelegramIntegrationRepository;
use App\Repositories\PublicationRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\VideoRepository;

/**
 * Сервис для работы с Telegram API
 */
class TelegramService extends Service
{
    private TelegramIntegrationRepository $integrationRepo;
    private PublicationRepository $publicationRepo;
    private ScheduleRepository $scheduleRepo;
    private VideoRepository $videoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->integrationRepo = new TelegramIntegrationRepository();
        $this->publicationRepo = new PublicationRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->videoRepo = new VideoRepository();
    }

    /**
     * Публикация видео в Telegram
     */
    public function publishVideo(int $scheduleId): array
    {
        $schedule = $this->scheduleRepo->findById($scheduleId);
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        // Поддержка мультиаккаунтов: используем integration_id из расписания или аккаунт по умолчанию
        $integration = null;
        if (!empty($schedule['integration_id']) && !empty($schedule['integration_type']) && $schedule['integration_type'] === 'telegram') {
            $integration = $this->integrationRepo->findByIdAndUserId($schedule['integration_id'], $schedule['user_id']);
        }
        
        if (!$integration) {
            $integration = $this->integrationRepo->findDefaultByUserId($schedule['user_id']);
        }
        
        if (!$integration || $integration['status'] !== 'connected') {
            return ['success' => false, 'message' => 'Telegram integration not connected'];
        }

        $video = $this->videoRepo->findById($schedule['video_id']);
        if (!$video || !file_exists($video['file_path'])) {
            return ['success' => false, 'message' => 'Video file not found'];
        }

        // Используем данные из видео (могут быть обновлены шаблоном)
        $caption = $video['description'] ?? '';

        // Обновление статуса расписания
        $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);

        try {
            $botToken = $integration['bot_token'];
            $channelId = $integration['channel_id'];
            
            // Отправка видео в канал
            $result = $this->sendVideoToChannel($botToken, $channelId, $video['file_path'], $caption);

            if ($result['success']) {
                // Создание записи о публикации
                $publicationId = $this->publicationRepo->create([
                    'schedule_id' => $scheduleId,
                    'user_id' => $schedule['user_id'],
                    'video_id' => $schedule['video_id'],
                    'platform' => 'telegram',
                    'platform_id' => $result['message_id'] ?? null,
                    'platform_url' => $result['url'] ?? null,
                    'status' => 'success',
                    'published_at' => date('Y-m-d H:i:s'),
                ]);

                // Обновление статуса расписания
                $this->scheduleRepo->update($scheduleId, ['status' => 'published']);

                return [
                    'success' => true,
                    'message' => 'Video published successfully',
                    'data' => ['publication_id' => $publicationId]
                ];
            } else {
                throw new \Exception($result['message'] ?? 'Failed to publish video');
            }

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
                'platform' => 'telegram',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Отправить видео в канал Telegram
     */
    private function sendVideoToChannel(string $botToken, string $channelId, string $videoPath, string $caption = ''): array
    {
        $apiUrl = $this->config['TELEGRAM_API_URL'] . $botToken . '/sendVideo';
        
        // Использование CURL для отправки файла
        $ch = curl_init();
        
        $postFields = [
            'chat_id' => $channelId,
            'video' => new \CURLFile($videoPath),
            'caption' => $caption,
            'supports_streaming' => true,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'CURL error: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => 'HTTP error: ' . $httpCode];
        }

        $data = json_decode($response, true);
        
        if (!$data || !$data['ok']) {
            return ['success' => false, 'message' => $data['description'] ?? 'Unknown error'];
        }

        return [
            'success' => true,
            'message_id' => $data['result']['message_id'] ?? null,
            'url' => 'https://t.me/' . $channelId . '/' . ($data['result']['message_id'] ?? ''),
        ];
    }
}
