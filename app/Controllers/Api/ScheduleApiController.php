<?php

namespace App\Controllers\Api;

use Core\Controller;
use App\Services\ScheduleService;

/**
 * API контроллер для работы с расписаниями
 */
class ScheduleApiController extends Controller
{
    private ScheduleService $scheduleService;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->scheduleService = new ScheduleService();
    }

    /**
     * Список расписаний
     */
    public function list(): void
    {
        $userId = $_SESSION['user_id'];
        $schedules = $this->scheduleService->getUserSchedules($userId);
        $this->success($schedules);
    }

    /**
     * Создать расписание
     */
    public function create(): void
    {
        $userId = $_SESSION['user_id'];
        $data = $this->getRequestData();

        $result = $this->scheduleService->createSchedule($userId, $data);

        if ($result['success']) {
            $this->success($result['data'], $result['message']);
        } else {
            $this->error($result['message'], 400, $result['errors'] ?? []);
        }
    }

    /**
     * Показать расписание
     */
    public function show(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $schedule = $this->scheduleService->getSchedule($id, $userId);

        if (!$schedule) {
            $this->error('Schedule not found', 404);
            return;
        }

        $this->success($schedule);
    }

    /**
     * Удалить расписание
     */
    public function delete(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->scheduleService->deleteSchedule($id, $userId);

        if ($result['success']) {
            $this->success([], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }
}
