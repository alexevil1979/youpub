<?php
$title = 'Видео';
$videos = $videos ?? [];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Видео</h1>
        <p class="page-subtitle">Все загруженные видео в системе.</p>
    </div>
    <div class="page-header-actions">
        <a href="/admin" class="btn btn-secondary">← Админ-панель</a>
    </div>
</div>

<?php if (empty($videos)): ?>
    <p>Видео нет.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Название</th>
                <th>Статус</th>
                <th>Размер</th>
                <th>Создано</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($videos as $v): ?>
            <tr>
                <td><?= (int)$v['id'] ?></td>
                <td><?= (int)($v['user_id'] ?? 0) ?></td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($v['title'] ?? $v['file_name'] ?? '-') ?></td>
                <td><span class="badge badge-<?= $v['status'] ?? 'info' ?>"><?= htmlspecialchars($v['status'] ?? '') ?></span></td>
                <td><?= isset($v['file_size']) ? number_format((int)$v['file_size'] / 1024 / 1024, 1) . ' MB' : '-' ?></td>
                <td><?= htmlspecialchars($v['created_at'] ?? '-') ?></td>
                <td><a href="/admin/videos/<?= (int)$v['id'] ?>" class="btn btn-xs btn-primary">Просмотр</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top: 1rem;"><a href="/admin" class="btn btn-secondary">← Назад в админ-панель</a></p>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
