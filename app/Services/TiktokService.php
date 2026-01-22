<?php

namespace App\Services;

use Core\Service;
use App\Repositories\TiktokIntegrationRepository;
use App\Repositories\PublicationRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\VideoRepository;

/**
 * Сервис для работы с TikTok API
 */
class TiktokService extends Service
{
    private TiktokIntegrationRepository $integrationRepo;
    private PublicationRepository $publicationRepo;
    private ScheduleRepository $scheduleRepo;
    private VideoRepository $videoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->integrationRepo = new TiktokIntegrationRepository();
        $this->publicationRepo = new PublicationRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->videoRepo = new VideoRepository();
    }

    /**
     * Публикация видео на TikTok
     */
    public function publishVideo(int $scheduleId): array
    {
        $schedule = $this->scheduleRepo->findById($scheduleId);
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        $integration = $this->integrationRepo->findByUserId($schedule['user_id']);
        if (!$integration || $integration['status'] !== 'connected') {
            return ['success' => false, 'message' => 'TikTok integration not connected'];
        }

        $video = $this->videoRepo->findById($schedule['video_id']);
        if (!$video || !file_exists($video['file_path'])) {
            return ['success' => false, 'message' => 'Video file not found'];
        }

        $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);

        try {
            // TODO: Реализовать загрузку видео через TikTok Content API
            // Использовать TikTok for Developers API
            
            $videoId = 'MOCK_TIKTOK_VIDEO_ID';
            $videoUrl = 'https://www.tiktok.com/@username/video/' . $videoId;

            $publicationId = $this->publicationRepo->create([
                'schedule_id' => $scheduleId,
                'user_id' => $schedule['user_id'],
                'video_id' => $schedule['video_id'],
                'platform' => 'tiktok',
                'platform_id' => $videoId,
                'platform_url' => $videoUrl,
                'status' => 'success',
                'published_at' => date('Y-m-d H:i:s'),
            ]);

            $this->scheduleRepo->update($scheduleId, ['status' => 'published']);

            return [
                'success' => true,
                'message' => 'Video published successfully',
                'data' => ['publication_id' => $publicationId, 'video_url' => $videoUrl]
            ];

        } catch (\Exception $e) {
            $this->scheduleRepo->update($scheduleId, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            $this->publicationRepo->create([
                'schedule_id' => $scheduleId,
                'user_id' => $schedule['user_id'],
                'video_id' => $schedule['video_id'],
                'platform' => 'tiktok',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
