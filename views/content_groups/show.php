<?php
$title = 'Группа: ' . htmlspecialchars($group['name']);
ob_start();
?>

<h1><?= htmlspecialchars($group['name']) ?></h1>

<?php if ($group['description']): ?>
    <p><?= htmlspecialchars($group['description']) ?></p>
<?php endif; ?>

<div class="info-card group-stats">
    <h3>Статистика группы</h3>
    <?php if (isset($group['stats'])): ?>
        <div class="group-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <div class="stat-item">
                <div class="stat-label">Всего файлов:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #3498db;"><?= $group['stats']['total_files'] ?? 0 ?></div>
            </div>
            <div class="stat-item stat-success">
                <div class="stat-label">Опубликовано:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #27ae60;"><?= $group['stats']['published_count'] ?? 0 ?></div>
            </div>
            <div class="stat-item stat-warning">
                <div class="stat-label">В очереди:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #f39c12;"><?= $group['stats']['queued_count'] ?? 0 ?></div>
            </div>
            <div class="stat-item stat-danger">
                <div class="stat-label">Ошибки:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #e74c3c;"><?= $group['stats']['error_count'] ?? 0 ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Новых:</div>
                <div class="stat-value" style="font-size: 1.5rem; color: #95a5a6;"><?= $group['stats']['new_count'] ?? 0 ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="info-card group-info">
    <h3>Информация о группе</h3>
    <div class="info-card-grid">
        <div class="info-card-item">
            <div class="info-card-label">Текущий шаблон:</div>
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
                    <div class="info-card-value" style="color: #27ae60;"><?= htmlspecialchars($currentTemplate['name']) ?></div>
                <?php else: ?>
                    <div class="info-card-value" style="color: #e74c3c;">Шаблон не найден (ID: <?= $group['template_id'] ?>)</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="info-card-value" style="color: #95a5a6;">Без шаблона</div>
            <?php endif; ?>
        </div>
        <div class="info-card-item">
            <div class="info-card-label">Статус:</div>
            <div class="info-card-value">
                <span class="badge badge-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'paused' ? 'warning' : 'secondary') ?>">
                    <?= ucfirst($group['status']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="form-actions group-actions">
    <a href="/content-groups" class="btn btn-secondary">Назад к списку</a>
    <a href="/content-groups/<?= $group['id'] ?>/edit" class="btn btn-primary">Редактировать группу</a>
    <button type="button" class="btn btn-info" onclick="shuffleGroup(<?= $group['id'] ?>)">Перемешать видео</button>
    <a href="/content-groups/schedules/create?group_id=<?= $group['id'] ?>" class="btn btn-success">Создать расписание</a>
</div>

<div class="group-files" style="margin-top: 2rem;">
    <h2>Видео в группе</h2>
    
    <?php if (empty($files)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('video', 64) ?></div>
            <h3>Нет видео в группе</h3>
            <p>Добавьте видео в эту группу для автоматической публикации</p>
            <a href="/videos" class="btn btn-primary">Добавить видео</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Статус</th>
                    <th>Порядок</th>
                    <th>Опубликовано</th>
                    <?php if ($group['status'] === 'active'): ?>
                        <th>Следующая публикация</th>
                    <?php endif; ?>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td>
                            <a href="/videos/<?= $file['video_id'] ?>"><?= htmlspecialchars($file['title'] ?? $file['file_name'] ?? 'Без названия') ?></a>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $file['status'] ?>">
                                <?= ucfirst($file['status']) ?>
                            </span>
                        </td>
                        <td><?= $file['order_index'] ?></td>
                        <td>
                            <?= $file['published_at'] ? date('d.m.Y H:i', strtotime($file['published_at'])) : '-' ?>
                        </td>
                        <?php if ($group['status'] === 'active'): ?>
                            <td>
                                <?php if (isset($nextPublishDates[$file['id']])): ?>
                                    <span style="color: #3498db; font-weight: 500;">
                                        <?= date('d.m.Y H:i', strtotime($nextPublishDates[$file['id']])) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <div class="action-buttons">
                                <a href="/videos/<?= $file['video_id'] ?>" class="btn btn-sm btn-primary">Просмотр</a>
                                <?php if (isset($filePublications[$file['video_id']])): 
                                    $pub = $filePublications[$file['video_id']];
                                    $pubUrl = $pub['platform_url'] ?? '';
                                    if (!$pubUrl && $pub['platform_id']) {
                                        switch ($pub['platform']) {
                                            case 'youtube':
                                                $pubUrl = 'https://youtube.com/watch?v=' . $pub['platform_id'];
                                                break;
                                            case 'telegram':
                                                $pubUrl = 'https://t.me/' . $pub['platform_id'];
                                                break;
                                            case 'tiktok':
                                                $pubUrl = 'https://www.tiktok.com/@' . $pub['platform_id'];
                                                break;
                                            case 'instagram':
                                                $pubUrl = 'https://www.instagram.com/p/' . $pub['platform_id'];
                                                break;
                                            case 'pinterest':
                                                $pubUrl = 'https://www.pinterest.com/pin/' . $pub['platform_id'];
                                                break;
                                        }
                                    }
                                    if ($pubUrl):
                                ?>
                                    <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" class="btn btn-sm btn-success" title="Перейти к публикации на <?= ucfirst($pub['platform']) ?>"><?= \App\Helpers\IconHelper::render('publish', 16, 'icon-inline') ?> Перейти</a>
                                <?php endif; endif; ?>
                                <button type="button" class="btn btn-sm <?= ($file['status'] === 'new' || $file['status'] === 'queued') ? 'btn-warning' : 'btn-success' ?>" 
                                        onclick="toggleFileStatus(<?= $group['id'] ?>, <?= $file['id'] ?>, '<?= $file['status'] ?>')">
                                    <?= ($file['status'] === 'new' || $file['status'] === 'queued') ? \App\Helpers\IconHelper::render('pause', 16, 'icon-inline') . ' Выкл' : \App\Helpers\IconHelper::render('play', 16, 'icon-inline') . ' Вкл' ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeFromGroup(<?= $group['id'] ?>, <?= $file['video_id'] ?>)"><?= \App\Helpers\IconHelper::render('delete', 16, 'icon-inline') ?> Удалить</button>
                            </div>
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
            showToast('Видео удалено из группы', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + (data.message || 'Не удалось удалить видео из группы'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка', 'error');
    });
}

function toggleFileStatus(groupId, fileId, currentStatus) {
    // Определяем новый статус: если файл активен (new, queued) - ставим paused, иначе - new
    let newStatus;
    if (currentStatus === 'new' || currentStatus === 'queued') {
        newStatus = 'paused';
    } else if (currentStatus === 'paused') {
        newStatus = 'new';
    } else {
        // Для published, error - возвращаем в new
        newStatus = 'new';
    }
    
    console.log('toggleFileStatus: groupId=' + groupId + ', fileId=' + fileId + ', currentStatus=' + currentStatus + ', newStatus=' + newStatus);
    
    fetch('/content-groups/' + groupId + '/files/' + fileId + '/toggle-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({status: newStatus})
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Ошибка сервера (HTTP ' + response.status + ')');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Статус файла изменен', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            const errorMsg = data.message || 'Не удалось изменить статус';
            console.error('Toggle file status error:', data);
            showToast('Ошибка: ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Произошла ошибка: ' + error.message, 'error');
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
