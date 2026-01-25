<?php
$title = 'Расписания публикаций';
ob_start();

// Получаем параметры фильтрации
$filterStatus = $_GET['status'] ?? 'all';
$filterPlatform = $_GET['platform'] ?? 'all';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterType = $_GET['type'] ?? 'all'; // all, single, group
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

// Подсчет статистики
$stats = [
    'total' => count($schedules),
    'pending' => 0,
    'published' => 0,
    'failed' => 0,
    'processing' => 0,
    'paused' => 0,
];

foreach ($schedules as $schedule) {
    if (isset($schedule['status'])) {
        if ($schedule['status'] === 'pending') $stats['pending']++;
        elseif ($schedule['status'] === 'published') $stats['published']++;
        elseif ($schedule['status'] === 'failed') $stats['failed']++;
        elseif ($schedule['status'] === 'processing') $stats['processing']++;
        elseif ($schedule['status'] === 'paused') $stats['paused']++;
    }
}

// Фильтрация
$filteredSchedules = $schedules;
if ($filterStatus !== 'all') {
    $filteredSchedules = array_filter($filteredSchedules, function($s) use ($filterStatus) {
        return $s['status'] === $filterStatus;
    });
}
if ($filterPlatform !== 'all') {
    $filteredSchedules = array_filter($filteredSchedules, function($s) use ($filterPlatform) {
        return $s['platform'] === $filterPlatform;
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

// Сортировка
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

$videoRepo = new \App\Repositories\VideoRepository();
$groupRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupRepository();
?>

<h1>Расписания публикаций</h1>

<div class="schedules-header">
    <div class="header-actions">
        <a href="/schedules/create" class="btn btn-primary"><?= \App\Helpers\IconHelper::render('add', 20, 'icon-inline') ?> Создать расписание</a>
        <a href="/content-groups/schedules/create" class="btn btn-success"><?= \App\Helpers\IconHelper::render('calendar', 20, 'icon-inline') ?> Умное расписание</a>
    </div>
    
    <!-- Статистика -->
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
        <div class="stat-item stat-failed">
            <span class="stat-value"><?= $stats['failed'] ?></span>
            <span class="stat-label">Ошибки</span>
        </div>
    </div>
</div>

<!-- Фильтры -->
<div class="filters-panel">
    <form method="GET" action="/schedules" class="filters-form" id="filtersForm">
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
    <div class="empty-state">
        <div class="empty-icon"><?= \App\Helpers\IconHelper::render('calendar', 64) ?></div>
        <h3>Нет расписаний</h3>
        <p><?= count($schedules) > 0 ? 'Попробуйте изменить фильтры' : 'Создайте ваше первое расписание' ?></p>
        <?php if (count($schedules) === 0): ?>
            <a href="/schedules/create" class="btn btn-primary">Создать расписание</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="schedules-table-container">
        <table class="schedules-table">
            <thead>
                <tr>
                    <th style="width: 30px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                    </th>
                    <th>Видео / Группа</th>
                    <th>Платформа</th>
                    <th>Дата публикации</th>
                    <th>Статус</th>
                    <th style="width: 200px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredSchedules as $schedule): 
                    $video = null;
                    $group = null;
                    
                    if ($schedule['video_id']) {
                        $video = $videoRepo->findById($schedule['video_id']);
                    }
                    if ($schedule['content_group_id']) {
                        $group = $groupRepo->findById($schedule['content_group_id']);
                    }
                ?>
                <tr class="schedule-row" data-status="<?= $schedule['status'] ?>" data-id="<?= $schedule['id'] ?>" data-publish-at="<?= $schedule['publish_at'] ?>">
                    <td>
                        <input type="checkbox" class="schedule-checkbox" value="<?= $schedule['id'] ?>">
                    </td>
                    <td>
                        <?php if ($video): ?>
                            <div class="video-info">
                                <a href="/videos/<?= $video['id'] ?>" class="video-link">
                                    <?= \App\Helpers\IconHelper::render('video', 16, 'icon-inline') ?> <?= htmlspecialchars($video['title'] ?? $video['file_name']) ?>
                                </a>
                            </div>
                        <?php elseif ($group): ?>
                            <div class="group-info">
                                <a href="/content-groups/<?= $group['id'] ?>" class="group-link">
                                    <?= \App\Helpers\IconHelper::render('folder', 16, 'icon-inline') ?> <?= htmlspecialchars($group['name']) ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">ID: <?= $schedule['video_id'] ?? $schedule['content_group_id'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="platform-badge platform-<?= $schedule['platform'] ?>">
                            <?php
                            $platformIcons = [
                                'youtube' => \App\Helpers\IconHelper::render('youtube', 16, 'icon-inline'),
                                'telegram' => \App\Helpers\IconHelper::render('telegram', 16, 'icon-inline'),
                                'tiktok' => \App\Helpers\IconHelper::render('tiktok', 16, 'icon-inline'),
                                'instagram' => \App\Helpers\IconHelper::render('instagram', 16, 'icon-inline'),
                                'pinterest' => \App\Helpers\IconHelper::render('pinterest', 16, 'icon-inline'),
                                'both' => \App\Helpers\IconHelper::render('youtube', 16, 'icon-inline') . \App\Helpers\IconHelper::render('telegram', 16, 'icon-inline')
                            ];
                            echo $platformIcons[$schedule['platform']] ?? \App\Helpers\IconHelper::render('upload', 16, 'icon-inline');
                            ?>
                            <?= ucfirst($schedule['platform']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="date-info">
                            <div class="date-main"><?= date('d.m.Y', strtotime($schedule['publish_at'])) ?></div>
                            <div class="date-time"><?= date('H:i', strtotime($schedule['publish_at'])) ?></div>
                            <?php if ($schedule['status'] === 'pending'): 
                                // Определяем причину просрочки, если время прошло
                                $overdueReason = null;
                                $publishAt = strtotime($schedule['publish_at']);
                                $now = time();
                                
                                if ($publishAt <= $now) {
                                    $reasons = [];
                                    
                                    // Проверяем наличие видео
                                    if (!empty($schedule['video_id'])) {
                                        $videoRepo = new \App\Repositories\VideoRepository();
                                        $video = $videoRepo->findById($schedule['video_id']);
                                        if (!$video || ($video['status'] ?? '') !== 'ready') {
                                            $reasons[] = 'Видео не готово';
                                        }
                                    }
                                    
                                    // Проверяем интеграцию
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
                                    
                                    if (empty($reasons)) {
                                        $reasons[] = 'Время публикации прошло';
                                    }
                                    
                                    $overdueReason = implode(', ', $reasons);
                                }
                            ?>
                                <div class="countdown-timer" 
                                     data-publish-at="<?= $schedule['publish_at'] ?>" 
                                     data-overdue-reason="<?= htmlspecialchars($overdueReason ?? '', ENT_QUOTES) ?>"
                                     style="margin-top: 0.5rem; font-size: 0.85rem; color: #3498db; font-weight: 500;">
                                    <span class="countdown-text">Осталось: </span>
                                    <span class="countdown-value">-</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $schedule['status'] ?>">
                            <?php
                            $statusIcons = [
                                'pending' => \App\Helpers\IconHelper::render('wait', 16, 'icon-inline'),
                                'processing' => \App\Helpers\IconHelper::render('settings', 16, 'icon-inline'),
                                'published' => \App\Helpers\IconHelper::render('success', 16, 'icon-inline'),
                                'failed' => \App\Helpers\IconHelper::render('error', 16, 'icon-inline'),
                                'paused' => \App\Helpers\IconHelper::render('pause', 16, 'icon-inline')
                            ];
                            echo $statusIcons[$schedule['status']] ?? '';
                            ?>
                            <?= ucfirst($schedule['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="schedule-actions">
                            <a href="/schedules/<?= $schedule['id'] ?>" class="btn-action btn-view" title="Просмотр"><?= \App\Helpers\IconHelper::render('view', 20) ?></a>
                            
                            <?php 
                            // Кнопка включения/выключения - показываем для всех статусов, кроме processing
                            if ($schedule['status'] !== 'processing'): 
                                if ($schedule['status'] === 'pending'): ?>
                                    <button type="button" class="btn-action btn-pause" onclick="pauseSchedule(<?= $schedule['id'] ?>)" title="Приостановить"><?= \App\Helpers\IconHelper::render('pause', 20) ?></button>
                                <?php elseif ($schedule['status'] === 'paused'): ?>
                                    <button type="button" class="btn-action btn-play" onclick="resumeSchedule(<?= $schedule['id'] ?>)" title="Возобновить"><?= \App\Helpers\IconHelper::render('play', 20) ?></button>
                                <?php elseif (in_array($schedule['status'], ['published', 'failed', 'cancelled'])): ?>
                                    <button type="button" class="btn-action btn-play" onclick="resumeSchedule(<?= $schedule['id'] ?>)" title="Включить"><?= \App\Helpers\IconHelper::render('play', 20) ?></button>
                                <?php endif; 
                            endif; ?>
                            
                            <?php 
                            // Кнопка редактирования - показываем только для расписаний, которые можно редактировать
                            $canEdit = !in_array($schedule['status'], ['published', 'processing']);
                            if ($canEdit): ?>
                                <button type="button" class="btn-action btn-edit" onclick="editSchedule(<?= $schedule['id'] ?>)" title="Редактировать"><?= \App\Helpers\IconHelper::render('edit', 20) ?></button>
                            <?php endif; ?>
                            
                            <?php if ($schedule['status'] === 'pending' || $schedule['status'] === 'paused'): ?>
                                <button type="button" class="btn-action btn-copy" onclick="duplicateSchedule(<?= $schedule['id'] ?>)" title="Копировать"><?= \App\Helpers\IconHelper::render('copy', 20) ?></button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-action btn-delete" onclick="deleteSchedule(<?= $schedule['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 20) ?></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Массовые действия -->
    <div class="bulk-actions" id="bulkActions" style="display: none;">
        <div class="bulk-actions-content">
            <span class="bulk-count">Выбрано: <strong id="selectedCount">0</strong></span>
            <div class="bulk-buttons">
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
    window.location.href = '/schedules';
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

function pauseSchedule(id) {
    if (!confirm('Приостановить это расписание?')) return;
    
    fetch('/schedules/' + id + '/pause', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Расписание приостановлено', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось приостановить'), 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showToast('Произошла ошибка', 'error');
    });
}

function resumeSchedule(id) {
    if (!confirm('Возобновить/Включить это расписание?')) return;
    
    fetch('/schedules/' + id + '/resume', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Расписание возобновлено', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось возобновить'), 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showToast('Произошла ошибка', 'error');
    });
}

function duplicateSchedule(id) {
    if (!confirm('Создать копию этого расписания?')) return;
    
    fetch('/schedules/' + id + '/duplicate', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Расписание скопировано', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось скопировать'), 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showToast('Произошла ошибка', 'error');
    });
}

function editSchedule(id) {
    window.location.href = '/schedules/' + id + '/edit';
}

function deleteSchedule(id) {
    if (!confirm('Удалить это расписание?')) return;
    if (!confirm('Вы уверены? Это действие нельзя отменить.')) return;
    
    fetch('/schedules/' + id, {
        method: 'DELETE',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Расписание удалено', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось удалить'), 'error');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        showToast('Произошла ошибка', 'error');
    });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function bulkPause() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Приостановить выбранные расписания (' + ids.length + ')?')) return;
    
    fetch('/schedules/bulk-pause', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ids: ids})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Расписания приостановлены', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось приостановить'), 'error');
        }
    });
}

function bulkResume() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Возобновить выбранные расписания (' + ids.length + ')?')) return;
    
    fetch('/schedules/bulk-resume', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ids: ids})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Расписания возобновлены', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось возобновить'), 'error');
        }
    });
}

function bulkDelete() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Удалить выбранные расписания (' + ids.length + ')?')) return;
    if (!confirm('ВНИМАНИЕ: Это действие нельзя отменить!')) return;
    
    fetch('/schedules/bulk-delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ids: ids})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Расписания удалены', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось удалить'), 'error');
        }
    });
}

function getSelectedIds() {
    const checked = document.querySelectorAll('.schedule-checkbox:checked');
    return Array.from(checked).map(cb => parseInt(cb.value));
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
