<?php
$title = 'Расписания публикаций';
ob_start();
?>

<h1>Расписания публикаций</h1>
<a href="/schedules/create" class="btn btn-primary">Создать расписание</a>

<?php if (empty($schedules)): ?>
    <p>Нет созданных расписаний</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Видео</th>
                <th>Платформа</th>
                <th>Дата публикации</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $videoRepo = new \App\Repositories\VideoRepository();
            foreach ($schedules as $schedule): 
                $video = $videoRepo->findById($schedule['video_id']);
            ?>
            <tr>
                <td>
                    <?php if ($video): ?>
                        <a href="/videos/<?= $video['id'] ?>"><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></a>
                    <?php else: ?>
                        ID: <?= $schedule['video_id'] ?>
                    <?php endif; ?>
                    <?php if ($schedule['content_group_id']): ?>
                        <br><small style="color: #666;">Группа: #<?= $schedule['content_group_id'] ?></small>
                    <?php endif; ?>
                </td>
                <td><?= ucfirst($schedule['platform']) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($schedule['publish_at'])) ?></td>
                <td>
                    <span class="badge badge-<?= 
                        $schedule['status'] === 'published' ? 'success' : 
                        ($schedule['status'] === 'failed' ? 'danger' : 
                        ($schedule['status'] === 'processing' ? 'warning' : 'secondary')) 
                    ?>">
                        <?= ucfirst($schedule['status']) ?>
                    </span>
                </td>
                <td>
                    <a href="/schedules/<?= $schedule['id'] ?>" class="btn btn-sm btn-primary">Просмотр</a>
                    <?php if ($schedule['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteSchedule(<?= $schedule['id'] ?>)">Удалить</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div style="margin-top: 2rem;">
    <a href="/content-groups/schedules/create" class="btn btn-success">Создать умное расписание (для групп)</a>
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
