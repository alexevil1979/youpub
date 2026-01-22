<?php

namespace App\Controllers;

use Core\Controller;
use App\Repositories\VideoRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\PublicationRepository;

/**
 * Контроллер главной страницы
 */
class DashboardController extends Controller
{
    private VideoRepository $videoRepo;
    private ScheduleRepository $scheduleRepo;
    private PublicationRepository $publicationRepo;

    public function __construct()
    {
        parent::__construct();
        $this->videoRepo = new VideoRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->publicationRepo = new PublicationRepository();
    }

    /**
     * Главная страница дашборда
     */
    public function index(): void
    {
        $userId = $_SESSION['user_id'];
        
        $stats = [
            'videos_total' => count($this->videoRepo->findByUserId($userId)),
            'schedules_pending' => count($this->scheduleRepo->findByUserIdAndStatus($userId, 'pending')),
            'publications_success' => count($this->publicationRepo->findByUserIdAndStatus($userId, 'success')),
            'publications_failed' => count($this->publicationRepo->findByUserIdAndStatus($userId, 'failed')),
        ];

        $recentVideos = $this->videoRepo->findByUserId($userId, ['created_at' => 'DESC'], 5);
        $upcomingSchedules = $this->scheduleRepo->findUpcoming($userId, 5);

        include __DIR__ . '/../../views/dashboard/index.php';
    }

    /**
     * Профиль пользователя
     */
    public function profile(): void
    {
        include __DIR__ . '/../../views/dashboard/profile.php';
    }

    /**
     * Интеграции
     */
    public function integrations(): void
    {
        include __DIR__ . '/../../views/dashboard/integrations.php';
    }

    /**
     * Подключение YouTube
     */
    public function youtubeConnect(): void
    {
        // TODO: Реализовать OAuth flow для YouTube
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от YouTube
     */
    public function youtubeCallback(): void
    {
        // TODO: Обработка OAuth callback
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Telegram
     */
    public function telegramConnect(): void
    {
        // TODO: Реализовать подключение Telegram бота
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение TikTok
     */
    public function tiktokConnect(): void
    {
        // TODO: Реализовать OAuth flow для TikTok
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от TikTok
     */
    public function tiktokCallback(): void
    {
        // TODO: Обработка OAuth callback
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Instagram
     */
    public function instagramConnect(): void
    {
        // TODO: Реализовать OAuth flow для Instagram
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от Instagram
     */
    public function instagramCallback(): void
    {
        // TODO: Обработка OAuth callback
        header('Location: /integrations');
        exit;
    }

    /**
     * Подключение Pinterest
     */
    public function pinterestConnect(): void
    {
        // TODO: Реализовать OAuth flow для Pinterest
        header('Location: /integrations');
        exit;
    }

    /**
     * Callback от Pinterest
     */
    public function pinterestCallback(): void
    {
        // TODO: Обработка OAuth callback
        header('Location: /integrations');
        exit;
    }

    /**
     * Статистика
     */
    public function statistics(): void
    {
        include __DIR__ . '/../../views/dashboard/statistics.php';
    }

    /**
     * История публикаций
     */
    public function publications(): void
    {
        $userId = $_SESSION['user_id'];
        $publications = $this->publicationRepo->findByUserId($userId, ['published_at' => 'DESC']);
        
        include __DIR__ . '/../../views/dashboard/publications.php';
    }
}
