<?php
$title = 'Логи';
$logs = $logs ?? [];
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Логи системы</h1>
        <p class="page-subtitle">Просмотр записей логов приложения.</p>
    </div>
</div>

<form method="GET" action="/admin/logs" class="filters-form" style="margin-bottom: 1.5rem;">
    <div class="form-row" style="flex-wrap: wrap; gap: 1rem;">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="type">Тип</label>
            <select name="type" id="type">
                <option value="">Все</option>
                <option value="info" <?= (($type ?? '') === 'info') ? 'selected' : '' ?>>info</option>
                <option value="warning" <?= (($type ?? '') === 'warning') ? 'selected' : '' ?>>warning</option>
                <option value="error" <?= (($type ?? '') === 'error') ? 'selected' : '' ?>>error</option>
                <option value="debug" <?= (($type ?? '') === 'debug') ? 'selected' : '' ?>>debug</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label for="module">Модуль</label>
            <input type="text" name="module" id="module" value="<?= htmlspecialchars($module ?? '') ?>" placeholder="Модуль">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label for="limit">Лимит</label>
            <input type="number" name="limit" id="limit" value="<?= (int)($limit ?? 100) ?>" min="10" max="500" style="width: 80px;">
        </div>
        <div class="form-group" style="margin-bottom: 0; align-self: flex-end;">
            <button type="submit" class="btn btn-primary">Применить</button>
        </div>
    </div>
</form>

<?php if (empty($logs)): ?>
    <p>Записей логов нет.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Тип</th>
                <th>Модуль</th>
                <th>Сообщение</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= (int)$log['id'] ?></td>
                <td><?= htmlspecialchars($log['created_at'] ?? '') ?></td>
                <td><span class="badge badge-<?= $log['type'] ?? 'info' ?>"><?= htmlspecialchars($log['type'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($log['module'] ?? '-') ?></td>
                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars(mb_substr($log['message'] ?? '', 0, 80)) ?><?= mb_strlen($log['message'] ?? '') > 80 ? '…' : '' ?></td>
                <td><a href="/admin/logs/<?= (int)$log['id'] ?>" class="btn btn-xs btn-secondary">Просмотр</a></td>
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
