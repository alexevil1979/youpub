<?php

namespace App\Modules\ContentGroups\Controllers;

use Core\Controller;
use App\Services\ScheduleService;
use App\Modules\ContentGroups\Services\GroupService;
use App\Modules\ContentGroups\Services\TemplateService;

/**
 * Контроллер для управления умными расписаниями
 */
class SmartScheduleController extends Controller
{
    private ScheduleService $scheduleService;
    private GroupService $groupService;
    private TemplateService $templateService;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleService = new ScheduleService();
        $this->groupService = new GroupService();
        $this->templateService = new TemplateService();
    }

    /**
     * Показать форму создания умного расписания
     */
    public function showCreate(): void
    {
        $userId = $_SESSION['user_id'];
        $groups = $this->groupService->getUserGroups($userId);
        $templates = $this->templateService->getUserTemplates($userId, true);
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        
        if (!isset($groups)) {
            $groups = [];
        }
        if (!isset($templates)) {
            $templates = [];
        }
        
        include __DIR__ . '/../../../../views/content_groups/schedules/create.php';
    }

    /**
     * Создать умное расписание
     */
    public function create(): void
    {
        $userId = $_SESSION['user_id'];
        
        // Обработка weekdays из массива checkbox
        $weekdaysArray = $_POST['weekdays'] ?? [];
        $weekdays = null;
        if (!empty($weekdaysArray) && is_array($weekdaysArray)) {
            $weekdays = implode(',', array_map('intval', $weekdaysArray));
        }
        
        $data = [
            'user_id' => $userId,
            'content_group_id' => $this->getParam('content_group_id') ? (int)$this->getParam('content_group_id') : null,
            'video_id' => $this->getParam('video_id') ? (int)$this->getParam('video_id') : null,
            'template_id' => $this->getParam('template_id') ? (int)$this->getParam('template_id') : null,
            'platform' => $this->getParam('platform', 'youtube'),
            'schedule_type' => $this->getParam('schedule_type', 'fixed'),
            'publish_at' => $this->getParam('publish_at') ? date('Y-m-d H:i:s', strtotime($this->getParam('publish_at'))) : date('Y-m-d H:i:s'),
            'interval_minutes' => $this->getParam('interval_minutes') ? (int)$this->getParam('interval_minutes') : null,
            'batch_count' => $this->getParam('batch_count') ? (int)$this->getParam('batch_count') : null,
            'batch_window_hours' => $this->getParam('batch_window_hours') ? (int)$this->getParam('batch_window_hours') : null,
            'random_window_start' => $this->getParam('random_window_start') ?: null,
            'random_window_end' => $this->getParam('random_window_end') ?: null,
            'weekdays' => $weekdays ?: null,
            'active_hours_start' => $this->getParam('active_hours_start') ?: null,
            'active_hours_end' => $this->getParam('active_hours_end') ?: null,
            'daily_limit' => $this->getParam('daily_limit') ? (int)$this->getParam('daily_limit') : null,
            'hourly_limit' => $this->getParam('hourly_limit') ? (int)$this->getParam('hourly_limit') : null,
            'delay_between_posts' => $this->getParam('delay_between_posts') ? (int)$this->getParam('delay_between_posts') : null,
            'skip_published' => $this->getParam('skip_published', '1') === '1',
            'status' => 'pending',
        ];

        // Валидация
        if (empty($data['content_group_id']) && empty($data['video_id'])) {
            $_SESSION['error'] = 'Необходимо указать группу или видео';
            header('Location: /content-groups/schedules/create');
            exit;
        }

        // Для fixed типа нужен publish_at
        if ($data['schedule_type'] === 'fixed' && empty($data['publish_at'])) {
            $_SESSION['error'] = 'Укажите дату и время публикации';
            header('Location: /content-groups/schedules/create');
            exit;
        }

        $scheduleRepo = new \App\Repositories\ScheduleRepository();
        $scheduleId = $scheduleRepo->create($data);

        $_SESSION['success'] = 'Умное расписание создано успешно';
        header('Location: /schedules');
        exit;
    }
}
