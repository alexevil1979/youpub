<?php
if (!$log) {
    http_response_code(404);
    echo 'Запись лога не найдена.';
    return;
}
$title = 'Лог #' . (int)$log['id'];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Лог #<?= (int)$log['id'] ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($log['created_at'] ?? '') ?> — <?= htmlspecialchars($log['type'] ?? '') ?></p>
    </div>
    <div class="page-header-actions">
        <a href="/admin/logs" class="btn btn-secondary">← К списку логов</a>
    </div>
</div>

<table class="profile-table data-table" style="max-width: 800px;">
    <tr>
        <th>ID</th>
        <td><?= (int)$log['id'] ?></td>
    </tr>
    <tr>
        <th>Дата</th>
        <td><?= htmlspecialchars($log['created_at'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Тип</th>
        <td><span class="badge badge-<?= $log['type'] ?? 'info' ?>"><?= htmlspecialchars($log['type'] ?? '') ?></span></td>
    </tr>
    <tr>
        <th>Модуль</th>
        <td><?= htmlspecialchars($log['module'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>User ID</th>
        <td><?= $log['user_id'] !== null ? (int)$log['user_id'] : '-' ?></td>
    </tr>
    <tr>
        <th>IP</th>
        <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Сообщение</th>
        <td><pre style="white-space: pre-wrap; word-break: break-word; margin: 0;"><?= htmlspecialchars($log['message'] ?? '') ?></pre></td>
    </tr>
    <?php if (!empty($log['context'])): ?>
    <tr>
        <th>Контекст</th>
        <td><pre style="white-space: pre-wrap; word-break: break-word; margin: 0; font-size: 0.9rem;"><?= htmlspecialchars(is_string($log['context']) ? $log['context'] : json_encode($log['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre></td>
    </tr>
    <?php endif; ?>
</table>

<p style="margin-top: 1.5rem;"><a href="/admin/logs" class="btn btn-secondary">← Назад к списку логов</a></p>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
