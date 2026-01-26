<?php
$title = 'Расписания';
ob_start();
?>

<h1>Расписания</h1>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 1rem;">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 1rem;">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<a href="/content-groups/schedules/create" class="btn btn-primary">Создать расписание</a>

<?php 
// Убеждаемся, что переменные определены
if (!isset($smartSchedules)) {
    $smartSchedules = [];
}
if (!isset($groups)) {
    $groups = [];
}
$filterStatus = $_GET['status'] ?? 'all';
$filterPlatform = $_GET['platform'] ?? 'all';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterType = $_GET['type'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'publish_at_desc';

$allowedStatuses = ['all', 'pending', 'published', 'failed', 'processing', 'paused'];
$allowedPlatforms = ['all', 'youtube', 'telegram', 'tiktok', 'instagram', 'pinterest', 'both'];
$allowedTypes = ['all', 'single', 'group'];
$allowedSorts = ['publish_at_desc', 'publish_at_asc', 'created_at_desc', 'created_at_asc', 'status_asc', 'status_desc'];

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'all';
}
if (!in_array($filterPlatform, $allowedPlatforms, true)) {
    $filterPlatform = 'all';
}
if (!in_array($filterType, $allowedTypes, true)) {
    $filterType = 'all';
}
if (!in_array($sortBy, $allowedSorts, true)) {
    $sortBy = 'publish_at_desc';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) {
    $filterDateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo)) {
    $filterDateTo = '';
}

$stats = [
    'total' => count($smartSchedules),
    'pending' => 0,
    'published' => 0,
    'failed' => 0,
    'processing' => 0,
    'paused' => 0,
];

foreach ($smartSchedules as $schedule) {
    if (isset($schedule['status'])) {
        if ($schedule['status'] === 'pending') $stats['pending']++;
        elseif ($schedule['status'] === 'published') $stats['published']++;
        elseif ($schedule['status'] === 'failed') $stats['failed']++;
        elseif ($schedule['status'] === 'processing') $stats['processing']++;
        elseif ($schedule['status'] === 'paused') $stats['paused']++;
    }
}

$filteredSchedules = $smartSchedules;
if ($filterStatus !== 'all') {
    $filteredSchedules = array_filter($filteredSchedules, function($s) use ($filterStatus) {
        return ($s['status'] ?? '') === $filterStatus;
    });
}
if ($filterPlatform !== 'all') {
    $filteredSchedules = array_filter($filteredSchedules, function($s) use ($filterPlatform) {
        return ($s['platform'] ?? '') === $filterPlatform;
    });
}
if ($filterType === 'group') {
    $filteredSchedules = array_filter($filteredSchedules, function($s) {
        return !empty($s['content_group_id']);
    });
} elseif ($filterType === 'single') {
    $filteredSchedules = array_filter($filteredSchedules, function($s) {
        return empty($s['content_group_id']);
    });
}
if ($filterDateFrom) {
    $filteredSchedules = array_filter($filteredSchedules, function($s) use ($filterDateFrom) {
        if (empty($s['publish_at'])) {
            return false;
        }
        return strtotime($s['publish_at']) >= strtotime($filterDateFrom);
    });
}
if ($filterDateTo) {
    $filteredSchedules = array_filter($filteredSchedules, function($s) use ($filterDateTo) {
        if (empty($s['publish_at'])) {
            return false;
        }
        return strtotime($s['publish_at']) <= strtotime($filterDateTo . ' 23:59:59');
    });
}

$sortParts = explode('_', $sortBy);
$sortField = $sortParts[0] ?? 'publish';
$sortDir = strtolower($sortParts[2] ?? $sortParts[1] ?? 'desc');
$sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

usort($filteredSchedules, function($a, $b) use ($sortField, $sortDir) {
    $getTime = function($item, $key) {
        if (!isset($item[$key]) || !$item[$key]) {
            return 0;
        }
        return strtotime($item[$key]) ?: 0;
    };

    switch ($sortField) {
        case 'created':
            $aTime = $getTime($a, 'created_at');
            $bTime = $getTime($b, 'created_at');
            break;
        case 'status':
            $aTime = strcmp($a['status'] ?? '', $b['status'] ?? '');
            $bTime = 0;
            break;
        case 'publish':
        default:
            $aTime = $getTime($a, 'publish_at');
            $bTime = $getTime($b, 'publish_at');
            break;
    }

    if ($sortField === 'status') {
        return $sortDir === 'asc' ? $aTime : -$aTime;
    }

    if ($aTime === $bTime) return 0;
    return ($sortDir === 'asc')
        ? ($aTime < $bTime ? -1 : 1)
        : ($aTime > $bTime ? -1 : 1);
});
$formatInterval = static function (int $seconds): string {
    $seconds = max(0, $seconds);
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days > 0) {
        $parts[] = $days . 'д';
    }
    if ($hours > 0 || $days > 0) {
        $parts[] = $hours . 'ч';
    }
    $parts[] = $minutes . 'м';
    return implode(' ', $parts);
};
?>

<div class="schedules-header">
    <div class="schedules-stats">
        <div class="stat-item">
            <span class="stat-value"><?= $stats['total'] ?></span>
            <span class="stat-label">Всего</span>
        </div>
        <div class="stat-item stat-pending">
            <span class="stat-value"><?= $stats['pending'] ?></span>
            <span class="stat-label">Ожидают</span>
        </div>
        <div class="stat-item stat-published">
            <span class="stat-value"><?= $stats['published'] ?></span>
            <span class="stat-label">Опубликовано</span>
        </div>
        <div class="stat-item stat-processing">
            <span class="stat-value"><?= $stats['processing'] ?></span>
            <span class="stat-label">В процессе</span>
        </div>
        <div class="stat-item stat-failed">
            <span class="stat-value"><?= $stats['failed'] ?></span>
            <span class="stat-label">Ошибки</span>
        </div>
        <div class="stat-item stat-paused">
            <span class="stat-value"><?= $stats['paused'] ?></span>
            <span class="stat-label">На паузе</span>
        </div>
    </div>
</div>

<div class="filters-panel">
    <form method="GET" action="/content-groups/schedules" class="filters-form" id="filtersForm">
        <div class="filter-group">
            <label for="filter_status">Статус:</label>
            <select id="filter_status" name="status" onchange="applyFilters()">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Все</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Ожидают</option>
                <option value="processing" <?= $filterStatus === 'processing' ? 'selected' : '' ?>>В процессе</option>
                <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Опубликовано</option>
                <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Ошибки</option>
                <option value="paused" <?= $filterStatus === 'paused' ? 'selected' : '' ?>>На паузе</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="filter_platform">Платформа:</label>
            <select id="filter_platform" name="platform" onchange="applyFilters()">
                <option value="all" <?= $filterPlatform === 'all' ? 'selected' : '' ?>>Все</option>
                <option value="youtube" <?= $filterPlatform === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                <option value="telegram" <?= $filterPlatform === 'telegram' ? 'selected' : '' ?>>Telegram</option>
                <option value="tiktok" <?= $filterPlatform === 'tiktok' ? 'selected' : '' ?>>TikTok</option>
                <option value="instagram" <?= $filterPlatform === 'instagram' ? 'selected' : '' ?>>Instagram</option>
                <option value="pinterest" <?= $filterPlatform === 'pinterest' ? 'selected' : '' ?>>Pinterest</option>
                <option value="both" <?= $filterPlatform === 'both' ? 'selected' : '' ?>>Обе (YouTube + Telegram)</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="filter_type">Тип:</label>
            <select id="filter_type" name="type" onchange="applyFilters()">
                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Все</option>
                <option value="single" <?= $filterType === 'single' ? 'selected' : '' ?>>Одиночные</option>
                <option value="group" <?= $filterType === 'group' ? 'selected' : '' ?>>Групповые</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="filter_date_from">С:</label>
            <input type="date" id="filter_date_from" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" onchange="applyFilters()">
        </div>

        <div class="filter-group">
            <label for="filter_date_to">По:</label>
            <input type="date" id="filter_date_to" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" onchange="applyFilters()">
        </div>

        <div class="filter-group">
            <label for="filter_sort">Сортировка:</label>
            <select id="filter_sort" name="sort" onchange="applyFilters()">
                <option value="publish_at_desc" <?= $sortBy === 'publish_at_desc' ? 'selected' : '' ?>>Сначала новые (публикация)</option>
                <option value="publish_at_asc" <?= $sortBy === 'publish_at_asc' ? 'selected' : '' ?>>Сначала старые (публикация)</option>
                <option value="created_at_desc" <?= $sortBy === 'created_at_desc' ? 'selected' : '' ?>>Сначала новые (создание)</option>
                <option value="created_at_asc" <?= $sortBy === 'created_at_asc' ? 'selected' : '' ?>>Сначала старые (создание)</option>
                <option value="status_asc" <?= $sortBy === 'status_asc' ? 'selected' : '' ?>>Статус (A→Z)</option>
                <option value="status_desc" <?= $sortBy === 'status_desc' ? 'selected' : '' ?>>Статус (Z→A)</option>
            </select>
        </div>

        <div class="filter-group">
            <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">Очистить</button>
        </div>
    </form>
</div>

<?php if (empty($filteredSchedules)): ?>
    <p style="margin-top: 2rem;">Нет расписаний. <a href="/content-groups/schedules/create">Создать расписание</a></p>
<?php else: ?>
    <div style="margin-top: 2rem;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6; width: 30px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Название / Группа</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Платформа</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Тип</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Следующая публикация</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Следующие публикации</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Статус</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredSchedules as $schedule): 
                    $groupId = isset($schedule['content_group_id']) ? (int)$schedule['content_group_id'] : 0;
                    $group = isset($groups[$groupId]) ? $groups[$groupId] : null;
                    $publishAtRaw = $schedule['publish_at'] ?? null;
                    $publishAtTs = $publishAtRaw ? strtotime($publishAtRaw) : null;
                    $scheduleTypeNames = [
                        'fixed' => 'Фиксированное',
                        'interval' => 'Интервальное',
                        'batch' => 'Пакетное',
                        'random' => 'Случайное',
                        'wave' => 'Волновое'
                    ];
                    $scheduleType = isset($schedule['schedule_type']) && isset($scheduleTypeNames[$schedule['schedule_type']]) 
                        ? $scheduleTypeNames[$schedule['schedule_type']] 
                        : ($schedule['schedule_type'] ?? 'Неизвестно');

                    // Для интервальных расписаний вычисляем следующее время публикации
                    $nextPublishAt = null;
                    $overdueReason = null;

                    if (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'interval' && !empty($schedule['interval_minutes'])) {
                        $baseTime = $publishAtTs ?? time();
                        $interval = (int)$schedule['interval_minutes'] * 60;
                        $now = time();

                        // Вычисляем следующее время публикации
                        if ($baseTime <= $now) {
                            $elapsed = $now - $baseTime;
                            $intervalsPassed = floor($elapsed / $interval);
                            $nextPublishAt = $baseTime + (($intervalsPassed + 1) * $interval);
                        } else {
                            $nextPublishAt = $baseTime;
                        }
                    } elseif ($publishAtTs) {
                        $nextPublishAt = $publishAtTs;
                    }
                ?>
                    <tr style="border-bottom: 1px solid #dee2e6;" data-publish-at="<?= $nextPublishAt ? date('Y-m-d H:i:s', $nextPublishAt) : '' ?>" data-status="<?= $schedule['status'] ?? '' ?>">
                        <td style="padding: 0.75rem;">
                            <input type="checkbox" class="schedule-checkbox" value="<?= (int)$schedule['id'] ?>">
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php if ($group && isset($group['id']) && isset($group['name'])): ?>
                                <a href="/content-groups/<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
                            <?php else: ?>
                                <span style="color: #95a5a6;">Группа не найдена (ID: <?= $groupId ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-info"><?= isset($schedule['platform']) ? ucfirst($schedule['platform']) : 'Неизвестно' ?></span>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?= htmlspecialchars($scheduleType) ?>
                            <?php if (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'interval' && isset($schedule['interval_minutes']) && $schedule['interval_minutes']): ?>
                                <br><small style="color: #95a5a6;">Каждые <?= (int)$schedule['interval_minutes'] ?> мин.</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php
                            // Определяем причину просрочки, если время прошло
                            if ($nextPublishAt !== null):
                                $now = time();
                                if ($nextPublishAt <= $now):
                                    // Время прошло, определяем причину
                                    $reasons = [];
                                    
                                    // Проверяем статус расписания
                                    if (isset($schedule['status']) && $schedule['status'] === 'paused') {
                                        $reasons[] = 'Расписание на паузе';
                                    }
                                    
                                    // Проверяем группу
                                    if ($group) {
                                        if (isset($group['status']) && $group['status'] !== 'active') {
                                            $reasons[] = 'Группа неактивна';
                                        }
                                        
                                        // Проверяем наличие доступных видео
                                        try {
                                            $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
                                            $nextFile = $fileRepo->findNextUnpublished((int)$group['id']);
                                            if (!$nextFile) {
                                                $reasons[] = 'Нет доступных видео';
                                            }
                                        } catch (\Exception $e) {
                                            error_log("Error checking files: " . $e->getMessage());
                                            $reasons[] = 'Ошибка проверки видео';
                                        }
                                        
                                        // Проверяем подключенные интеграции
                                        try {
                                            $platform = $schedule['platform'] ?? 'youtube';
                                            $integrationRepo = null;
                                            
                                            switch ($platform) {
                                                case 'youtube':
                                                    $integrationRepo = new \App\Repositories\YoutubeIntegrationRepository();
                                                    break;
                                                case 'telegram':
                                                    $integrationRepo = new \App\Repositories\TelegramIntegrationRepository();
                                                    break;
                                                case 'tiktok':
                                                    $integrationRepo = new \App\Repositories\TiktokIntegrationRepository();
                                                    break;
                                                case 'instagram':
                                                    $integrationRepo = new \App\Repositories\InstagramIntegrationRepository();
                                                    break;
                                                case 'pinterest':
                                                    $integrationRepo = new \App\Repositories\PinterestIntegrationRepository();
                                                    break;
                                            }
                                            
                                            if ($integrationRepo) {
                                                $integration = $integrationRepo->findDefaultByUserId($schedule['user_id'] ?? 0);
                                                if (!$integration || ($integration['status'] ?? '') !== 'connected') {
                                                    $reasons[] = 'Интеграция не подключена';
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            error_log("Error checking integration: " . $e->getMessage());
                                        }
                                    } else {
                                        $reasons[] = 'Группа не найдена';
                                    }
                                    
                                    // Если нет специфических причин, указываем общую
                                    if (empty($reasons)) {
                                        $reasons[] = 'Время публикации прошло';
                                    }
                                    
                                    $overdueReason = implode(', ', $reasons);
                            ?>
                                    <div>
                                        <span style="color: #e74c3c; font-weight: 500;">Просрочено</span>
                                        <br><small style="color: #e74c3c; font-size: 0.75rem;">На: <?= htmlspecialchars($formatInterval($now - $nextPublishAt)) ?></small>
                                        <?php if ($overdueReason): ?>
                                            <br><small style="color: #e74c3c; font-size: 0.75rem;"><?= htmlspecialchars($overdueReason) ?></small>
                                        <?php endif; ?>
                                        <?php if ($publishAtTs): ?>
                                            <br><small style="color: #95a5a6; font-size: 0.75rem;">План: <?= date('d.m.Y H:i', $publishAtTs) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <div style="color: #3498db; font-weight: 500;">
                                            <?= date('d.m.Y H:i', $nextPublishAt) ?>
                                        </div>
                                        <div style="color: #95a5a6; font-size: 0.75rem;">
                                            Через: <?= htmlspecialchars($formatInterval($nextPublishAt - $now)) ?>
                                        </div>
                                        <?php if ($publishAtTs && (!isset($schedule['schedule_type']) || $schedule['schedule_type'] !== 'interval')): ?>
                                            <div style="color: #95a5a6; font-size: 0.75rem;">
                                                План: <?= date('d.m.Y H:i', $publishAtTs) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'interval' && $publishAtTs): ?>
                                            <div style="color: #95a5a6; font-size: 0.75rem;">
                                                База: <?= date('d.m.Y H:i', $publishAtTs) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($schedule['status']) && $schedule['status'] === 'pending'): ?>
                                            <div class="countdown-timer" 
                                                 data-publish-at="<?= date('Y-m-d H:i:s', $nextPublishAt) ?>" 
                                                 data-overdue-reason="<?= htmlspecialchars($overdueReason ?? '', ENT_QUOTES) ?>"
                                                 style="margin-top: 0.5rem; font-size: 0.85rem; color: #3498db; font-weight: 500;">
                                                <span class="countdown-text">Осталось: </span>
                                                <span class="countdown-value">-</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #95a5a6;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php 
                            $scheduleId = (int)($schedule['id'] ?? 0);
                            $publications = isset($nextPublications[$scheduleId]) ? $nextPublications[$scheduleId] : [];
                            if (!empty($publications)):
                            ?>
                                <div style="font-size: 0.85rem; max-width: 300px;">
                                    <?php 
                                    // Показываем первые 5 публикаций
                                    $showCount = min(5, count($publications));
                                    for ($i = 0; $i < $showCount; $i++):
                                        $pub = $publications[$i];
                                        $isNext = ($i === 0);
                                    ?>
                                        <div style="margin-bottom: 0.5rem; padding: 0.25rem 0.5rem; background: <?= $isNext ? '#e3f2fd' : '#f5f5f5' ?>; border-radius: 4px; <?= $isNext ? 'border-left: 3px solid #3498db;' : '' ?>">
                                            <div style="font-weight: <?= $isNext ? '500' : '400' ?>; color: <?= $isNext ? '#3498db' : '#555' ?>;">
                                                <?= htmlspecialchars($pub['formatted']) ?>
                                            </div>
                                            <?php if ($isNext && $pub['time'] > time()): ?>
                                                <div class="countdown-timer-small" 
                                                     data-publish-at="<?= htmlspecialchars($pub['date']) ?>" 
                                                     style="font-size: 0.75rem; color: #3498db; margin-top: 0.25rem;">
                                                    <span class="countdown-text-small">Осталось: </span>
                                                    <span class="countdown-value-small">-</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                    <?php if (count($publications) > 5): ?>
                                        <div style="font-size: 0.75rem; color: #95a5a6; margin-top: 0.5rem;">
                                            И еще <?= count($publications) - 5 ?> публикаций...
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #95a5a6; font-size: 0.85rem;">Нет запланированных публикаций</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-<?= 
                                (isset($schedule['status']) && $schedule['status'] === 'pending') ? 'warning' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'published') ? 'success' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'failed') ? 'danger' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'paused') ? 'info' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'processing') ? 'primary' : 'secondary')))) 
                            ?>">
                                <?php 
                                $statusNames = [
                                    'pending' => 'Ожидает',
                                    'published' => 'Опубликовано',
                                    'failed' => 'Ошибка',
                                    'paused' => 'Приостановлено',
                                    'processing' => 'Обработка'
                                ];
                                echo $statusNames[$schedule['status'] ?? ''] ?? ucfirst($schedule['status'] ?? 'Неизвестно');
                                ?>
                            </span>
                        </td>
                        <td style="padding: 0.5rem;">
                            <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                <?php if (isset($schedule['id'])): ?>
                                    <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>" class="btn btn-xs btn-primary" title="Просмотр">
                                        <?= \App\Helpers\IconHelper::render('view', 14, 'icon-inline') ?>
                                    </a>
                                    <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>/edit" class="btn btn-xs btn-secondary" title="Редактировать">
                                        <?= \App\Helpers\IconHelper::render('edit', 14, 'icon-inline') ?>
                                    </a>
                                    <?php if (isset($schedule['status']) && $schedule['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-xs btn-warning" onclick="toggleSchedulePause(<?= (int)$schedule['id'] ?>, 'pause')" title="Приостановить">
                                            <?= \App\Helpers\IconHelper::render('pause', 14, 'icon-inline') ?>
                                        </button>
                                    <?php elseif (isset($schedule['status']) && $schedule['status'] === 'paused'): ?>
                                        <button type="button" class="btn btn-xs btn-success" onclick="toggleSchedulePause(<?= (int)$schedule['id'] ?>, 'resume')" title="Возобновить">
                                            <?= \App\Helpers\IconHelper::render('play', 14, 'icon-inline') ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-xs btn-danger" onclick="deleteSchedule(<?= (int)$schedule['id'] ?>)" title="Удалить">
                                        <?= \App\Helpers\IconHelper::render('delete', 14, 'icon-inline') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bulk-actions" id="bulkActions" style="display: none; margin-top: 1rem;">
        <div class="bulk-actions-content" style="display: flex; align-items: center; gap: 1rem;">
            <span class="bulk-count">Выбрано: <strong id="selectedCount">0</strong></span>
            <div class="bulk-buttons" style="display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-sm btn-warning" onclick="bulkPause()">⏸ Приостановить</button>
                <button type="button" class="btn btn-sm btn-success" onclick="bulkResume()">▶ Возобновить</button>
                <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()">🗑 Удалить</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function applyFilters() {
    document.getElementById('filtersForm').submit();
}

function clearFilters() {
    window.location.href = '/content-groups/schedules';
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.schedule-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.schedule-checkbox:checked');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (checked.length > 0) {
        bulkActions.style.display = 'block';
        selectedCount.textContent = checked.length;
    } else {
        bulkActions.style.display = 'none';
    }
}

document.querySelectorAll('.schedule-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});

function bulkPause() {
    const ids = Array.from(document.querySelectorAll('.schedule-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return;
    if (!confirm('Приостановить выбранные расписания?')) return;

    fetch('/content-groups/schedules/bulk-pause', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ids})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось приостановить'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Произошла ошибка');
    });
}

function bulkResume() {
    const ids = Array.from(document.querySelectorAll('.schedule-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return;
    if (!confirm('Возобновить выбранные расписания?')) return;

    fetch('/content-groups/schedules/bulk-resume', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ids})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось возобновить'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Произошла ошибка');
    });
}

function bulkDelete() {
    const ids = Array.from(document.querySelectorAll('.schedule-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return;
    if (!confirm('Удалить выбранные расписания?')) return;

    fetch('/content-groups/schedules/bulk-delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ids})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Произошла ошибка');
    });
}

function deleteSchedule(id) {
    if (!confirm('Удалить расписание?')) {
        return;
    }
    
    fetch('/content-groups/schedules/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Расписание удалено');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить расписание'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function toggleSchedulePause(id, action) {
    const actionText = action === 'pause' ? 'приостановить' : 'возобновить';
    if (!confirm('Вы уверены, что хотите ' + actionText + ' это расписание?')) {
        return;
    }
    
    fetch('/content-groups/schedules/' + id + '/' + action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось изменить статус расписания'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

// Обратный отсчет до публикации
function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown-timer');
    
    countdowns.forEach(timer => {
        const publishAtStr = timer.getAttribute('data-publish-at');
        if (!publishAtStr) return;
        
        const publishAt = new Date(publishAtStr.replace(' ', 'T'));
        const now = new Date();
        const diff = publishAt - now;
        
        if (diff <= 0) {
            // Время прошло - показываем причину, если есть
            const overdueReason = timer.getAttribute('data-overdue-reason');
            if (overdueReason && overdueReason.trim()) {
                timer.querySelector('.countdown-text').textContent = 'Причина: ';
                timer.querySelector('.countdown-value').textContent = overdueReason;
            } else {
                timer.querySelector('.countdown-text').textContent = '';
                timer.querySelector('.countdown-value').textContent = 'Время прошло';
            }
            timer.style.color = '#e74c3c';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        let countdownText = '';
        if (days > 0) {
            countdownText = `${days}д ${hours}ч ${minutes}м`;
        } else if (hours > 0) {
            countdownText = `${hours}ч ${minutes}м ${seconds}с`;
        } else if (minutes > 0) {
            countdownText = `${minutes}м ${seconds}с`;
        } else {
            countdownText = `${seconds}с`;
        }
        
        timer.querySelector('.countdown-text').textContent = 'Осталось: ';
        timer.querySelector('.countdown-value').textContent = countdownText;
        
        // Меняем цвет при приближении времени
        if (diff < 3600000) { // Меньше часа
            timer.style.color = '#e74c3c';
        } else if (diff < 86400000) { // Меньше суток
            timer.style.color = '#f39c12';
        } else {
            timer.style.color = '#3498db';
        }
    });
}

// Обновляем обратный отсчет каждую секунду
setInterval(updateCountdowns, 1000);
updateCountdowns(); // Первый запуск сразу

// Обратный отсчет для маленьких таймеров в списке следующих публикаций
function updateSmallCountdowns() {
    const countdowns = document.querySelectorAll('.countdown-timer-small');
    
    countdowns.forEach(timer => {
        const publishAtStr = timer.getAttribute('data-publish-at');
        if (!publishAtStr) return;
        
        const publishAt = new Date(publishAtStr.replace(' ', 'T'));
        const now = new Date();
        const diff = publishAt - now;
        
        if (diff <= 0) {
            timer.querySelector('.countdown-text-small').textContent = '';
            timer.querySelector('.countdown-value-small').textContent = 'Время прошло';
            timer.style.color = '#e74c3c';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        let countdownText = '';
        if (days > 0) {
            countdownText = `${days}д ${hours}ч ${minutes}м`;
        } else if (hours > 0) {
            countdownText = `${hours}ч ${minutes}м ${seconds}с`;
        } else if (minutes > 0) {
            countdownText = `${minutes}м ${seconds}с`;
        } else {
            countdownText = `${seconds}с`;
        }
        
        timer.querySelector('.countdown-text-small').textContent = 'Осталось: ';
        timer.querySelector('.countdown-value-small').textContent = countdownText;
        
        // Меняем цвет при приближении времени
        if (diff < 3600000) { // Меньше часа
            timer.style.color = '#e74c3c';
        } else if (diff < 86400000) { // Меньше суток
            timer.style.color = '#f39c12';
        } else {
            timer.style.color = '#3498db';
        }
    });
}

// Обновляем маленькие таймеры каждую секунду
setInterval(updateSmallCountdowns, 1000);
updateSmallCountdowns(); // Первый запуск сразу
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
