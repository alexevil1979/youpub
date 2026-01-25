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
            <?php $isYoutube = in_array($platform, ['youtube', 'both'], true); ?>
            <strong><?= $isYoutube ? 'Название (YouTube)' : 'Название' ?>:</strong>
            <div id="publish-preview-title" style="color: #2c3e50; word-break: break-word;">
                <?= htmlspecialchars($preview['title'] ?? 'Без названия') ?>
            </div>
        </div>
        <div style="margin-bottom: 0.75rem;">
            <strong><?= $isYoutube ? 'Описание (YouTube)' : 'Описание' ?>:</strong>
            <div id="publish-preview-description" style="color: #666; white-space: pre-wrap;">
                <?= htmlspecialchars($preview['description'] ?? '—') ?>
            </div>
        </div>
        <div>
            <strong>Теги (YouTube):</strong>
            <div id="publish-preview-tags" style="color: #666; word-break: break-word;">
                <?= htmlspecialchars($preview['tags'] ?? '—') ?>
            </div>
        </div>
    </div>
    <div style="margin-top: 1rem;">
        <button type="button"
                class="btn btn-sm btn-secondary"
                id="regenerate-preview-btn"
                title="Перегенерировать оформление"
                aria-label="Перегенерировать оформление">
            <?= \App\Helpers\IconHelper::render('shuffle', 16, 'icon-inline') ?>
        </button>
    </div>
</div>

<div class="form-actions">
    <a href="/content-groups/<?= (int)$group['id'] ?>" class="btn btn-secondary">Назад к группе</a>
    <form method="POST" action="/content-groups/<?= (int)$group['id'] ?>/files/<?= (int)$file['id'] ?>/publish-now" style="display: inline;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <button type="submit"
                class="btn btn-success"
                <?= $canPublish ? '' : 'disabled' ?>
                title="Опубликовать сейчас"
                aria-label="Опубликовать сейчас"
                onclick="return confirm('Опубликовать видео сейчас?');">
            <?= \App\Helpers\IconHelper::render('publish', 16, 'icon-inline') ?>
        </button>
    </form>
    <?php if (!$canPublish): ?>
        <span style="margin-left: 0.75rem; color: #e74c3c; font-size: 0.9rem;">Этот файл нельзя опубликовать сейчас</span>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const regenerateBtn = document.getElementById('regenerate-preview-btn');
    if (!regenerateBtn) {
        return;
    }

    const csrfToken = <?= json_encode($csrfToken) ?>;
    const previewTitle = document.getElementById('publish-preview-title');
    const previewDescription = document.getElementById('publish-preview-description');
    const previewTags = document.getElementById('publish-preview-tags');

    regenerateBtn.addEventListener('click', () => {
        regenerateBtn.disabled = true;
        fetch('/content-groups/<?= (int)$group['id'] ?>/files/<?= (int)$file['id'] ?>/publish-now/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({})
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Ошибка сервера (HTTP ' + response.status + ')');
                });
            }
            return response.json();
        })
        .then(data => {
            const preview = data.data && data.data.preview ? data.data.preview : {};
            if (previewTitle) {
                previewTitle.textContent = preview.title || 'Без названия';
            }
            if (previewDescription) {
                previewDescription.textContent = preview.description || '—';
            }
            if (previewTags) {
                previewTags.textContent = preview.tags || '—';
            }
        })
        .catch(error => {
            console.error('Preview regeneration error:', error);
            alert('Не удалось перегенерировать оформление: ' + error.message);
        })
        .finally(() => {
            regenerateBtn.disabled = false;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
