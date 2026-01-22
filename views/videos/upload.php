<?php
$title = 'Загрузка видео';
ob_start();
?>

<h1>Загрузка видео</h1>

<form method="POST" action="/videos/upload" enctype="multipart/form-data" class="upload-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="video">Видео файл</label>
        <input type="file" id="video" name="video" accept="video/*" required>
        <small>Максимальный размер: 5GB</small>
    </div>

    <div class="form-group">
        <label for="title">Название</label>
        <input type="text" id="title" name="title" placeholder="Название видео">
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="5" placeholder="Описание видео"></textarea>
    </div>

    <div class="form-group">
        <label for="tags">Теги (через запятую)</label>
        <input type="text" id="tags" name="tags" placeholder="tag1, tag2, tag3">
    </div>

    <button type="submit" class="btn btn-primary">Загрузить</button>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
