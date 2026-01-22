<?php
$title = 'Группы контента';
ob_start();
?>

<h1>Группы контента</h1>

<a href="/content-groups/create" class="btn btn-primary">Создать группу</a>

<?php if (empty($groups)): ?>
    <p style="margin-top: 2rem;">Нет созданных групп</p>
<?php else: ?>
    <?php 
    // Получаем все шаблоны для отображения
    $templateService = new \App\Modules\ContentGroups\Services\TemplateService();
    $allTemplates = $templateService->getUserTemplates($_SESSION['user_id'], true);
    $templatesMap = [];
    foreach ($allTemplates as $template) {
        $templatesMap[$template['id']] = $template;
    }
    ?>
    <div class="groups-grid">
        <?php foreach ($groups as $group): ?>
            <div class="group-card <?= $group['status'] === 'active' ? 'group-card-active' : 'group-card-paused' ?>">
                <div class="group-card-header">
                    <h3 class="group-title"><?= htmlspecialchars($group['name']) ?></h3>
                    <span class="group-status-badge badge-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'paused' ? 'warning' : 'secondary') ?>">
                        <?= $group['status'] === 'active' ? '● Активна' : '⏸ На паузе' ?>
                    </span>
                </div>
                
                <?php if ($group['description']): ?>
                    <p class="group-description"><?= htmlspecialchars($group['description']) ?></p>
                <?php endif; ?>
                
                <div class="group-info-box">
                    <div class="group-info-item">
                        <span class="info-label">Шаблон:</span>
                        <?php if ($group['template_id'] && isset($templatesMap[$group['template_id']])): ?>
                            <span class="info-value info-value-success">✓ <?= htmlspecialchars($templatesMap[$group['template_id']]['name']) ?></span>
                        <?php else: ?>
                            <span class="info-value info-value-muted">Без шаблона</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="group-stats-grid">
                    <?php if (isset($group['stats'])): ?>
                        <div class="stat-item">
                            <div class="stat-value"><?= $group['stats']['total_files'] ?? 0 ?></div>
                            <div class="stat-label">Всего</div>
                        </div>
                        <div class="stat-item stat-success">
                            <div class="stat-value"><?= $group['stats']['published_count'] ?? 0 ?></div>
                            <div class="stat-label">Опубликовано</div>
                        </div>
                        <div class="stat-item stat-warning">
                            <div class="stat-value"><?= $group['stats']['queued_count'] ?? 0 ?></div>
                            <div class="stat-label">В очереди</div>
                        </div>
                        <div class="stat-item stat-danger">
                            <div class="stat-value"><?= $group['stats']['error_count'] ?? 0 ?></div>
                            <div class="stat-label">Ошибки</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="group-actions">
                    <div class="action-group action-group-primary">
                        <a href="/content-groups/<?= $group['id'] ?>" class="btn-action btn-action-primary" title="Открыть группу">
                            <span class="btn-icon">👁</span>
                            <span class="btn-text">Открыть</span>
                        </a>
                        <a href="/content-groups/<?= $group['id'] ?>/edit" class="btn-action btn-action-info" title="Редактировать">
                            <span class="btn-icon">✏️</span>
                            <span class="btn-text">Изменить</span>
                        </a>
                    </div>
                    
                    <div class="action-group action-group-secondary">
                        <button type="button" class="btn-action btn-action-<?= $group['status'] === 'active' ? 'warning' : 'success' ?>" 
                                onclick="toggleGroupStatus(<?= $group['id'] ?>, '<?= $group['status'] ?>')" 
                                title="<?= $group['status'] === 'active' ? 'Приостановить публикации' : 'Возобновить публикации' ?>">
                            <span class="btn-icon"><?= $group['status'] === 'active' ? '⏸' : '▶' ?></span>
                            <span class="btn-text"><?= $group['status'] === 'active' ? 'Пауза' : 'Включить' ?></span>
                        </button>
                        <button type="button" class="btn-action btn-action-secondary" onclick="duplicateGroup(<?= $group['id'] ?>)" title="Создать копию группы">
                            <span class="btn-icon">📋</span>
                            <span class="btn-text">Копировать</span>
                        </button>
                        <button type="button" class="btn-action btn-action-secondary" onclick="shuffleGroup(<?= $group['id'] ?>)" title="Перемешать порядок видео">
                            <span class="btn-icon">🔀</span>
                            <span class="btn-text">Перемешать</span>
                        </button>
                    </div>
                    
                    <div class="action-group action-group-danger">
                        <button type="button" class="btn-action btn-action-danger" onclick="deleteGroup(<?= $group['id'] ?>)" title="Удалить группу">
                            <span class="btn-icon">🗑</span>
                            <span class="btn-text">Удалить</span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function toggleGroupStatus(id, currentStatus) {
    const action = currentStatus === 'active' ? 'выключить' : 'включить';
    if (!confirm('Вы уверены, что хотите ' + action + ' эту группу?')) {
        return;
    }
    
    fetch('/content-groups/' + id + '/toggle-status', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Статус группы изменен');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось изменить статус группы'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function duplicateGroup(id) {
    if (!confirm('Создать копию этой группы? Все видео из группы будут скопированы.')) {
        return;
    }
    
    fetch('/content-groups/' + id + '/duplicate', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Группа успешно скопирована!');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось скопировать группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function deleteGroup(id) {
    if (!confirm('Вы уверены, что хотите удалить эту группу? Это действие нельзя отменить.')) {
        return;
    }
    
    if (!confirm('ВНИМАНИЕ: Все видео останутся, но будут удалены из группы. Продолжить?')) {
        return;
    }
    
    fetch('/content-groups/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Группа удалена');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить группу'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
