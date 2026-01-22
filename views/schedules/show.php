<?php
$title = 'Расписание публикации';
ob_start();

// Получаем информацию о видео
$videoRepo = new \App\Repositories\VideoRepository();
$video = $videoRepo->findById($schedule['video_id']);
?>

<h1>Расписание публикации</h1>

<div class="schedule-details">
    <div class="detail-item">
        <strong>Видео:</strong>
        <?php if ($video): ?>
            <a href="/videos/<?= $video['id'] ?>"><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></a>
        <?php else: ?>
            ID: <?= $schedule['video_id'] ?>
        <?php endif; ?>
    </div>

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
