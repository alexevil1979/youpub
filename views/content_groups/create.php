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
        <label>
            <input type="checkbox" id="use_auto_generation" name="use_auto_generation" value="1">
            🚀 Использовать автогенерацию контента на основе имени файла
        </label>
        <small>Если включено, контент будет автоматически генерироваться из имени файла (например: She_s_SO_FLEXIBLE.mp4 → "She s SO FLEXIBLE"). Шаблон будет игнорироваться.</small>
    </div>

    <div class="form-group" id="template_group">
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
        <label for="schedule_id">Расписание публикации (опционально)</label>
        <select id="schedule_id" name="schedule_id">
            <option value="">Без расписания</option>
            <?php foreach ($schedules as $schedule): ?>
                <option value="<?= $schedule['id'] ?>">
                    <?= htmlspecialchars($schedule['schedule_type'] ?? 'fixed') ?> - 
                    <?= htmlspecialchars($schedule['platform'] ?? 'youtube') ?> - 
                    <?= $schedule['publish_at'] ? date('d.m.Y H:i', strtotime($schedule['publish_at'])) : 'не указано' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Расписание определяет когда и как публиковать видео из группы. Можно выбрать позже при редактировании группы или <a href="/content-groups/schedules/create">создать новое расписание</a>.</small>
    </div>

    <div class="form-group">
        <label>Каналы публикации</label>
        <div style="margin-top: 0.5rem;">
            <?php if (!empty($youtubeAccounts)): ?>
                <div style="margin-bottom: 1rem; padding: 1rem; background: #fff; border: 1px solid #dee2e6; border-radius: 6px;">
                    <div style="font-weight: 500; display: flex; align-items: center; margin-bottom: 0.75rem;">
                        <?= \App\Helpers\IconHelper::render('youtube', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">YouTube</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($youtubeAccounts as $account): ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="youtube_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">
                                        <?= htmlspecialchars($account['channel_name'] ?? $account['account_name'] ?? 'Канал ' . $account['id']) ?>
                                        <?php if (!empty($account['is_default'])): ?>
                                            <span style="color: #28a745; font-size: 0.85em; margin-left: 0.5rem;">(по умолчанию)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($account['channel_id']): ?>
                                        <small style="color: #6c757d; font-size: 0.85em;">ID: <?= htmlspecialchars($account['channel_id']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($telegramAccounts)): ?>
                <div style="margin-bottom: 1rem; padding: 1rem; background: #fff; border: 1px solid #dee2e6; border-radius: 6px;">
                    <div style="font-weight: 500; display: flex; align-items: center; margin-bottom: 0.75rem;">
                        <?= \App\Helpers\IconHelper::render('telegram', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">Telegram</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($telegramAccounts as $account): ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="telegram_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">
                                        <?php
                                        $name = $account['channel_username'] ? '@' . $account['channel_username'] : ($account['channel_name'] ?? 'Канал ' . $account['id']);
                                        echo htmlspecialchars($name);
                                        ?>
                                        <?php if (!empty($account['is_default'])): ?>
                                            <span style="color: #28a745; font-size: 0.85em; margin-left: 0.5rem;">(по умолчанию)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($tiktokAccounts)): ?>
                <div style="margin-bottom: 1rem; padding: 1rem; background: #fff; border: 1px solid #dee2e6; border-radius: 6px;">
                    <div style="font-weight: 500; display: flex; align-items: center; margin-bottom: 0.75rem;">
                        <?= \App\Helpers\IconHelper::render('tiktok', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">TikTok</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($tiktokAccounts as $account): ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="tiktok_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">
                                        <?= htmlspecialchars($account['username'] ?? 'Аккаунт ' . $account['id']) ?>
                                        <?php if (!empty($account['is_default'])): ?>
                                            <span style="color: #28a745; font-size: 0.85em; margin-left: 0.5rem;">(по умолчанию)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($instagramAccounts)): ?>
                <div style="margin-bottom: 1rem; padding: 1rem; background: #fff; border: 1px solid #dee2e6; border-radius: 6px;">
                    <div style="font-weight: 500; display: flex; align-items: center; margin-bottom: 0.75rem;">
                        <?= \App\Helpers\IconHelper::render('instagram', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">Instagram</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($instagramAccounts as $account): ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="instagram_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">
                                        <?= htmlspecialchars($account['username'] ?? 'Аккаунт ' . $account['id']) ?>
                                        <?php if (!empty($account['is_default'])): ?>
                                            <span style="color: #28a745; font-size: 0.85em; margin-left: 0.5rem;">(по умолчанию)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($pinterestAccounts)): ?>
                <div style="margin-bottom: 1rem; padding: 1rem; background: #fff; border: 1px solid #dee2e6; border-radius: 6px;">
                    <div style="font-weight: 500; display: flex; align-items: center; margin-bottom: 0.75rem;">
                        <?= \App\Helpers\IconHelper::render('pinterest', 20, 'icon-inline') ?>
                        <span style="margin-left: 0.5rem;">Pinterest</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($pinterestAccounts as $account): ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="pinterest_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">
                                        <?= htmlspecialchars($account['username'] ?? 'Аккаунт ' . $account['id']) ?>
                                        <?php if (!empty($account['is_default'])): ?>
                                            <span style="color: #28a745; font-size: 0.85em; margin-left: 0.5rem;">(по умолчанию)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <small style="display: block; margin-top: 0.5rem; color: #6c757d;">Выберите конкретные каналы для публикации. Можно выбрать несколько каналов одной или разных платформ.</small>
        <?php if (empty($youtubeAccounts) && empty($telegramAccounts) && empty($tiktokAccounts) && empty($instagramAccounts) && empty($pinterestAccounts)): ?>
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
