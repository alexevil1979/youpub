<?php

namespace App\Controllers\Admin;

use Core\Controller;
use App\Repositories\ScheduleRepository;

/**
 * Админ контроллер расписаний
 */
class AdminSchedulesController extends Controller
{
    private ScheduleRepository $scheduleRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->scheduleRepo = new ScheduleRepository();
    }

    /**
     * Список всех расписаний
     */
    public function index(): void
    {
        $schedules = $this->scheduleRepo->findAll([], ['publish_at' => 'ASC']);
        include __DIR__ . '/../../../views/admin/schedules/index.php';
    }

    /**
     * Показать расписание
     */
    public function show(int $id): void
    {
        $schedule = $this->scheduleRepo->findById($id);
        if (!$schedule) {
            http_response_code(404);
            echo 'Schedule not found';
            return;
        }
        include __DIR__ . '/../../../views/admin/schedules/show.php';
    }
}
