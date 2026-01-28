<?php

namespace App\Controllers\Admin;

use Core\Controller;
use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\PublicationRepository;
use App\Services\AppSettingsService;

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
        $env = require __DIR__ . '/../../../config/env.php';
        $svc = AppSettingsService::getInstance();
        $sessionSeconds = $svc->getInt('session_lifetime_seconds', (int)($env['SESSION_LIFETIME'] ?? 36000));
        $settings = [
            'session_lifetime_hours' => max(2, (int)round($sessionSeconds / 3600)),
            'session_strict_ip'      => $svc->getBool('session_strict_ip', (bool)($env['SESSION_STRICT_IP'] ?? false)),
            'site_name'              => $svc->get('site_name', $env['APP_NAME'] ?? 'YouPub'),
            'site_url'               => $svc->get('site_url', $env['APP_URL'] ?? 'https://you.1tlt.ru'),
            'seo_title_suffix'       => $svc->get('seo_title_suffix', ' - Автоматическая публикация видео'),
            'seo_default_description'=> $svc->get('seo_default_description', ''),
        ];
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

        error_log('AdminDashboardController::updateSettings — сохранение настроек');

        $hours = isset($_POST['session_lifetime_hours']) ? (int)$_POST['session_lifetime_hours'] : 10;
        $hours = max(2, min(720, $hours)); // 2–720 часов
        $sessionLifetimeSeconds = (string)($hours * 3600);
        $sessionStrictIp = (isset($_POST['session_strict_ip']) && $_POST['session_strict_ip'] === '1') ? '1' : '0';
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        $siteUrl = trim((string)($_POST['site_url'] ?? ''));
        $seoTitleSuffix = trim((string)($_POST['seo_title_suffix'] ?? ''));
        $seoDefaultDescription = trim((string)($_POST['seo_default_description'] ?? ''));

        $values = [
            'session_lifetime_seconds' => $sessionLifetimeSeconds,
            'session_strict_ip'        => $sessionStrictIp,
            'site_name'                => $siteName !== '' ? $siteName : 'YouPub',
            'site_url'                 => $siteUrl !== '' ? $siteUrl : 'https://you.1tlt.ru',
            'seo_title_suffix'         => $seoTitleSuffix,
            'seo_default_description'  => $seoDefaultDescription,
        ];

        try {
            AppSettingsService::getInstance()->updateMany($values);
            error_log('AdminDashboardController::updateSettings — настройки сохранены успешно');
            $_SESSION['success'] = 'Настройки сохранены.';
        } catch (\Throwable $e) {
            error_log('AdminDashboardController::updateSettings — ошибка: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $_SESSION['error'] = 'Не удалось сохранить настройки: ' . $e->getMessage();
        }

        header('Location: /admin/settings');
        exit;
    }
}
