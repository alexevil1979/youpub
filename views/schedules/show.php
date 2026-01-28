<?php
$title = 'Расписание публикации';
ob_start();

// Получаем информацию о видео (если есть video_id)
$video = null;
if (!empty($schedule['video_id'])) {
    try {
        $videoRepo = new \App\Repositories\VideoRepository();
        $video = $videoRepo->findById($schedule['video_id']);
    } catch (\Exception $e) {
        error_log("Error loading video for schedule: " . $e->getMessage());
    }
}

// Получаем информацию о группе (если есть content_group_id)
$group = null;
if (!empty($schedule['content_group_id'])) {
    try {
        $groupRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupRepository();
        $group = $groupRepo->findById($schedule['content_group_id']);
    } catch (\Exception $e) {
        error_log("Error loading group for schedule: " . $e->getMessage());
    }
}
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Расписание публикации</h1>
        <p class="page-subtitle">
            Детальная информация о выбранном расписании: видео или группа, платформа и статус.
        </p>
    </div>
</div>

<div class="info-card schedule-details">
    <div class="info-card-grid">
        <?php if ($video): ?>
            <div class="info-card-item">
                <div class="info-card-label">Видео:</div>
                <div class="info-card-value">
                    <a href="/videos/<?= $video['id'] ?>"><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></a>
                </div>
            </div>
        <?php elseif ($group): ?>
            <div class="info-card-item">
                <div class="info-card-label">Группа контента:</div>
                <div class="info-card-value">
                    <a href="/content-groups/<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
                </div>
            </div>
        <?php elseif (!empty($schedule['video_id'])): ?>
            <div class="info-card-item">
                <div class="info-card-label">Видео:</div>
                <div class="info-card-value" style="color: #e74c3c;">ID: <?= $schedule['video_id'] ?> (не найдено)</div>
            </div>
        <?php endif; ?>

        <div class="info-card-item">
            <div class="info-card-label">Платформа:</div>
            <div class="info-card-value">
                <span class="platform-badge platform-<?= $schedule['platform'] ?? 'unknown' ?>">
                    <?= ucfirst($schedule['platform'] ?? 'Неизвестно') ?>
                </span>
            </div>
        </div>

        <div class="info-card-item">
            <div class="info-card-label">Дата публикации:</div>
            <div class="info-card-value">
                <?= !empty($schedule['publish_at']) ? date('d.m.Y H:i', strtotime($schedule['publish_at'])) : 'Не указана' ?>
            </div>
        </div>

        <div class="info-card-item">
            <div class="info-card-label">Статус:</div>
            <div class="info-card-value">
                <span class="status-badge status-<?= $schedule['status'] ?? 'unknown' ?>">
                    <?= ucfirst($schedule['status'] ?? 'Неизвестно') ?>
                </span>
            </div>
        </div>

        <?php if (!empty($schedule['schedule_type']) && $schedule['schedule_type'] !== 'fixed'): ?>
            <div class="info-card-item">
                <div class="info-card-label">Тип расписания:</div>
                <div class="info-card-value">
                    <?php
                    $scheduleTypeNames = [
                        'fixed' => 'Фиксированное',
                        'interval' => 'Интервальное',
                        'batch' => 'Пакетное',
                        'random' => 'Случайное',
                        'wave' => 'Волновое'
                    ];
                    echo htmlspecialchars($scheduleTypeNames[$schedule['schedule_type']] ?? ucfirst($schedule['schedule_type']));
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($schedule['error_message'])): ?>
            <div class="info-card-item" style="grid-column: 1 / -1;">
                <div class="info-card-label" style="color: #e74c3c;">Ошибка:</div>
                <div class="info-card-value" style="color: #e74c3c;">
                    <?= htmlspecialchars($schedule['error_message']) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="form-actions schedule-actions">
    <a href="/schedules" class="btn btn-secondary">Назад к списку</a>
    <?php 
    $canEdit = !in_array($schedule['status'] ?? '', ['published', 'processing']);
    if ($canEdit): ?>
        <a href="/schedules/<?= $schedule['id'] ?>/edit" class="btn btn-primary"><?= \App\Helpers\IconHelper::render('edit', 16, 'icon-inline') ?> Редактировать</a>
    <?php endif; ?>
    <?php if (($schedule['status'] ?? '') === 'pending' || ($schedule['status'] ?? '') === 'paused'): ?>
        <button type="button" class="btn btn-danger" onclick="deleteSchedule(<?= $schedule['id'] ?>)"><?= \App\Helpers\IconHelper::render('delete', 16, 'icon-inline') ?> Удалить расписание</button>
    <?php endif; ?>
</div>

<script>
function deleteSchedule(id) {
    if (!confirm('Удалить расписание?')) {
        return;
    }
    
    fetch('/schedules/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Расписание удалено');
            window.location.href = '/schedules';
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить расписание'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
