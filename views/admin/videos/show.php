<?php
if (!$video) {
    http_response_code(404);
    echo 'Видео не найдено.';
    return;
}
$title = 'Видео #' . (int)$video['id'];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Видео #<?= (int)$video['id'] ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($video['title'] ?? $video['file_name'] ?? '') ?></p>
    </div>
    <div class="page-header-actions">
        <a href="/admin/videos" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<table class="profile-table data-table" style="max-width: 700px;">
    <tr>
        <th>ID</th>
        <td><?= (int)$video['id'] ?></td>
    </tr>
    <tr>
        <th>User ID</th>
        <td><?= (int)($video['user_id'] ?? 0) ?></td>
    </tr>
    <tr>
        <th>Название</th>
        <td><?= htmlspecialchars($video['title'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Имя файла</th>
        <td><?= htmlspecialchars($video['file_name'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Статус</th>
        <td><span class="badge badge-<?= $video['status'] ?? 'info' ?>"><?= htmlspecialchars($video['status'] ?? '') ?></span></td>
    </tr>
    <tr>
        <th>Размер</th>
        <td><?= isset($video['file_size']) ? number_format((int)$video['file_size'] / 1024 / 1024, 2) . ' MB' : '-' ?></td>
    </tr>
    <tr>
        <th>MIME</th>
        <td><?= htmlspecialchars($video['mime_type'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Путь к файлу</th>
        <td><code style="font-size: 0.85rem; word-break: break-all;"><?= htmlspecialchars($video['file_path'] ?? '-') ?></code></td>
    </tr>
    <tr>
        <th>Создано</th>
        <td><?= htmlspecialchars($video['created_at'] ?? '-') ?></td>
    </tr>
</table>

<p style="margin-top: 1.5rem;"><a href="/admin/videos" class="btn btn-secondary">← Назад к списку видео</a></p>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
