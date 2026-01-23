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

<h1>Расписание публикации</h1>

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

    <div class="detail-item">
        <strong>Платформа:</strong> <?= ucfirst($schedule['platform']) ?>
    </div>

    <div class="detail-item">
        <strong>Дата публикации:</strong> <?= date('d.m.Y H:i', strtotime($schedule['publish_at'])) ?>
    </div>

    <div class="detail-item">
        <strong>Статус:</strong>
        <span class="badge badge-<?= 
            $schedule['status'] === 'published' ? 'success' : 
            ($schedule['status'] === 'failed' ? 'danger' : 
            ($schedule['status'] === 'processing' ? 'warning' : 'secondary')) 
        ?>">
            <?= ucfirst($schedule['status']) ?>
        </span>
    </div>

    <?php if ($schedule['content_group_id']): ?>
        <div class="detail-item">
            <strong>Группа:</strong>
            <a href="/content-groups/<?= $schedule['content_group_id'] ?>">Просмотр группы</a>
        </div>
    <?php endif; ?>

    <?php if ($schedule['schedule_type'] && $schedule['schedule_type'] !== 'fixed'): ?>
        <div class="detail-item">
            <strong>Тип расписания:</strong> <?= ucfirst($schedule['schedule_type']) ?>
        </div>
    <?php endif; ?>

    <?php if ($schedule['error_message']): ?>
        <div class="detail-item" style="color: #e74c3c;">
            <strong>Ошибка:</strong> <?= htmlspecialchars($schedule['error_message']) ?>
        </div>
    <?php endif; ?>
</div>

<div class="schedule-actions" style="margin-top: 2rem;">
    <a href="/schedules" class="btn btn-secondary">Назад к списку</a>
    <?php if ($schedule['status'] === 'pending'): ?>
        <button type="button" class="btn btn-danger" onclick="deleteSchedule(<?= $schedule['id'] ?>)">Удалить расписание</button>
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
