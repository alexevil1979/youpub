<?php
$title = 'Создать группу контента';
ob_start();
?>

<h1>Создать группу контента</h1>

<form method="POST" action="/content-groups/create" class="group-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="name">Название группы *</label>
        <input type="text" id="name" name="name" required placeholder="Например: Котики, Мемы, Релакс">
    </div>

    <div class="form-group">
        <label for="description">Описание</label>
        <textarea id="description" name="description" rows="3" placeholder="Описание группы (опционально)"></textarea>
    </div>

    <div class="form-group">
        <label for="template_id">Шаблон оформления (опционально)</label>
        <select id="template_id" name="template_id">
            <option value="">Без шаблона</option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <small>Шаблон позволяет автоматически генерировать заголовки, описания и теги для публикаций. Можно выбрать позже при редактировании группы.</small>
        <div style="margin-top: 0.5rem;">
            <a href="/content-groups/templates/create-shorts" target="_blank" class="btn btn-sm btn-secondary">Создать новый шаблон</a>
            <?php if (!empty($templates)): ?>
                <a href="/content-groups/templates" target="_blank" class="btn btn-sm btn-info">Управление шаблонами</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group">
        <label>Каналы публикации</label>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 0.5rem;">
            <label style="display: flex; align-items: center; padding: 0.75rem; border: 2px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s;" 
                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                <input type="checkbox" name="platforms[]" value="youtube" style="margin-right: 0.5rem; cursor: pointer;">
                <div style="flex: 1;">
                    <div style="font-weight: 500; display: flex; align-items: center;">
                        <?= \App\Helpers\IconHelper::render('youtube', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">YouTube</span>
                    </div>
                    <small style="display: block; color: #6c757d; margin-top: 0.25rem;">
                        <?= $youtubeAccount ? '✓ ' . htmlspecialchars($youtubeAccount['channel_name'] ?? $youtubeAccount['account_name'] ?? 'Подключен') : 'Не подключен' ?>
                    </small>
                </div>
            </label>
            
            <label style="display: flex; align-items: center; padding: 0.75rem; border: 2px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s;" 
                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                <input type="checkbox" name="platforms[]" value="telegram" style="margin-right: 0.5rem; cursor: pointer;">
                <div style="flex: 1;">
                    <div style="font-weight: 500; display: flex; align-items: center;">
                        <?= \App\Helpers\IconHelper::render('telegram', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">Telegram</span>
                    </div>
                    <small style="display: block; color: #6c757d; margin-top: 0.25rem;">
                        <?= $telegramAccount ? '✓ ' . htmlspecialchars($telegramAccount['channel_username'] ? '@' . $telegramAccount['channel_username'] : ($telegramAccount['channel_name'] ?? 'Подключен')) : 'Не подключен' ?>
                    </small>
                </div>
            </label>
            
            <label style="display: flex; align-items: center; padding: 0.75rem; border: 2px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s;" 
                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                <input type="checkbox" name="platforms[]" value="tiktok" style="margin-right: 0.5rem; cursor: pointer;">
                <div style="flex: 1;">
                    <div style="font-weight: 500; display: flex; align-items: center;">
                        <?= \App\Helpers\IconHelper::render('tiktok', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">TikTok</span>
                    </div>
                    <small style="display: block; color: #6c757d; margin-top: 0.25rem;">
                        <?= $tiktokAccount ? '✓ ' . htmlspecialchars($tiktokAccount['username'] ?? 'Подключен') : 'Не подключен' ?>
                    </small>
                </div>
            </label>
            
            <label style="display: flex; align-items: center; padding: 0.75rem; border: 2px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s;" 
                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                <input type="checkbox" name="platforms[]" value="instagram" style="margin-right: 0.5rem; cursor: pointer;">
                <div style="flex: 1;">
                    <div style="font-weight: 500; display: flex; align-items: center;">
                        <?= \App\Helpers\IconHelper::render('instagram', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">Instagram</span>
                    </div>
                    <small style="display: block; color: #6c757d; margin-top: 0.25rem;">
                        <?= $instagramAccount ? '✓ ' . htmlspecialchars($instagramAccount['username'] ?? 'Подключен') : 'Не подключен' ?>
                    </small>
                </div>
            </label>
            
            <label style="display: flex; align-items: center; padding: 0.75rem; border: 2px solid #dee2e6; border-radius: 6px; cursor: pointer; transition: all 0.2s;" 
                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                <input type="checkbox" name="platforms[]" value="pinterest" style="margin-right: 0.5rem; cursor: pointer;">
                <div style="flex: 1;">
                    <div style="font-weight: 500; display: flex; align-items: center;">
                        <?= \App\Helpers\IconHelper::render('pinterest', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">Pinterest</span>
                    </div>
                    <small style="display: block; color: #6c757d; margin-top: 0.25rem;">
                        <?= $pinterestAccount ? '✓ ' . htmlspecialchars($pinterestAccount['username'] ?? 'Подключен') : 'Не подключен' ?>
                    </small>
                </div>
            </label>
        </div>
        <small style="display: block; margin-top: 0.5rem; color: #6c757d;">Выберите один или несколько каналов для публикации. Можно выбрать позже при создании расписания.</small>
        <?php if (!$youtubeAccount && !$telegramAccount && !$tiktokAccount && !$instagramAccount && !$pinterestAccount): ?>
            <div style="margin-top: 0.75rem; padding: 0.75rem; background: #fff3cd; border-radius: 4px; border-left: 4px solid #ffc107;">
                <strong>⚠️ Нет подключенных каналов</strong>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.9em;">Для публикации необходимо подключить хотя бы один канал в <a href="/integrations">разделе интеграций</a>.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="status">Статус</label>
        <select id="status" name="status">
            <option value="active" selected>Активна</option>
            <option value="paused">На паузе</option>
            <option value="archived">Архивная</option>
        </select>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Создать группу</button>
        <a href="/content-groups" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
