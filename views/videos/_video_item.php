<div class="catalog-item">
    <span class="item-icon"><?= \App\Helpers\IconHelper::render('video', 20) ?></span>
    <div class="item-info">
        <div class="item-title"><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></div>
        <div class="item-meta">
            <span><?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB</span>
            <span>•</span>
            <span><?= date('d.m.Y H:i', strtotime($video['created_at'])) ?></span>
            <span>•</span>
            <span class="status-badge status-<?= $video['status'] ?>"><?= ucfirst($video['status']) ?></span>
            <?php if (isset($videoGroups[$video['id']]) && !empty($videoGroups[$video['id']])): ?>
                <span>•</span>
                <span class="groups-badge">
                    <?php foreach ($videoGroups[$video['id']] as $vg): ?>
                        <span class="group-tag"><?= \App\Helpers\IconHelper::render('folder', 16, 'icon-inline') ?> <?= htmlspecialchars($vg['group_name'] ?? 'Группа') ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="item-actions">
        <a href="/videos/<?= $video['id'] ?>" class="btn-action" title="Просмотр"><?= \App\Helpers\IconHelper::render('view', 20) ?></a>
        <?php if (isset($videoPublications[$video['id']])): 
            $pub = $videoPublications[$video['id']];
            $pubUrl = $pub['platform_url'] ?? '';
            if (!$pubUrl && $pub['platform_id']) {
                switch ($pub['platform']) {
                    case 'youtube':
                        $pubUrl = 'https://youtube.com/watch?v=' . $pub['platform_id'];
                        break;
                    case 'telegram':
                        $pubUrl = 'https://t.me/' . $pub['platform_id'];
                        break;
                    case 'tiktok':
                        $pubUrl = 'https://www.tiktok.com/@' . $pub['platform_id'];
                        break;
                    case 'instagram':
                        $pubUrl = 'https://www.instagram.com/p/' . $pub['platform_id'];
                        break;
                    case 'pinterest':
                        $pubUrl = 'https://www.pinterest.com/pin/' . $pub['platform_id'];
                        break;
                }
            }
            if ($pubUrl):
        ?>
            <a href="<?= htmlspecialchars($pubUrl) ?>" target="_blank" class="btn-action btn-action-publish" title="Перейти к публикации на <?= ucfirst($pub['platform']) ?>"><?= \App\Helpers\IconHelper::render('publish', 20) ?></a>
        <?php endif; endif; ?>
        <a href="/schedules/create?video_id=<?= $video['id'] ?>" class="btn-action" title="Запланировать"><?= \App\Helpers\IconHelper::render('calendar', 20) ?></a>
        <button type="button" class="btn-action" onclick="showAddToGroupModal(<?= $video['id'] ?>)" title="В группу"><?= \App\Helpers\IconHelper::render('folder', 20) ?></button>
        <button type="button" class="btn-action <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'btn-pause' : 'btn-play' ?>" 
                onclick="toggleVideoStatus(<?= $video['id'] ?>)" 
                title="<?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? 'Выключить' : 'Включить' ?>">
            <?= ($video['status'] === 'active' || $video['status'] === 'uploaded' || $video['status'] === 'ready') ? \App\Helpers\IconHelper::render('pause', 20) : \App\Helpers\IconHelper::render('play', 20) ?>
        </button>
        <button type="button" class="btn-action btn-delete" onclick="deleteVideo(<?= $video['id'] ?>)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 20) ?></button>
    </div>
</div>
