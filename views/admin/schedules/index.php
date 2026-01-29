<?php
$title = 'Расписания';
$schedules = $schedules ?? [];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Расписания</h1>
        <p class="page-subtitle">Все запланированные публикации.</p>
    </div>
    <div class="page-header-actions">
        <a href="/admin" class="btn btn-secondary">← Админ-панель</a>
    </div>
</div>

<?php if (empty($schedules)): ?>
    <p>Расписаний нет.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Платформа</th>
                <th>Статус</th>
                <th>Дата публикации</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedules as $s): ?>
            <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= (int)($s['user_id'] ?? 0) ?></td>
                <td><?= htmlspecialchars($s['platform'] ?? '-') ?></td>
                <td><span class="badge badge-<?= $s['status'] ?? 'info' ?>"><?= htmlspecialchars($s['status'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($s['publish_at'] ?? '-') ?></td>
                <td><a href="/admin/schedules/<?= (int)$s['id'] ?>" class="btn btn-xs btn-primary">Просмотр</a></td>
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
