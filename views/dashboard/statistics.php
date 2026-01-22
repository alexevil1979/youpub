<?php
$title = 'Статистика';
ob_start();

use App\Repositories\PublicationRepository;
use App\Repositories\StatisticsRepository;

$userId = $_SESSION['user_id'];
$publicationRepo = new PublicationRepository();
$statsRepo = new StatisticsRepository();

$publications = $publicationRepo->findByUserId($userId, ['published_at' => 'DESC']);

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

<h1>Статистика</h1>

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
</div>

<div class="stats-section">
    <h2>Статистика по публикациям</h2>
    
    <?php if (empty($publications)): ?>
        <p>Нет опубликованных видео</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Платформа</th>
                    <th>Дата публикации</th>
                    <th>Просмотры</th>
                    <th>Лайки</th>
                    <th>Комментарии</th>
                    <th>Репосты</th>
                    <th>Ссылка</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($publications as $publication): ?>
                    <?php 
                    $stats = $statsRepo->findByPublicationId($publication['id'], ['collected_at' => 'DESC']);
                    $latestStats = !empty($stats) ? $stats[0] : null;
                    ?>
                    <tr>
                        <td><?= ucfirst($publication['platform']) ?></td>
                        <td><?= $publication['published_at'] ? date('d.m.Y H:i', strtotime($publication['published_at'])) : '-' ?></td>
                        <td><?= $latestStats ? number_format($latestStats['views']) : '0' ?></td>
                        <td><?= $latestStats ? number_format($latestStats['likes']) : '0' ?></td>
                        <td><?= $latestStats ? number_format($latestStats['comments']) : '0' ?></td>
                        <td><?= $latestStats ? number_format($latestStats['shares']) : '0' ?></td>
                        <td>
                            <?php if ($publication['platform_url']): ?>
                                <a href="<?= htmlspecialchars($publication['platform_url']) ?>" target="_blank">Открыть</a>
                            <?php else: ?>
                                -
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
