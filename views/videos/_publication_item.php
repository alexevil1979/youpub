<?php
// Формируем URL в зависимости от платформы
$url = '';
if (!empty($publication['platform_url'])) {
    $url = $publication['platform_url'];
} elseif (!empty($publication['platform_id'])) {
    switch ($publication['platform']) {
        case 'youtube':
            $url = 'https://youtube.com/shorts/' . $publication['platform_id'];
            break;
        case 'telegram':
            $url = 'https://t.me/' . $publication['platform_id'];
            break;
        default:
            $url = '#';
    }
}
?>
<div class="publication-item" data-platform="<?= htmlspecialchars($publication['platform']) ?>">
    <div class="publication-platform">
        <strong><?= ucfirst($publication['platform']) ?></strong>
        <?php if (!empty($publication['published_at'])): ?>
            <span class="publication-date"><?= date('d.m.Y H:i', strtotime($publication['published_at'])) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($url && $url !== '#'): ?>
        <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="btn btn-primary btn-sm">
            Открыть на <?= ucfirst($publication['platform']) ?>
        </a>
    <?php endif; ?>
</div>
