<?php

namespace App\Controllers;

use Core\Controller;
use App\Services\ScheduleService;

/**
 * Контроллер для работы с расписаниями
 */
class ScheduleController extends Controller
{
    private ScheduleService $scheduleService;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleService = new ScheduleService();
    }

    /**
     * Список расписаний
     */
    public function index(): void
    {
        $userId = $_SESSION['user_id'];
        $schedules = $this->scheduleService->getUserSchedules($userId);
        
        include __DIR__ . '/../../views/schedules/index.php';
    }

    /**
     * Показать форму создания расписания
     */
    public function showCreate(): void
    {
        $userId = $_SESSION['user_id'];
        $videos = (new \App\Repositories\VideoRepository())->findByUserId($userId);
        
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../views/schedules/create.php';
    }

    /**
     * Создать расписание
     */
    public function create(): void
    {
        $userId = $_SESSION['user_id'];
        $data = $this->getRequestData();
        
        if (empty($data)) {
            $data = [
                'video_id' => $this->getParam('video_id'),
                'platform' => $this->getParam('platform'),
                'publish_at' => $this->getParam('publish_at'),
                'timezone' => $this->getParam('timezone', 'UTC'),
                'repeat_type' => $this->getParam('repeat_type', 'once'),
                'repeat_until' => $this->getParam('repeat_until'),
            ];
        }

        $result = $this->scheduleService->createSchedule($userId, $data);

        if ($result['success']) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->success($result['data'], $result['message']);
            } else {
                header('Location: /schedules');
            }
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
            http_response_code(404);
            echo 'Schedule not found';
            return;
        }

        include __DIR__ . '/../../views/schedules/show.php';
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

    /**
     * Приостановить расписание
     */
    public function pause(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->scheduleService->pauseSchedule($id, $userId);

        if ($result['success']) {
            $this->success([], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Возобновить расписание
     */
    public function resume(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->scheduleService->resumeSchedule($id, $userId);

        if ($result['success']) {
            $this->success([], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Копировать расписание
     */
    public function duplicate(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->scheduleService->duplicateSchedule($id, $userId);

        if ($result['success']) {
            $this->success($result['data'] ?? [], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Массовое приостановление
     */
    public function bulkPause(): void
    {
        $userId = $_SESSION['user_id'];
        $data = $this->getRequestData();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $this->error('Invalid IDs', 400);
            return;
        }

        $result = $this->scheduleService->bulkPause($ids, $userId);
        $this->success($result['data'] ?? [], $result['message']);
    }

    /**
     * Массовое возобновление
     */
    public function bulkResume(): void
    {
        $userId = $_SESSION['user_id'];
        $data = $this->getRequestData();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $this->error('Invalid IDs', 400);
            return;
        }

        $result = $this->scheduleService->bulkResume($ids, $userId);
        $this->success($result['data'] ?? [], $result['message']);
    }

    /**
     * Массовое удаление
     */
    public function bulkDelete(): void
    {
        $userId = $_SESSION['user_id'];
        $data = $this->getRequestData();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $this->error('Invalid IDs', 400);
            return;
        }

        $result = $this->scheduleService->bulkDelete($ids, $userId);
        $this->success($result['data'] ?? [], $result['message']);
    }
}
