<?php

namespace App\Controllers\Api;

use Core\Controller;
use App\Services\VideoService;

/**
 * API контроллер для работы с видео
 */
class VideoApiController extends Controller
{
    private VideoService $videoService;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->videoService = new VideoService();
    }

    /**
     * Список видео
     */
    public function list(): void
    {
        $userId = $_SESSION['user_id'];
        $videos = $this->videoService->getUserVideos($userId);
        $this->success($videos);
    }

    /**
     * Загрузка видео
     */
    public function upload(): void
    {
        $userId = $_SESSION['user_id'];

        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $this->error('File upload failed', 400);
            return;
        }

        $data = $this->getRequestData();
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $tags = $data['tags'] ?? '';

        $result = $this->videoService->uploadVideo(
            $userId,
            $_FILES['video'],
            $title,
            $description,
            $tags
        );

        if ($result['success']) {
            $this->success($result['data'], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Показать видео
     */
    public function show(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $video = $this->videoService->getVideo($id, $userId);

        if (!$video) {
            $this->error('Video not found', 404);
            return;
        }

        $this->success($video);
    }

    /**
     * Удалить видео
     */
    public function delete(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->videoService->deleteVideo($id, $userId);

        if ($result['success']) {
            $this->success([], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }
}
