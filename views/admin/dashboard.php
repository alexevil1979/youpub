<?php
$title = 'Админ-панель';
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Админ-панель</h1>
        <p class="page-subtitle">
            Обзор ключевых метрик сервиса и последних публикаций.
        </p>
    </div>
    <div class="page-header-actions">
        <a href="/admin/settings" class="btn btn-secondary">
            <i class="fa-solid fa-gear icon-inline" aria-hidden="true"></i>
            Настройки системы
        </a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Всего пользователей</h3>
        <p class="stat-number"><?= $stats['users_total'] ?></p>
    </div>
    <div class="stat-card">
        <h3>Всего видео</h3>
        <p class="stat-number"><?= $stats['videos_total'] ?></p>
    </div>
    <div class="stat-card">
        <h3>Ожидают публикации</h3>
        <p class="stat-number"><?= $stats['schedules_pending'] ?></p>
    </div>
    <div class="stat-card">
        <h3>В обработке</h3>
        <p class="stat-number"><?= $stats['schedules_processing'] ?></p>
    </div>
    <div class="stat-card">
        <h3>Успешных публикаций</h3>
        <p class="stat-number"><?= $stats['publications_success'] ?></p>
    </div>
    <div class="stat-card">
        <h3>Ошибок</h3>
        <p class="stat-number"><?= $stats['publications_failed'] ?></p>
    </div>
</div>

<section>
    <h2>Последние публикации</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Пользователь</th>
                <th>Платформа</th>
                <th>Статус</th>
                <th>Дата</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentPublications as $pub): ?>
            <tr>
                <td>ID: <?= $pub['user_id'] ?></td>
                <td><?= ucfirst($pub['platform']) ?></td>
                <td><?= ucfirst($pub['status']) ?></td>
                <td><?= $pub['published_at'] ? date('d.m.Y H:i', strtotime($pub['published_at'])) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
