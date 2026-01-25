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
            'users_total' => $this->userRepo->countAll(),
            'videos_total' => $this->videoRepo->countAll(),
            'schedules_pending' => $this->scheduleRepo->countAll(['status' => 'pending']),
            'schedules_processing' => $this->scheduleRepo->countAll(['status' => 'processing']),
            'publications_success' => $this->publicationRepo->countAll(['status' => 'success']),
            'publications_failed' => $this->publicationRepo->countAll(['status' => 'failed']),
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
        if (!$this->validateCsrf()) {
            $_SESSION['error'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
            header('Location: /admin/settings');
            exit;
        }

        $_SESSION['error'] = 'Обновление настроек временно недоступно.';
        header('Location: /admin/settings');
        exit;
    }
}
