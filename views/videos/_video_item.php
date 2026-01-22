<div class="catalog-item">
    <span class="item-icon">🎬</span>
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
                        <span class="group-tag">📁 <?= htmlspecialchars($vg['group_name'] ?? 'Группа') ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="item-actions">
        <a href="/videos/<?= $video['id'] ?>" class="btn-action" title="Просмотр">👁</a>
        <a href="/schedules/create?video_id=<?= $video['id'] ?>" class="btn-action" title="Запланировать">📅</a>
        <button type="button" class="btn-action" onclick="showAddToGroupModal(<?= $video['id'] ?>)" title="В группу">📁</button>
    </div>
</div>
