<?php

namespace App\Controllers\Admin;

use Core\Controller;
use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\PublicationRepository;

/**
 * Админ контроллер дашборда
 */
class AdminDashboardController extends Controller
{
    private UserRepository $userRepo;
    private VideoRepository $videoRepo;
    private ScheduleRepository $scheduleRepo;
    private PublicationRepository $publicationRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->userRepo = new UserRepository();
        $this->videoRepo = new VideoRepository();
        $this->scheduleRepo = new ScheduleRepository();
        $this->publicationRepo = new PublicationRepository();
    }

    /**
     * Главная страница админки
     */
    public function index(): void
    {
        $stats = [
            'users_total' => count($this->userRepo->findAll()),
            'videos_total' => count($this->videoRepo->findAll()),
            'schedules_pending' => count($this->scheduleRepo->findAll(['status' => 'pending'])),
            'schedules_processing' => count($this->scheduleRepo->findAll(['status' => 'processing'])),
            'publications_success' => count($this->publicationRepo->findAll(['status' => 'success'])),
            'publications_failed' => count($this->publicationRepo->findAll(['status' => 'failed'])),
        ];

        $recentPublications = $this->publicationRepo->findAll([], ['published_at' => 'DESC'], 10);

        include __DIR__ . '/../../../views/admin/dashboard.php';
    }

    /**
     * Настройки системы
     */
    public function settings(): void
    {
        include __DIR__ . '/../../../views/admin/settings.php';
    }

    /**
     * Обновить настройки
     */
    public function updateSettings(): void
    {
        // TODO: Реализовать сохранение настроек
        header('Location: /admin/settings');
        exit;
    }
}
