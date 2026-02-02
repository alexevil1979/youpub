<?php
$title = 'Статистика';
ob_start();

use App\Repositories\PublicationRepository;
use App\Repositories\StatisticsRepository;

$userId = $_SESSION['user_id'];
$publicationRepo = new PublicationRepository();
$statsRepo = new StatisticsRepository();

$publications = $publicationRepo->findByUserIdWithVideoInfo($userId, ['published_at' => 'DESC']);

// Подсчет общей статистики
$totalViews = 0;
$totalLikes = 0;
$totalComments = 0;
$totalShares = 0;

foreach ($publications as $publication) {
    $stats = $statsRepo->findByPublicationId($publication['id'], ['collected_at' => 'DESC']);
    if (!empty($stats)) {
        $latest = $stats[0];
        $totalViews += $latest['views'];
        $totalLikes += $latest['likes'];
        $totalComments += $latest['comments'];
        $totalShares += $latest['shares'];
    }
}
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Статистика</h1>
        <p class="page-subtitle">
            Сводные показатели просмотров, вовлечённости и подробная статистика по публикациям.
        </p>
    </div>
</div>

<?php if ($totalViews === 0 && $totalLikes === 0 && $totalComments === 0 && !empty($publications)): ?>
    <div class="alert alert-warning" style="margin-bottom: 1rem;">
        <strong>Данные пока нулевые.</strong> Статистика по YouTube подтягивается воркером раз в час. Проверьте:
        <ul style="margin: 0.5rem 0 0 1rem;">
            <li>В crontab добавлена строка: <code>0 * * * * /ssd/www/youpub/cron/stats.sh</code></li>
            <li>Логи воркера: <code>storage/logs/workers/stats_<?= date('Y-m-d') ?>.log</code></li>
            <li>У публикаций на YouTube заполнен <code>platform_id</code> и подключена интеграция YouTube.</li>
        </ul>
    </div>
<?php endif; ?>

<div class="stats-overview">
    <div class="stat-card">
        <h3>Всего просмотров</h3>
        <p class="stat-number"><?= number_format($totalViews) ?></p>
    </div>
    <div class="stat-card">
        <h3>Всего лайков</h3>
        <p class="stat-number"><?= number_format($totalLikes) ?></p>
    </div>
    <div class="stat-card">
        <h3>Всего комментариев</h3>
        <p class="stat-number"><?= number_format($totalComments) ?></p>
    </div>
    <div class="stat-card">
        <h3>Всего репостов</h3>
        <p class="stat-number"><?= number_format($totalShares) ?></p>
    </div>
    <?php
    $engagementRate = $totalViews > 0 ? round(100 * ($totalLikes + $totalComments) / $totalViews, 2) : 0;
    ?>
    <div class="stat-card">
        <h3>Вовлечённость (лайки+комменты / просмотры)</h3>
        <p class="stat-number"><?= $engagementRate ?>%</p>
        <p class="stat-hint">Показатель для анализа качества публикаций</p>
    </div>
</div>

<div class="stats-section">
    <h2>Статистика по публикациям</h2>
    
    <?php if (empty($publications)): ?>
        <p>Нет опубликованных видео</p>
    <?php else: ?>
        <p class="stats-source-hint">Данные по YouTube подтягиваются с YouTube Data API (cron каждый час). Остальные платформы — по мере реализации.</p>
        <table>
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Платформа</th>
                    <th>Дата публикации</th>
                    <th>Просмотры</th>
                    <th>Лайки</th>
                    <th>Комментарии</th>
                    <th>Репосты</th>
                    <th>Вовлечённость</th>
                    <th>Дата сбора данных</th>
                    <th>Ссылка</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($publications as $publication): ?>
                    <?php 
                    $stats = $statsRepo->findByPublicationId($publication['id'], ['collected_at' => 'DESC']);
                    $latestStats = !empty($stats) ? $stats[0] : null;
                    $views = $latestStats ? (int)$latestStats['views'] : 0;
                    $likes = $latestStats ? (int)$latestStats['likes'] : 0;
                    $comments = $latestStats ? (int)$latestStats['comments'] : 0;
                    $eng = $views > 0 ? round(100 * ($likes + $comments) / $views, 2) : 0;
                    $videoTitle = !empty($publication['video_title']) ? $publication['video_title'] : ($publication['video_file_name'] ?? '—');
                    $videoDesc = $publication['video_description'] ?? '';
                    $videoDescShort = mb_strlen($videoDesc) > 120 ? mb_substr($videoDesc, 0, 120) . '…' : $videoDesc;
                    ?>
                    <tr>
                        <td style="max-width: 220px;" title="<?= htmlspecialchars($videoTitle, ENT_QUOTES) ?>"><?= htmlspecialchars($videoTitle) ?></td>
                        <td style="max-width: 280px;" title="<?= htmlspecialchars($videoDesc, ENT_QUOTES) ?>"><?= htmlspecialchars($videoDescShort ?: '—') ?></td>
                        <td><?= ucfirst($publication['platform']) ?></td>
                        <td><?= $publication['published_at'] ? date('d.m.Y H:i', strtotime($publication['published_at'])) : '-' ?></td>
                        <td><?= number_format($views) ?></td>
                        <td><?= number_format($likes) ?></td>
                        <td><?= number_format($comments) ?></td>
                        <td><?= $latestStats ? number_format((int)$latestStats['shares']) : '0' ?></td>
                        <td><?= $eng ?>%</td>
                        <td><?= $latestStats && !empty($latestStats['collected_at']) ? date('d.m.Y H:i', strtotime($latestStats['collected_at'])) : '—' ?></td>
                        <td>
                            <?php if ($publication['platform_url']): ?>
                                <a href="<?= htmlspecialchars($publication['platform_url']) ?>" target="_blank">Открыть</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="export-section">
            <h3>Экспорт статистики</h3>
            <a href="/api/stats/export?format=json" class="btn btn-primary">Экспорт JSON</a>
            <a href="/api/stats/export?format=csv" class="btn btn-primary">Экспорт CSV</a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
