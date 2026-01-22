<?php
$title = 'Группа: ' . htmlspecialchars($group['name']);
ob_start();
?>

<h1><?= htmlspecialchars($group['name']) ?></h1>

<?php if ($group['description']): ?>
    <p><?= htmlspecialchars($group['description']) ?></p>
<?php endif; ?>

<div class="group-stats" style="margin: 1.5rem 0; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
    <h3>Статистика группы</h3>
    <?php if (isset($group['stats'])): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <div>
                <strong>Всего файлов:</strong>
                <div style="font-size: 1.5rem; color: #3498db;"><?= $group['stats']['total_files'] ?? 0 ?></div>
            </div>
            <div>
                <strong>Опубликовано:</strong>
                <div style="font-size: 1.5rem; color: #27ae60;"><?= $group['stats']['published_count'] ?? 0 ?></div>
            </div>
            <div>
                <strong>В очереди:</strong>
                <div style="font-size: 1.5rem; color: #f39c12;"><?= $group['stats']['queued_count'] ?? 0 ?></div>
            </div>
            <div>
                <strong>Ошибки:</strong>
                <div style="font-size: 1.5rem; color: #e74c3c;"><?= $group['stats']['error_count'] ?? 0 ?></div>
            </div>
            <div>
                <strong>Новых:</strong>
                <div style="font-size: 1.5rem; color: #95a5a6;"><?= $group['stats']['new_count'] ?? 0 ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="group-info" style="margin: 1.5rem 0; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
    <h3>Информация о группе</h3>
    <div style="margin-top: 0.5rem;">
        <strong>Текущий шаблон:</strong>
        <?php if ($group['template_id']): ?>
            <?php 
            $currentTemplate = null;
            foreach ($templates as $template) {
                if ($template['id'] == $group['template_id']) {
                    $currentTemplate = $template;
                    break;
                }
            }
            ?>
            <?php if ($currentTemplate): ?>
                <span style="color: #27ae60;"><?= htmlspecialchars($currentTemplate['name']) ?></span>
            <?php else: ?>
                <span style="color: #e74c3c;">Шаблон не найден (ID: <?= $group['template_id'] ?>)</span>
            <?php endif; ?>
        <?php else: ?>
            <span style="color: #95a5a6;">Без шаблона</span>
        <?php endif; ?>
    </div>
    <div style="margin-top: 0.5rem;">
        <strong>Статус:</strong> 
        <span class="badge badge-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'paused' ? 'warning' : 'secondary') ?>">
            <?= ucfirst($group['status']) ?>
        </span>
    </div>
</div>

<div class="group-actions" style="margin: 1.5rem 0;">
    <a href="/content-groups" class="btn btn-secondary">Назад к списку</a>
    <a href="/content-groups/<?= $group['id'] ?>/edit" class="btn btn-primary">Редактировать группу</a>
    <button type="button" class="btn btn-info" onclick="shuffleGroup(<?= $group['id'] ?>)">Перемешать видео</button>
    <a href="/content-groups/schedules/create?group_id=<?= $group['id'] ?>" class="btn btn-success">Создать расписание</a>
</div>

<div class="group-files" style="margin-top: 2rem;">
    <h2>Видео в группе</h2>
    
    <?php if (empty($files)): ?>
        <p>В группе пока нет видео. <a href="/videos">Добавить видео</a></p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Название</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Статус</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Порядок</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Опубликовано</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 0.75rem;">
                            <a href="/videos/<?= $file['video_id'] ?>"><?= htmlspecialchars($file['title'] ?? $file['file_name'] ?? 'Без названия') ?></a>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-<?= 
                                $file['status'] === 'published' ? 'success' : 
                                ($file['status'] === 'error' ? 'danger' : 
                                ($file['status'] === 'queued' ? 'warning' : 'secondary')) 
                            ?>">
                                <?= ucfirst($file['status']) ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem;"><?= $file['order_index'] ?></td>
                        <td style="padding: 0.75rem;">
                            <?= $file['published_at'] ? date('d.m.Y H:i', strtotime($file['published_at'])) : '-' ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <a href="/videos/<?= $file['video_id'] ?>" class="btn btn-sm btn-primary">Просмотр</a>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeFromGroup(<?= $group['id'] ?>, <?= $file['video_id'] ?>)">Удалить</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function shuffleGroup(id) {
    if (!confirm('Перемешать видео в группе?')) {
        return;
    }
    
    fetch('/content-groups/' + id + '/shuffle', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Группа перемешана успешно');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось перемешать группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function removeFromGroup(groupId, videoId) {
    if (!confirm('Удалить видео из группы?')) {
        return;
    }
    
    fetch('/content-groups/' + groupId + '/videos/' + videoId, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Видео удалено из группы');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить видео из группы'));
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
