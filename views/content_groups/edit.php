<?php
$title = 'Редактировать группу: ' . htmlspecialchars($group['name']);
ob_start();
?>

<h1>Редактировать группу</h1>

<form method="POST" action="/content-groups/<?= $group['id'] ?>/edit" class="group-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="name">Название группы *</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($group['name']) ?>" required placeholder="Например: Котики, Мемы, Релакс">
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3" placeholder="Описание группы (опционально)"><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="template_id">Шаблон оформления (опционально)</label>
        <select id="template_id" name="template_id">
            <option value="">Без шаблона</option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= $template['id'] ?>" <?= ($group['template_id'] == $template['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($template['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Выберите шаблон для автоматического оформления публикаций из этой группы</small>
        <div style="margin-top: 0.5rem;">
            <a href="/content-groups/templates/create-shorts" target="_blank" class="btn btn-sm btn-secondary">Создать новый шаблон</a>
        </div>
    </div>

    <div class="form-group">
        <label for="status">Статус</label>
        <select id="status" name="status">
            <option value="active" <?= ($group['status'] === 'active') ? 'selected' : '' ?>>Активна</option>
            <option value="paused" <?= ($group['status'] === 'paused') ? 'selected' : '' ?>>На паузе</option>
            <option value="archived" <?= ($group['status'] === 'archived') ? 'selected' : '' ?>>Архивная</option>
        </select>
        <small>Группы на паузе не будут публиковать видео</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="/content-groups/<?= $group['id'] ?>" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
    <h3 style="margin-top: 0; margin-bottom: 1rem;">📹 Добавить видео в группу</h3>
    
    <?php if (empty($availableVideos)): ?>
        <p style="color: #6c757d; margin-bottom: 1rem;">Нет доступных видео для добавления. Все ваши видео уже в этой группе или у вас нет загруженных видео.</p>
        <a href="/videos/upload" class="btn btn-primary">Загрузить видео</a>
    <?php else: ?>
        <div style="margin-bottom: 1rem;">
            <label for="video-select" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Выберите видео для добавления:</label>
            <select id="video-select" multiple style="width: 100%; min-height: 200px; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem;">
                <?php foreach ($availableVideos as $video): ?>
                    <option value="<?= $video['id'] ?>">
                        <?= htmlspecialchars($video['title'] ?: $video['file_name']) ?>
                        <?php if ($video['file_size']): ?>
                            (<?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="display: block; margin-top: 0.5rem; color: #6c757d;">Удерживайте Ctrl (Cmd на Mac) для выбора нескольких видео</small>
        </div>
        <button type="button" id="add-videos-btn" class="btn btn-success">
            <?= \App\Helpers\IconHelper::render('add', 16, 'icon-inline') ?> Добавить выбранные видео
        </button>
        <div id="add-videos-status" style="margin-top: 1rem; display: none;"></div>
    <?php endif; ?>
</div>

<div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
    <h3 style="margin-top: 0;">💡 О шаблонах</h3>
    <p>Шаблон оформления позволяет автоматически генерировать заголовки, описания и теги для публикаций из этой группы.</p>
    <p>Если шаблон не выбран, будут использоваться данные из самого видео (название, описание, теги).</p>
    <p><a href="/content-groups/templates">Управление шаблонами</a> | <a href="/content-groups/templates/create-shorts">Создать новый шаблон</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addVideosBtn = document.getElementById('add-videos-btn');
    const videoSelect = document.getElementById('video-select');
    const statusDiv = document.getElementById('add-videos-status');
    
    if (addVideosBtn && videoSelect) {
        addVideosBtn.addEventListener('click', function() {
            const selectedOptions = Array.from(videoSelect.selectedOptions);
            const videoIds = selectedOptions.map(option => parseInt(option.value));
            
            if (videoIds.length === 0) {
                alert('Выберите хотя бы одно видео для добавления');
                return;
            }
            
            if (!confirm('Добавить ' + videoIds.length + ' видео в группу?')) {
                return;
            }
            
            addVideosBtn.disabled = true;
            addVideosBtn.style.opacity = '0.6';
            statusDiv.style.display = 'block';
            statusDiv.className = 'alert';
            statusDiv.textContent = 'Добавление видео...';
            
            const csrfToken = <?= json_encode($csrfToken) ?>;
            
            // Отправляем как JSON для правильной обработки массива
            fetch('/content-groups/<?= $group['id'] ?>/add-videos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    video_ids: videoIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.className = 'alert alert-success';
                    statusDiv.textContent = 'Видео успешно добавлены в группу!';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    statusDiv.className = 'alert alert-error';
                    statusDiv.textContent = 'Ошибка: ' + (data.message || 'Не удалось добавить видео');
                    addVideosBtn.disabled = false;
                    addVideosBtn.style.opacity = '1';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusDiv.className = 'alert alert-error';
                statusDiv.textContent = 'Произошла ошибка при добавлении видео';
                addVideosBtn.disabled = false;
                addVideosBtn.style.opacity = '1';
            });
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
