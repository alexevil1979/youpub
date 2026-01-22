<?php

namespace App\Services;

use Core\Service;
use App\Repositories\InstagramIntegrationRepository;
use App\Repositories\PublicationRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\VideoRepository;

/**
 * Сервис для работы с Instagram API
 */
class InstagramService extends Service
{
    private InstagramIntegrationRepository $integrationRepo;
    private PublicationRepository $publicationRepo;
    private ScheduleRepository $scheduleRepo;
    private VideoRepository $videoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->integrationRepo = new InstagramIntegrationRepository();
        $this->publicationRepo = new PublicationRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->videoRepo = new VideoRepository();
    }

    /**
     * Публикация Reels на Instagram
     */
    public function publishReel(int $scheduleId): array
    {
        $schedule = $this->scheduleRepo->findById($scheduleId);
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        $integration = $this->integrationRepo->findByUserId($schedule['user_id']);
        if (!$integration || $integration['status'] !== 'connected') {
            return ['success' => false, 'message' => 'Instagram integration not connected'];
        }

        $video = $this->videoRepo->findById($schedule['video_id']);
        if (!$video || !file_exists($video['file_path'])) {
            return ['success' => false, 'message' => 'Video file not found'];
        }

        $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);

        try {
            // TODO: Реализовать загрузку Reels через Instagram Graph API
            // Использовать Instagram Basic Display API или Instagram Graph API
            
            $mediaId = 'MOCK_INSTAGRAM_MEDIA_ID';
            $mediaUrl = 'https://www.instagram.com/reel/' . $mediaId;

            $publicationId = $this->publicationRepo->create([
                'schedule_id' => $scheduleId,
                'user_id' => $schedule['user_id'],
                'video_id' => $schedule['video_id'],
                'platform' => 'instagram',
                'platform_id' => $mediaId,
                'platform_url' => $mediaUrl,
                'status' => 'success',
                'published_at' => date('Y-m-d H:i:s'),
            ]);

            $this->scheduleRepo->update($scheduleId, ['status' => 'published']);

            return [
                'success' => true,
                'message' => 'Reel published successfully',
                'data' => ['publication_id' => $publicationId, 'media_url' => $mediaUrl]
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
                'platform' => 'instagram',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
