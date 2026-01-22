<?php

namespace App\Controllers;

use Core\Controller;
use App\Services\VideoService;

/**
 * Контроллер для работы с видео
 */
class VideoController extends Controller
{
    private VideoService $videoService;

    public function __construct()
    {
        parent::__construct();
        $this->videoService = new VideoService();
    }

    /**
     * Список видео
     */
    public function index(): void
    {
        $userId = $_SESSION['user_id'];
        $videos = $this->videoService->getUserVideos($userId);
        
        include __DIR__ . '/../../views/videos/index.php';
    }

    /**
     * Показать форму загрузки
     */
    public function showUpload(): void
    {
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../views/videos/upload.php';
    }

    /**
     * Загрузка видео
     */
    public function upload(): void
    {
        $userId = $_SESSION['user_id'];

        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $this->error('File upload failed');
            return;
        }

        $title = $this->getParam('title', '');
        $description = $this->getParam('description', '');
        $tags = $this->getParam('tags', '');

        $result = $this->videoService->uploadVideo(
            $userId,
            $_FILES['video'],
            $title,
            $description,
            $tags
        );

        if ($result['success']) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->success($result['data'], $result['message']);
            } else {
                header('Location: /videos');
            }
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
            http_response_code(404);
            echo 'Video not found';
            return;
        }

        // Проверяем, что файл существует
        if (!file_exists($video['file_path'])) {
            error_log('Video file not found: ' . $video['file_path']);
        }

        include __DIR__ . '/../../views/videos/show.php';
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
