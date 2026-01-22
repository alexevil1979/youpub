<?php

namespace App\Controllers\Admin;

use Core\Controller;
use App\Repositories\VideoRepository;

/**
 * Админ контроллер видео
 */
class AdminVideosController extends Controller
{
    private VideoRepository $videoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->videoRepo = new VideoRepository();
    }

    /**
     * Список всех видео
     */
    public function index(): void
    {
        $videos = $this->videoRepo->findAll([], ['created_at' => 'DESC']);
        include __DIR__ . '/../../../views/admin/videos/index.php';
    }

    /**
     * Показать видео
     */
    public function show(int $id): void
    {
        $video = $this->videoRepo->findById($id);
        if (!$video) {
            http_response_code(404);
            echo 'Video not found';
            return;
        }
        include __DIR__ . '/../../../views/admin/videos/show.php';
    }
}
