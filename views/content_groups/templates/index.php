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

<a href="/content-groups/templates/create" class="btn btn-primary">Создать шаблон</a>

<?php if (empty($templates)): ?>
    <p style="margin-top: 2rem;">Нет созданных шаблонов</p>
<?php else: ?>
    <div style="margin-top: 2rem;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Название</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Описание</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Статус</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 0.75rem;"><?= htmlspecialchars($template['name']) ?></td>
                        <td style="padding: 0.75rem;"><?= htmlspecialchars($template['description'] ?? '') ?></td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-<?= $template['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $template['is_active'] ? 'Активен' : 'Неактивен' ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem;">
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">Удалить</button>
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
