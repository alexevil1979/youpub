<?php
$title = 'Редактировать шаблон';
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Редактировать шаблон</h1>
        <p class="page-subtitle">
            Внесите изменения в структуру оформления, переменные и статус шаблона.
        </p>
    </div>
</div>

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

<form method="POST" action="/content-groups/templates/<?= $template['id'] ?>/update" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="name">Название шаблона *</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($template['name'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="title_template">Шаблон названия</label>
        <input type="text" id="title_template" name="title_template" value="<?= htmlspecialchars($template['title_template'] ?? '') ?>" placeholder="Например: {random_emoji} {title} - часть {index}">
        <small>Доступные переменные: {title}, {group_name}, {index}, {date}, {platform}, {random_emoji}</small>
    </div>

    <div class="form-group">
        <label for="description_template">Шаблон описания</label>
        <textarea id="description_template" name="description_template" rows="5" placeholder="Например: {random_emoji} Видео из группы {group_name}, часть {index}"><?= htmlspecialchars($template['description_template'] ?? '') ?></textarea>
        <small>Доступные переменные: {title}, {group_name}, {index}, {date}, {platform}, {random_emoji}</small>
    </div>

    <div class="form-group">
        <label for="tags_template">Шаблон тегов</label>
        <input type="text" id="tags_template" name="tags_template" value="<?= htmlspecialchars($template['tags_template'] ?? '') ?>" placeholder="Например: видео, {group_name}, часть {index}">
        <small>Доступные переменные: {title}, {group_name}, {index}, {date}, {platform}, {random_emoji}</small>
    </div>

    <div class="form-group">
        <label for="emoji_list">Список emoji (через запятую)</label>
        <input type="text" id="emoji_list" name="emoji_list" value="<?= htmlspecialchars(implode(', ', json_decode($template['emoji_list'] ?? '[]', true) ?: [])) ?>" placeholder="🎬, 🎥, 📹, 🎞️">
        <small>Случайный emoji будет использоваться в шаблонах через переменную {random_emoji}</small>
    </div>

    <div class="form-group">
        <label>Варианты описания (рандомизация)</label>
        <input type="text" name="variant_1" value="<?= htmlspecialchars(json_decode($template['variants'] ?? '{}', true)['description'][0] ?? '') ?>" placeholder="Вариант 1">
        <input type="text" name="variant_2" value="<?= htmlspecialchars(json_decode($template['variants'] ?? '{}', true)['description'][1] ?? '') ?>" placeholder="Вариант 2" style="margin-top: 0.5rem;">
        <input type="text" name="variant_3" value="<?= htmlspecialchars(json_decode($template['variants'] ?? '{}', true)['description'][2] ?? '') ?>" placeholder="Вариант 3" style="margin-top: 0.5rem;">
        <small>Если указаны варианты, будет выбран случайный при применении шаблона</small>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="is_active" value="1" <?= ($template['is_active'] ?? 1) ? 'checked' : '' ?>>
            Активен
        </label>
        <small>Неактивные шаблоны не будут доступны для выбора</small>
    </div>

    <div class="form-actions">
        <a href="/content-groups/templates" class="btn btn-secondary">Отмена</a>
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
