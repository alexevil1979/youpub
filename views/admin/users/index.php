<?php
$title = 'Пользователи';
$users = $users ?? [];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Пользователи</h1>
        <p class="page-subtitle">Список зарегистрированных пользователей.</p>
    </div>
    <div class="page-header-actions">
        <a href="/admin" class="btn btn-secondary">← Админ-панель</a>
    </div>
</div>

<?php if (empty($users)): ?>
    <p>Пользователей нет.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Имя</th>
                <th>Роль</th>
                <th>Статус</th>
                <th>Лимит загрузок</th>
                <th>Дата регистрации</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['name'] ?? '-') ?></td>
                <td><span class="badge badge-<?= ($u['role'] ?? '') === 'admin' ? 'warning' : 'info' ?>"><?= htmlspecialchars($u['role'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($u['status'] ?? '-') ?></td>
                <td><?= (int)($u['upload_limit'] ?? 0) ?></td>
                <td><?= htmlspecialchars($u['created_at'] ?? '-') ?></td>
                <td><a href="/admin/users/<?= (int)$u['id'] ?>" class="btn btn-xs btn-primary">Просмотр</a></td>
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
