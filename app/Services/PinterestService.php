<?php

namespace App\Services;

use Core\Service;
use App\Repositories\PinterestIntegrationRepository;
use App\Repositories\PublicationRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\VideoRepository;

/**
 * Сервис для работы с Pinterest API
 */
class PinterestService extends Service
{
    private PinterestIntegrationRepository $integrationRepo;
    private PublicationRepository $publicationRepo;
    private ScheduleRepository $scheduleRepo;
    private VideoRepository $videoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->integrationRepo = new PinterestIntegrationRepository();
        $this->publicationRepo = new PublicationRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->videoRepo = new VideoRepository();
    }

    /**
     * Публикация Idea Pin / Video Pin на Pinterest
     */
    public function publishPin(int $scheduleId): array
    {
        $schedule = $this->scheduleRepo->findById($scheduleId);
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        $integration = $this->integrationRepo->findByUserId($schedule['user_id']);
        if (!$integration || $integration['status'] !== 'connected') {
            return ['success' => false, 'message' => 'Pinterest integration not connected'];
        }

        $video = $this->videoRepo->findById($schedule['video_id']);
        if (!$video || !file_exists($video['file_path'])) {
            return ['success' => false, 'message' => 'Video file not found'];
        }

        $this->scheduleRepo->update($scheduleId, ['status' => 'processing']);

        try {
            $message = 'Pinterest publishing is not implemented';
            $this->scheduleRepo->update($scheduleId, [
                'status' => 'failed',
                'error_message' => $message
            ]);

            $this->publicationRepo->create([
                'schedule_id' => $scheduleId,
                'user_id' => $schedule['user_id'],
                'video_id' => $schedule['video_id'],
                'platform' => 'pinterest',
                'status' => 'failed',
                'error_message' => $message,
            ]);

            return ['success' => false, 'message' => $message];

        } catch (\Exception $e) {
            $this->scheduleRepo->update($scheduleId, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            $this->publicationRepo->create([
                'schedule_id' => $scheduleId,
                'user_id' => $schedule['user_id'],
                'video_id' => $schedule['video_id'],
                'platform' => 'pinterest',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
