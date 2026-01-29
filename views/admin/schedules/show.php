<?php
if (!$schedule) {
    http_response_code(404);
    echo 'Расписание не найдено.';
    return;
}
$title = 'Расписание #' . (int)$schedule['id'];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Расписание #<?= (int)$schedule['id'] ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($schedule['platform'] ?? '') ?> — <?= htmlspecialchars($schedule['status'] ?? '') ?></p>
    </div>
    <div class="page-header-actions">
        <a href="/admin/schedules" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<table class="profile-table data-table" style="max-width: 600px;">
    <tr>
        <th>ID</th>
        <td><?= (int)$schedule['id'] ?></td>
    </tr>
    <tr>
        <th>User ID</th>
        <td><?= (int)($schedule['user_id'] ?? 0) ?></td>
    </tr>
    <tr>
        <th>Video ID</th>
        <td><?= !empty($schedule['video_id']) ? (int)$schedule['video_id'] : '-' ?></td>
    </tr>
    <tr>
        <th>Content Group ID</th>
        <td><?= !empty($schedule['content_group_id']) ? (int)$schedule['content_group_id'] : '-' ?></td>
    </tr>
    <tr>
        <th>Платформа</th>
        <td><?= htmlspecialchars($schedule['platform'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Статус</th>
        <td><span class="badge badge-<?= $schedule['status'] ?? 'info' ?>"><?= htmlspecialchars($schedule['status'] ?? '') ?></span></td>
    </tr>
    <tr>
        <th>Дата публикации</th>
        <td><?= htmlspecialchars($schedule['publish_at'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Создано</th>
        <td><?= htmlspecialchars($schedule['created_at'] ?? '-') ?></td>
    </tr>
</table>

<p style="margin-top: 1.5rem;"><a href="/admin/schedules" class="btn btn-secondary">← Назад к списку расписаний</a></p>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
