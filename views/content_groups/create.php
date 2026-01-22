<?php
$title = 'Создать группу контента';
ob_start();
?>

<h1>Создать группу контента</h1>

<form method="POST" action="/content-groups/create" class="group-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="name">Название группы *</label>
        <input type="text" id="name" name="name" required placeholder="Например: Котики, Мемы, Релакс">
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3" placeholder="Описание группы (опционально)"></textarea>
    </div>

    <div class="form-group">
        <label for="template_id">Шаблон оформления (опционально)</label>
        <select id="template_id" name="template_id">
            <option value="">Без шаблона</option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <small>Можно выбрать шаблон позже</small>
    </div>

    <div class="form-group">
        <label for="status">Статус</label>
        <select id="status" name="status">
            <option value="active" selected>Активна</option>
            <option value="paused">На паузе</option>
            <option value="archived">Архивная</option>
        </select>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Создать группу</button>
        <a href="/content-groups" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
