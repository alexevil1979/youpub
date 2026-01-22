<?php

namespace App\Controllers;

use Core\Controller;
use App\Repositories\VideoRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\PublicationRepository;
use App\Modules\ContentGroups\Repositories\ContentGroupRepository;
use App\Modules\ContentGroups\Repositories\PublicationTemplateRepository;

/**
 * Контроллер для глобального поиска
 */
class SearchController extends Controller
{
    /**
     * Поиск по всем разделам
     */
    public function search(): void
    {
        $userId = $_SESSION['user_id'];
        $query = trim($this->getParam('q', ''));
        
        if (empty($query) || strlen($query) < 2) {
            $this->success(['results' => []]);
            return;
        }

        $results = [];

        // Поиск видео
        $videoRepo = new VideoRepository();
        $videos = $videoRepo->search($userId, $query);
        foreach ($videos as $video) {
            $results[] = [
                'type' => 'video',
                'id' => $video['id'],
                'title' => $video['title'] ?? $video['file_name'] ?? 'Видео #' . $video['id'],
                'description' => mb_substr($video['description'] ?? '', 0, 100),
                'url' => '/videos/' . $video['id'],
                'icon' => '🎬',
            ];
        }

        // Поиск групп контента
        $groupRepo = new ContentGroupRepository();
        $groups = $groupRepo->search($userId, $query);
        foreach ($groups as $group) {
            $results[] = [
                'type' => 'group',
                'id' => $group['id'],
                'title' => $group['name'],
                'description' => mb_substr($group['description'] ?? '', 0, 100),
                'url' => '/content-groups/' . $group['id'],
                'icon' => '📁',
            ];
        }

        // Поиск расписаний
        $scheduleRepo = new ScheduleRepository();
        $schedules = $scheduleRepo->search($userId, $query);
        foreach ($schedules as $schedule) {
            $videoTitle = 'Видео #' . $schedule['video_id'];
            if ($schedule['video_id']) {
                try {
                    $video = $videoRepo->findById($schedule['video_id']);
                    if ($video) {
                        $videoTitle = $video['title'] ?? $video['file_name'] ?? 'Видео #' . $schedule['video_id'];
                    }
                } catch (\Exception $e) {
                    // Игнорируем
                }
            }

            $results[] = [
                'type' => 'schedule',
                'id' => $schedule['id'],
                'title' => 'Расписание для: ' . $videoTitle,
                'description' => 'Платформа: ' . $schedule['platform'] . ', Дата: ' . ($schedule['publish_at'] ?? 'не указана'),
                'url' => '/schedules/' . $schedule['id'],
                'icon' => '📅',
            ];
        }

        // Поиск шаблонов
        $templateRepo = new PublicationTemplateRepository();
        $templates = $templateRepo->search($userId, $query);
        foreach ($templates as $template) {
            $results[] = [
                'type' => 'template',
                'id' => $template['id'],
                'title' => $template['name'],
                'description' => mb_substr($template['description'] ?? '', 0, 100),
                'url' => '/content-groups/templates',
                'icon' => '📝',
            ];
        }

        // Поиск публикаций
        $publicationRepo = new PublicationRepository();
        $publications = $publicationRepo->search($userId, $query);
        foreach ($publications as $publication) {
            $videoTitle = 'Видео #' . $publication['video_id'];
            if ($publication['video_id']) {
                try {
                    $video = $videoRepo->findById($publication['video_id']);
                    if ($video) {
                        $videoTitle = $video['title'] ?? $video['file_name'] ?? 'Видео #' . $publication['video_id'];
                    }
                } catch (\Exception $e) {
                    // Игнорируем
                }
            }

            $results[] = [
                'type' => 'publication',
                'id' => $publication['id'],
                'title' => 'Публикация: ' . $videoTitle,
                'description' => 'Платформа: ' . $publication['platform'] . ', Статус: ' . $publication['status'],
                'url' => '/videos/' . $publication['video_id'],
                'icon' => '📤',
            ];
        }

        // Ограничиваем количество результатов
        $results = array_slice($results, 0, 20);

        // Возвращаем результаты в правильном формате
        $this->success(['results' => $results, 'query' => $query, 'count' => count($results)]);
    }
}
