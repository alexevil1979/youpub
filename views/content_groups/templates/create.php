<?php
$title = 'Создать шаблон оформления';
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Создать шаблон оформления</h1>
        <p class="page-subtitle">
            Опишите структуру названия, описания и тегов, чтобы автоматизировать оформление публикаций.
        </p>
    </div>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<form method="POST" action="/content-groups/templates/create" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="name">Название шаблона *</label>
        <input type="text" id="name" name="name" required placeholder="Например: Шаблон для котиков">
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="2" placeholder="Описание шаблона (опционально)"></textarea>
    </div>

    <div class="form-group">
        <label for="title_template">Шаблон названия</label>
        <input type="text" id="title_template" name="title_template" placeholder="{title} | {group_name}">
        <small>Доступные переменные: {title}, {group_name}, {index}, {date}, {platform}</small>
    </div>

    <div class="form-group">
        <label for="description_template">Шаблон описания</label>
        <textarea id="description_template" name="description_template" rows="5" placeholder="🎬 {title}&#10;📁 Группа: {group_name}&#10;#{group_name} #видео {random_emoji}"></textarea>
        <small>Доступные переменные: {title}, {group_name}, {index}, {date}, {platform}, {random_emoji}</small>
    </div>

    <div class="form-group">
        <label for="tags_template">Шаблон тегов</label>
        <input type="text" id="tags_template" name="tags_template" placeholder="{group_name}, видео, {date}">
        <small>Доступные переменные: {title}, {group_name}, {index}, {date}, {platform}</small>
    </div>

    <div class="form-group">
        <label for="emoji_list">Список emoji (через запятую)</label>
        <input type="text" id="emoji_list" name="emoji_list" placeholder="😺,😸,😹,😻,😼,😽">
        <small>Эти emoji будут использоваться для переменной {random_emoji}</small>
    </div>

    <div class="form-group">
        <label>Варианты описания (для рандомизации)</label>
        <div class="variant-inputs">
            <input type="text" name="variant_1" placeholder="Вариант 1">
            <input type="text" name="variant_2" placeholder="Вариант 2">
            <input type="text" name="variant_3" placeholder="Вариант 3">
        </div>
        <small>Если указаны варианты, будет выбран случайный при публикации</small>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="is_active" value="1" checked> Активен
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Создать шаблон</button>
        <a href="/content-groups/templates" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
