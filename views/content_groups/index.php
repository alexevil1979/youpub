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
    <div class="groups-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
        <?php foreach ($groups as $group): ?>
            <div class="group-card" style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3><?= htmlspecialchars($group['name']) ?></h3>
                <?php if ($group['description']): ?>
                    <p style="color: #666; margin: 0.5rem 0;"><?= htmlspecialchars($group['description']) ?></p>
                <?php endif; ?>
                
                <div class="group-info" style="margin: 1rem 0; padding: 0.75rem; background: #f8f9fa; border-radius: 4px;">
                    <p style="margin: 0.25rem 0;">
                        <strong>Шаблон:</strong> 
                        <?php if ($group['template_id'] && isset($templatesMap[$group['template_id']])): ?>
                            <span style="color: #27ae60;">✓ <?= htmlspecialchars($templatesMap[$group['template_id']]['name']) ?></span>
                        <?php else: ?>
                            <span style="color: #95a5a6;">Без шаблона</span>
                        <?php endif; ?>
                    </p>
                    <p style="margin: 0.25rem 0;">
                        <strong>Статус:</strong> 
                        <span class="badge badge-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'paused' ? 'warning' : 'secondary') ?>">
                            <?= ucfirst($group['status']) ?>
                        </span>
                    </p>
                </div>
                
                <div class="group-stats" style="margin: 1rem 0; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                    <?php if (isset($group['stats'])): ?>
                        <p><strong>Всего:</strong> <?= $group['stats']['total_files'] ?? 0 ?></p>
                        <p><strong>Опубликовано:</strong> <span style="color: #27ae60;"><?= $group['stats']['published_count'] ?? 0 ?></span></p>
                        <p><strong>В очереди:</strong> <?= $group['stats']['queued_count'] ?? 0 ?></p>
                        <p><strong>Ошибки:</strong> <span style="color: #e74c3c;"><?= $group['stats']['error_count'] ?? 0 ?></span></p>
                    <?php endif; ?>
                </div>

                <div class="group-actions" style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                    <a href="/content-groups/<?= $group['id'] ?>" class="btn btn-primary btn-sm">Открыть</a>
                    <a href="/content-groups/<?= $group['id'] ?>/edit" class="btn btn-info btn-sm">Редактировать</a>
                    <button type="button" class="btn btn-<?= $group['status'] === 'active' ? 'warning' : 'success' ?> btn-sm" onclick="toggleGroupStatus(<?= $group['id'] ?>, '<?= $group['status'] ?>')">
                        <?= $group['status'] === 'active' ? '⏸ Выключить' : '▶ Включить' ?>
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="duplicateGroup(<?= $group['id'] ?>)">📋 Копировать</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="shuffleGroup(<?= $group['id'] ?>)">🔀 Перемешать</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteGroup(<?= $group['id'] ?>)">🗑 Удалить</button>
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
