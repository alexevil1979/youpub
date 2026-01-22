<?php
$title = 'Дашборд';
ob_start();
?>

<h1>Дашборд</h1>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Всего видео</h3>
        <p class="stat-number"><?= $stats['videos_total'] ?></p>
    </div>
    <div class="stat-card">
        <h3>Ожидают публикации</h3>
        <p class="stat-number"><?= $stats['schedules_pending'] ?></p>
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

<div class="dashboard-sections">
    <section>
        <h2>Последние видео</h2>
        <?php if (empty($recentVideos)): ?>
            <p>Нет загруженных видео</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Размер</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentVideos as $video): ?>
                    <tr>
                        <td><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></td>
                        <td><?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB</td>
                        <td><?= date('d.m.Y H:i', strtotime($video['created_at'])) ?></td>
                        <td><a href="/videos/<?= $video['id'] ?>">Просмотр</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section>
        <h2>Ближайшие публикации</h2>
        <?php if (empty($upcomingSchedules)): ?>
            <p>Нет запланированных публикаций</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Платформа</th>
                        <th>Дата публикации</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingSchedules as $schedule): ?>
                    <tr>
                        <td><?= ucfirst($schedule['platform']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($schedule['publish_at'])) ?></td>
                        <td><?= ucfirst($schedule['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
