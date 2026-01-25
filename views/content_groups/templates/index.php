<?php
$title = 'Шаблоны оформления';
ob_start();
?>

<h1>Шаблоны оформления</h1>

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

<a href="/content-groups/auto-shorts" class="btn btn-primary">🚀 Автогенерация Shorts</a>
<a href="/content-groups/templates/create-shorts" class="btn btn-primary">🎯 Создать Shorts шаблон</a>

<?php 
// Убеждаемся, что переменная определена
if (!isset($templates)) {
    $templates = [];
}
?>

<?php if (empty($templates)): ?>
    <div class="empty-state" style="margin-top: 2rem;">
        <div class="empty-state-icon"><?= \App\Helpers\IconHelper::render('file', 64) ?></div>
        <h3>Нет созданных шаблонов</h3>
        <p>Создайте первый шаблон для автоматического оформления публикаций</p>
        <a href="/content-groups/templates/create-shorts" class="btn btn-primary">🎯 Создать шаблон для Shorts</a>
    </div>
<?php else: ?>
    <div style="margin-top: 2rem;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Статус</th>
                    <th style="width: 150px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><?= htmlspecialchars($template['name']) ?></td>
                        <td><?= htmlspecialchars($template['description'] ?? '') ?></td>
                        <td>
                            <span class="status-badge status-<?= $template['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $template['is_active'] ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="/content-groups/templates/<?= $template['id'] ?>/edit" class="btn btn-xs btn-primary" title="Редактировать"><?= \App\Helpers\IconHelper::render('edit', 14, 'icon-inline') ?></a>
                                <button type="button" class="btn btn-xs btn-danger" onclick="deleteTemplate(<?= $template['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 14, 'icon-inline') ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
function deleteTemplate(id) {
    if (!confirm('Удалить шаблон?')) {
        return;
    }
    
    fetch('/content-groups/templates/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Шаблон удален');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить шаблон'));
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
include __DIR__ . '/../../layout.php';
?>
