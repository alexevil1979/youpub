<?php

namespace Core;

/**
 * Класс для генерации хлебных крошек
 */
class Breadcrumbs
{
    /**
     * Генерирует хлебные крошки на основе URL
     */
    public static function generateFromUrl(string $url = null): array
    {
        if ($url === null) {
            $url = $_SERVER['REQUEST_URI'] ?? '/';
        }

        // Убираем query string
        $url = parse_url($url, PHP_URL_PATH);
        $url = rtrim($url, '/');
        
        // Всегда начинаем с главной
        $breadcrumbs = [
            ['title' => 'Главная', 'url' => '/dashboard']
        ];

        if ($url === '/' || $url === '/dashboard') {
            return $breadcrumbs;
        }

        $parts = explode('/', trim($url, '/'));
        $currentPath = '';

        foreach ($parts as $index => $part) {
            $currentPath .= '/' . $part;
            
            // Пропускаем пустые части
            if (empty($part)) {
                continue;
            }

            // Определяем название для части URL
            $title = self::getTitleForPath($part, $parts, $index);
            
            // Последний элемент не должен быть ссылкой
            $isLast = ($index === count($parts) - 1);
            
            $breadcrumbs[] = [
                'title' => $title,
                'url' => $isLast ? null : $currentPath
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Получить название для части пути
     */
    private static function getTitleForPath(string $part, array $allParts, int $index): string
    {
        // Маппинг известных путей
        $mapping = [
            'dashboard' => 'Дашборд',
            'videos' => 'Видео',
            'upload' => 'Загрузка',
            'schedules' => 'Расписания',
            'create' => 'Создание',
            'edit' => 'Редактирование',
            'integrations' => 'Интеграции',
            'content-groups' => 'Группы контента',
            'templates' => 'Шаблоны',
            'smart-schedules' => 'Умные расписания',
            'statistics' => 'Статистика',
            'publications' => 'Публикации',
            'profile' => 'Профиль',
            'admin' => 'Админ-панель',
            'auth' => 'Авторизация',
            'login' => 'Вход',
            'register' => 'Регистрация',
        ];

        // Если это ID (число), пытаемся определить контекст
        if (is_numeric($part)) {
            $prevPart = $allParts[$index - 1] ?? '';
            return self::getTitleForId($part, $prevPart);
        }

        return $mapping[$part] ?? ucfirst(str_replace(['-', '_'], ' ', $part));
    }

    /**
     * Получить название для ID (нужно загрузить из БД)
     */
    private static function getTitleForId(string $id, string $context): string
    {
        // Пытаемся загрузить данные только если есть подключение к БД
        try {
            $db = \Core\Database::getInstance();
        } catch (\Exception $e) {
            return '#' . $id;
        }

        // Для видео
        if ($context === 'videos') {
            try {
                $videoRepo = new \App\Repositories\VideoRepository();
                $video = $videoRepo->findById((int)$id);
                if ($video) {
                    $title = $video['title'] ?? $video['file_name'] ?? null;
                    if ($title) {
                        return mb_substr($title, 0, 30) . (mb_strlen($title) > 30 ? '...' : '');
                    }
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }

        // Для групп
        if ($context === 'content-groups') {
            try {
                $groupRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupRepository();
                $group = $groupRepo->findById((int)$id);
                if ($group) {
                    $name = $group['name'] ?? null;
                    if ($name) {
                        return mb_substr($name, 0, 30) . (mb_strlen($name) > 30 ? '...' : '');
                    }
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }

        // Для расписаний
        if ($context === 'schedules') {
            return 'Расписание #' . $id;
        }

        // Для шаблонов
        if ($context === 'templates') {
            try {
                $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
                $template = $templateRepo->findById((int)$id);
                if ($template) {
                    $name = $template['name'] ?? null;
                    if ($name) {
                        return mb_substr($name, 0, 30) . (mb_strlen($name) > 30 ? '...' : '');
                    }
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }

        return '#' . $id;
    }

    /**
     * Рендерит HTML хлебных крошек
     */
    public static function render(array $breadcrumbs = null): string
    {
        if ($breadcrumbs === null) {
            $breadcrumbs = self::generateFromUrl();
        }

        if (count($breadcrumbs) <= 1) {
            return '';
        }

        $html = '<nav class="breadcrumbs" aria-label="Хлебные крошки">';
        $html .= '<ol class="breadcrumb-list">';
        
        foreach ($breadcrumbs as $index => $crumb) {
            $isLast = ($index === count($breadcrumbs) - 1);
            $html .= '<li class="breadcrumb-item' . ($isLast ? ' active' : '') . '">';
            
            if ($isLast || $crumb['url'] === null) {
                $html .= '<span>' . htmlspecialchars($crumb['title']) . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($crumb['url']) . '">' . htmlspecialchars($crumb['title']) . '</a>';
            }
            
            if (!$isLast) {
                $html .= '<span class="breadcrumb-separator">›</span>';
            }
            
            $html .= '</li>';
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }
}
