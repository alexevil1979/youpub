<?php
$title = 'Админ-панель';
ob_start();
?>

<h1>Админ-панель</h1>

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
    <table>
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
