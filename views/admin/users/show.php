<?php
if (!$user) {
    http_response_code(404);
    echo 'Пользователь не найден.';
    return;
}
$title = 'Пользователь #' . (int)$user['id'];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Пользователь #<?= (int)$user['id'] ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($user['email'] ?? '') ?></p>
    </div>
    <div class="page-header-actions">
        <a href="/admin/users" class="btn btn-secondary">← К списку</a>
    </div>
</div>

<table class="profile-table data-table" style="max-width: 600px;">
    <tr>
        <th>ID</th>
        <td><?= (int)$user['id'] ?></td>
    </tr>
    <tr>
        <th>Email</th>
        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Имя</th>
        <td><?= htmlspecialchars($user['name'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Роль</th>
        <td><span class="badge badge-<?= ($user['role'] ?? '') === 'admin' ? 'warning' : 'info' ?>"><?= htmlspecialchars($user['role'] ?? '') ?></span></td>
    </tr>
    <tr>
        <th>Статус</th>
        <td><?= htmlspecialchars($user['status'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Лимит загрузок</th>
        <td><?= (int)($user['upload_limit'] ?? 0) ?></td>
    </tr>
    <tr>
        <th>Лимит публикаций</th>
        <td><?= (int)($user['publish_limit'] ?? 0) ?></td>
    </tr>
    <tr>
        <th>Создан</th>
        <td><?= htmlspecialchars($user['created_at'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Обновлён</th>
        <td><?= htmlspecialchars($user['updated_at'] ?? '-') ?></td>
    </tr>
</table>

<p style="margin-top: 1.5rem;"><a href="/admin/users" class="btn btn-secondary">← Назад к списку пользователей</a></p>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
