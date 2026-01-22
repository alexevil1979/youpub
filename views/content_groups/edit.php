<?php
$title = 'Редактировать группу: ' . htmlspecialchars($group['name']);
ob_start();
?>

<h1>Редактировать группу</h1>

<form method="POST" action="/content-groups/<?= $group['id'] ?>/edit" class="group-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="name">Название группы *</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($group['name']) ?>" required placeholder="Например: Котики, Мемы, Релакс">
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3" placeholder="Описание группы (опционально)"><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="template_id">Шаблон оформления (опционально)</label>
        <select id="template_id" name="template_id">
            <option value="">Без шаблона</option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= $template['id'] ?>" <?= ($group['template_id'] == $template['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($template['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Выберите шаблон для автоматического оформления публикаций из этой группы</small>
        <div style="margin-top: 0.5rem;">
            <a href="/content-groups/templates/create" target="_blank" class="btn btn-sm btn-secondary">Создать новый шаблон</a>
        </div>
    </div>

    <div class="form-group">
        <label for="status">Статус</label>
        <select id="status" name="status">
            <option value="active" <?= ($group['status'] === 'active') ? 'selected' : '' ?>>Активна</option>
            <option value="paused" <?= ($group['status'] === 'paused') ? 'selected' : '' ?>>На паузе</option>
            <option value="archived" <?= ($group['status'] === 'archived') ? 'selected' : '' ?>>Архивная</option>
        </select>
        <small>Группы на паузе не будут публиковать видео</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="/content-groups/<?= $group['id'] ?>" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
    <h3 style="margin-top: 0;">💡 О шаблонах</h3>
    <p>Шаблон оформления позволяет автоматически генерировать заголовки, описания и теги для публикаций из этой группы.</p>
    <p>Если шаблон не выбран, будут использоваться данные из самого видео (название, описание, теги).</p>
    <p><a href="/content-groups/templates">Управление шаблонами</a> | <a href="/content-groups/templates/create">Создать новый шаблон</a></p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
