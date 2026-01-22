<?php
$title = 'Редактировать видео';
ob_start();
?>

<h1>Редактировать видео</h1>

<form method="POST" action="/videos/<?= $video['id'] ?>/edit" class="video-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="title">Название</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($video['title'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="5"><?= htmlspecialchars($video['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="tags">Теги (через запятую)</label>
        <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($video['tags'] ?? '') ?>" placeholder="тег1, тег2, тег3">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/videos/<?= $video['id'] ?>" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
