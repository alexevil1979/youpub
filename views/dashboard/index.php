<?php
$title = 'Дашборд';
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Дашборд</h1>
        <p class="page-subtitle">
            Сводка по видео, расписаниям и публикациям в YouPub.
        </p>
    </div>
    <div class="page-header-actions" aria-label="Быстрые действия">
        <a href="/videos/upload" class="btn btn-primary">
            <i class="fa-solid fa-cloud-arrow-up icon-inline" aria-hidden="true"></i>
            Загрузить видео
        </a>
        <a href="/schedules/create" class="btn btn-secondary">
            <i class="fa-solid fa-calendar-plus icon-inline" aria-hidden="true"></i>
            Новое расписание
        </a>
    </div>
</div>

<!-- Основная сетка дашборда: статистика + контент -->
<div class="dashboard-layout">
    <!-- Левая колонка: ключевые метрики -->
    <section class="dashboard-panel dashboard-panel--metrics" aria-label="Ключевые метрики">
        <div class="stats-grid">
            <article class="stat-card" aria-label="Всего видео">
                <div class="stat-card-header">
                    <div class="stat-card-icon stat-card-icon--primary">
                        <i class="fa-solid fa-video" aria-hidden="true"></i>
                    </div>
                    <span class="stat-label">Всего видео</span>
                </div>
                <p class="stat-number"><?= (int)($stats['videos_total'] ?? 0) ?></p>
            </article>

            <article class="stat-card" aria-label="Ожидают публикации">
                <div class="stat-card-header">
                    <div class="stat-card-icon stat-card-icon--warning">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                    </div>
                    <span class="stat-label">Ожидают публикации</span>
                </div>
                <p class="stat-number"><?= (int)($stats['schedules_pending'] ?? 0) ?></p>
            </article>

            <article class="stat-card" aria-label="Успешных публикаций">
                <div class="stat-card-header">
                    <div class="stat-card-icon stat-card-icon--success">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    </div>
                    <span class="stat-label">Успешных публикаций</span>
                </div>
                <p class="stat-number"><?= (int)($stats['publications_success'] ?? 0) ?></p>
            </article>

            <article class="stat-card" aria-label="Ошибок публикаций">
                <div class="stat-card-header">
                    <div class="stat-card-icon stat-card-icon--danger">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <span class="stat-label">Ошибок</span>
                </div>
                <p class="stat-number"><?= (int)($stats['publications_failed'] ?? 0) ?></p>
            </article>
        </div>
    </section>

    <!-- Правая колонка: последние сущности -->
    <section class="dashboard-panel dashboard-panel--lists">
        <div class="dashboard-sections">
            <section class="dashboard-card" aria-labelledby="latest-published-heading">
                <div class="dashboard-card-header">
                    <h2 id="latest-published-heading" class="dashboard-card-title">
                        <i class="fa-solid fa-share-nodes icon-inline" aria-hidden="true"></i>
                        Последние опубликованные
                    </h2>
                    <a href="/publications" class="dashboard-card-link">Все публикации</a>
                </div>

                <?php
                $recentPublications = $recentPublications ?? [];
                $recentPublicationsVideoNames = $recentPublicationsVideoNames ?? [];
                ?>
                <?php if (empty($recentPublications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
                        </div>
                        <h3>Нет опубликованных видео</h3>
                        <p>Запланируйте публикацию или опубликуйте видео вручную.</p>
                        <a href="/schedules" class="btn btn-secondary">К расписаниям</a>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="data-table" aria-label="Последние опубликованные видео">
                            <thead>
                                <tr>
                                    <th scope="col">Файл</th>
                                    <th scope="col">Платформа</th>
                                    <th scope="col">Опубликовано</th>
                                    <th scope="col" class="text-right">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPublications as $pub):
                                    $videoId = (int)($pub['video_id'] ?? 0);
                                    $fileName = $recentPublicationsVideoNames[$videoId] ?? 'video';
                                    $pubUrl = !empty($pub['platform_url']) ? $pub['platform_url'] : null;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="cell-main">
                                                <span class="cell-title"><?= htmlspecialchars($fileName) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="platform-badge platform-<?= htmlspecialchars($pub['platform'] ?? '') ?>">
                                                <?= ucfirst($pub['platform'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $pub['published_at'] ? date('d.m.Y H:i', strtotime($pub['published_at'])) : '-' ?>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($pubUrl): ?>
                                                <a href="<?= htmlspecialchars($pubUrl) ?>"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   class="btn-action btn-action-publish"
                                                   title="Перейти к публикации"
                                                   aria-label="Перейти к публикации">
                                                    <i class="fa-solid fa-external-link-alt" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($videoId > 0): ?>
                                                <a href="/videos/<?= $videoId ?>"
                                                   class="btn-action btn-view"
                                                   title="Открыть видео"
                                                   aria-label="Открыть видео">
                                                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-card" aria-labelledby="latest-uploads-heading">
                <div class="dashboard-card-header">
                    <h2 id="latest-uploads-heading" class="dashboard-card-title">
                        <i class="fa-solid fa-cloud-arrow-up icon-inline" aria-hidden="true"></i>
                        Последние загруженные
                    </h2>
                    <a href="/videos" class="dashboard-card-link">Все видео</a>
                </div>

                <?php if (empty($recentVideos)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fa-regular fa-circle-play" aria-hidden="true"></i>
                        </div>
                        <h3>Нет загруженных видео</h3>
                        <p>Загрузите первое видео, чтобы начать планировать публикации.</p>
                        <a href="/videos/upload" class="btn btn-primary">Загрузить видео</a>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="data-table" aria-label="Последние загруженные видео">
                            <thead>
                                <tr>
                                    <th scope="col">Файл</th>
                                    <th scope="col">Размер</th>
                                    <th scope="col">Загружено</th>
                                    <th scope="col" class="text-right">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recentVideoPublications = $recentVideoPublications ?? [];
                                foreach ($recentVideos as $video):
                                    $videoId = (int)$video['id'];
                                    $pub = $recentVideoPublications[$videoId] ?? null;
                                    $pubUrl = $pub && !empty($pub['platform_url']) ? $pub['platform_url'] : null;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="cell-main">
                                                <span class="cell-title">
                                                    <?= htmlspecialchars($video['file_name'] ?? 'video') ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?= number_format(($video['file_size'] ?? 0) / 1024 / 1024, 2) ?> MB
                                        </td>
                                        <td>
                                            <?= date('d.m.Y H:i', strtotime($video['created_at'])) ?>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($pubUrl): ?>
                                                <a href="<?= htmlspecialchars($pubUrl) ?>"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   class="btn-action btn-action-publish"
                                                   title="Перейти к публикации на <?= ucfirst($pub['platform'] ?? '') ?>"
                                                   aria-label="Перейти к публикации">
                                                    <i class="fa-solid fa-external-link-alt" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="/videos/<?= $videoId ?>"
                                               class="btn-action btn-view"
                                               title="Открыть видео"
                                               aria-label="Открыть видео">
                                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-card" aria-labelledby="upcoming-schedules-heading">
                <div class="dashboard-card-header">
                    <h2 id="upcoming-schedules-heading" class="dashboard-card-title">
                        <i class="fa-solid fa-calendar-days icon-inline" aria-hidden="true"></i>
                        Ближайшие публикации
                    </h2>
                    <a href="/schedules" class="dashboard-card-link">Все расписания</a>
                </div>

                <?php if (empty($upcomingSchedules)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        </div>
                        <h3>Нет запланированных публикаций</h3>
                        <p>Создайте расписание, чтобы публиковать видео автоматически.</p>
                        <a href="/schedules/create" class="btn btn-secondary">Создать расписание</a>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="data-table" aria-label="Ближайшие публикации">
                            <thead>
                                <tr>
                                    <th scope="col">Платформа</th>
                                    <th scope="col">Дата публикации</th>
                                    <th scope="col">Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingSchedules as $schedule): ?>
                                    <tr>
                                        <td>
                                            <span class="platform-badge platform-<?= $schedule['platform'] ?>">
                                                <?= ucfirst($schedule['platform']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('d.m.Y H:i', strtotime($schedule['publish_at'])) ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $schedule['status'] ?>">
                                                <?= ucfirst($schedule['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
