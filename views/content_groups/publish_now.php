<?php
$title = 'Публикация сейчас';
ob_start();
?>

<h1>Опубликовать сейчас</h1>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 1rem;">
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 1rem;">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<div class="info-card" style="margin-bottom: 1.5rem;">
    <h3>Информация о файле</h3>
    <div class="group-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1rem;">
        <div class="stat-item">
            <div class="stat-label">Группа:</div>
            <div class="stat-value"><?= htmlspecialchars($group['name']) ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Файл:</div>
            <div class="stat-value"><?= htmlspecialchars($video['file_name'] ?? $video['title'] ?? 'Без названия') ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Статус:</div>
            <div class="stat-value"><?= htmlspecialchars($file['status']) ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Платформа:</div>
            <div class="stat-value"><?= htmlspecialchars(ucfirst($platform)) ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Шаблон:</div>
            <div class="stat-value"><?= htmlspecialchars($templateName ?: 'Без шаблона') ?></div>
        </div>
    </div>
</div>

<div class="info-card" style="margin-bottom: 1.5rem;">
    <h3>Как будет опубликовано</h3>
    <div style="margin-top: 1rem;">
        <div style="margin-bottom: 0.75rem;">
            <strong>Название:</strong>
            <div style="color: #2c3e50; word-break: break-word;">
                <?= htmlspecialchars($preview['title'] ?? 'Без названия') ?>
            </div>
        </div>
        <?php if (!empty($preview['description'])): ?>
            <div style="margin-bottom: 0.75rem;">
                <strong>Описание:</strong>
                <div style="color: #666; white-space: pre-wrap;">
                    <?= htmlspecialchars($preview['description']) ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($preview['tags'])): ?>
            <div>
                <strong>Теги:</strong>
                <div style="color: #666; word-break: break-word;">
                    <?= htmlspecialchars($preview['tags']) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="form-actions">
    <a href="/content-groups/<?= (int)$group['id'] ?>" class="btn btn-secondary">Назад к группе</a>
    <form method="POST" action="/content-groups/<?= (int)$group['id'] ?>/files/<?= (int)$file['id'] ?>/publish-now" style="display: inline;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <button type="submit" class="btn btn-success" <?= $canPublish ? '' : 'disabled' ?> onclick="return confirm('Опубликовать видео сейчас?');">
            Опубликовать сейчас
        </button>
    </form>
    <?php if (!$canPublish): ?>
        <span style="margin-left: 0.75rem; color: #e74c3c; font-size: 0.9rem;">Этот файл нельзя опубликовать сейчас</span>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
