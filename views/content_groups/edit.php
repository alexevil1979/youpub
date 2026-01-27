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
        <label>
            <input type="checkbox" id="use_auto_generation" name="use_auto_generation" value="1" <?= !empty($group['use_auto_generation']) ? 'checked' : '' ?>>
            🚀 Использовать автогенерацию контента на основе имени файла
        </label>
        <small>Если включено, контент будет автоматически генерироваться из имени файла (например: She_s_SO_FLEXIBLE.mp4 → "She s SO FLEXIBLE"). Шаблон будет игнорироваться.</small>
    </div>

    <div class="form-group" id="template_group" style="<?= !empty($group['use_auto_generation']) ? 'display: none;' : '' ?>">
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
        <label for="schedule_id">Расписание публикации (опционально)</label>
        <select id="schedule_id" name="schedule_id">
            <option value="">Без расписания</option>
            <?php foreach ($schedules as $schedule): ?>
                <option value="<?= $schedule['id'] ?>" <?= (isset($group['schedule_id']) && $group['schedule_id'] == $schedule['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($schedule['schedule_type'] ?? 'fixed') ?> - 
                    <?= htmlspecialchars($schedule['platform'] ?? 'youtube') ?> - 
                    <?= $schedule['publish_at'] ? date('d.m.Y H:i', strtotime($schedule['publish_at'])) : 'не указано' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Расписание определяет когда и как публиковать видео из группы. Можно <a href="/content-groups/schedules/create?group_id=<?= $group['id'] ?>">создать новое расписание</a>.</small>
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
                            <?php
                            $isSelected = false;
                            foreach ($selectedIntegrations as $sel) {
                                if (($sel['platform'] ?? '') === 'youtube' && ($sel['integration_id'] ?? 0) === (int)$account['id']) {
                                    $isSelected = true;
                                    break;
                                }
                            }
                            ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="youtube_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;" <?= $isSelected ? 'checked' : '' ?>>
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
                            <?php
                            $isSelected = false;
                            foreach ($selectedIntegrations as $sel) {
                                if (($sel['platform'] ?? '') === 'telegram' && ($sel['integration_id'] ?? 0) === (int)$account['id']) {
                                    $isSelected = true;
                                    break;
                                }
                            }
                            ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="telegram_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;" <?= $isSelected ? 'checked' : '' ?>>
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
                            <?php
                            $isSelected = false;
                            foreach ($selectedIntegrations as $sel) {
                                if (($sel['platform'] ?? '') === 'tiktok' && ($sel['integration_id'] ?? 0) === (int)$account['id']) {
                                    $isSelected = true;
                                    break;
                                }
                            }
                            ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="tiktok_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;" <?= $isSelected ? 'checked' : '' ?>>
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
                            <?php
                            $isSelected = false;
                            foreach ($selectedIntegrations as $sel) {
                                if (($sel['platform'] ?? '') === 'instagram' && ($sel['integration_id'] ?? 0) === (int)$account['id']) {
                                    $isSelected = true;
                                    break;
                                }
                            }
                            ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="instagram_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;" <?= $isSelected ? 'checked' : '' ?>>
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
                            <?php
                            $isSelected = false;
                            foreach ($selectedIntegrations as $sel) {
                                if (($sel['platform'] ?? '') === 'pinterest' && ($sel['integration_id'] ?? 0) === (int)$account['id']) {
                                    $isSelected = true;
                                    break;
                                }
                            }
                            ?>
                            <label style="display: flex; align-items: center; padding: 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                                   onmouseover="this.style.borderColor='#007bff'; this.style.backgroundColor='#f8f9ff';" 
                                   onmouseout="this.style.borderColor='#dee2e6'; this.style.backgroundColor='';">
                                <input type="checkbox" name="integrations[]" value="pinterest_<?= $account['id'] ?>" style="margin-right: 0.5rem; cursor: pointer;" <?= $isSelected ? 'checked' : '' ?>>
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
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Загрузка новых видео -->
        <div style="padding: 1rem; background: white; border-radius: 6px; border: 2px solid #007bff;">
            <h4 style="margin-top: 0; margin-bottom: 0.75rem; color: #007bff;">Загрузить новые видео</h4>
            <div class="file-upload-area" id="fileUploadArea" style="position: relative; margin-bottom: 0.75rem;">
                <input type="file" id="new-videos" name="new-videos[]" accept="video/*" multiple style="position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;">
                <div class="file-upload-dropzone" style="border: 2px dashed #007bff; border-radius: 6px; padding: 1.5rem; text-align: center; background: #f0f7ff; min-height: 120px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                    <div style="margin-bottom: 0.5rem;">
                        <?= \App\Helpers\IconHelper::render('upload', 32) ?>
                    </div>
                    <p style="margin: 0; font-size: 0.9rem;">Перетащите файлы сюда<br>или <span style="color: #007bff; text-decoration: underline; cursor: pointer;">выберите файлы</span></p>
                    <small style="display: block; margin-top: 0.5rem; color: #6c757d;">Максимум 5GB на файл</small>
                </div>
                <div id="newFileList" style="margin-top: 0.75rem; max-height: 150px; overflow-y: auto;"></div>
            </div>
            <button type="button" id="upload-new-videos-btn" class="btn btn-primary" style="width: 100%;" disabled>
                <?= \App\Helpers\IconHelper::render('upload', 16, 'icon-inline') ?> Загрузить и добавить в группу
            </button>
            <div id="upload-status" style="margin-top: 0.75rem; display: none;"></div>
        </div>
        
        <!-- Выбор существующих видео -->
        <div style="padding: 1rem; background: white; border-radius: 6px; border: 2px solid #28a745;">
            <h4 style="margin-top: 0; margin-bottom: 0.75rem; color: #28a745;">Добавить существующие видео</h4>
            <?php if (empty($availableVideos)): ?>
                <p style="color: #6c757d; margin-bottom: 0.75rem; font-size: 0.9rem;">Нет доступных видео для добавления. Все ваши видео уже в этой группе.</p>
            <?php else: ?>
                <div style="margin-bottom: 0.75rem;">
                    <select id="video-select" multiple style="width: 100%; min-height: 150px; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem;">
                        <?php foreach ($availableVideos as $video): ?>
                            <option value="<?= $video['id'] ?>">
                                <?= htmlspecialchars($video['title'] ?: $video['file_name']) ?>
                                <?php if ($video['file_size']): ?>
                                    (<?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 0.5rem; color: #6c757d; font-size: 0.85rem;">Удерживайте Ctrl (Cmd на Mac) для выбора нескольких</small>
                </div>
                <button type="button" id="add-videos-btn" class="btn btn-success" style="width: 100%;">
                    <?= \App\Helpers\IconHelper::render('add', 16, 'icon-inline') ?> Добавить выбранные
                </button>
            <?php endif; ?>
            <div id="add-videos-status" style="margin-top: 0.75rem; display: none;"></div>
        </div>
    </div>
</div>

<style>
.file-upload-area.dragover .file-upload-dropzone {
    border-color: #0056b3;
    background: #e6f2ff;
}

.new-file-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem;
    background: #f5f5f5;
    border-radius: 4px;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.new-file-item-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-right: 0.5rem;
}

.new-file-item-size {
    color: #6c757d;
    margin-right: 0.5rem;
}

.new-file-item-remove {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
    font-size: 0.75rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newVideosInput = document.getElementById('new-videos');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const newFileList = document.getElementById('newFileList');
    const uploadNewVideosBtn = document.getElementById('upload-new-videos-btn');
    const uploadStatus = document.getElementById('upload-status');
    let selectedNewFiles = [];
    
    // Обработка выбора файлов
    if (newVideosInput) {
        newVideosInput.addEventListener('change', function(e) {
            handleNewFiles(Array.from(e.target.files));
        });
    }
    
    // Drag and drop
    if (fileUploadArea) {
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('video/'));
            handleNewFiles(files);
        });
    }
    
    function handleNewFiles(files) {
        const MAX_FILE_SIZE = 5 * 1024 * 1024 * 1024; // 5GB
        
        files.forEach(file => {
            if (!file.type.startsWith('video/')) {
                showStatus('Файл ' + file.name + ' не является видео файлом', 'error');
                return;
            }
            
            if (file.size > MAX_FILE_SIZE) {
                showStatus('Файл ' + file.name + ' слишком большой (максимум 5GB)', 'error');
                return;
            }
            
            if (selectedNewFiles.some(f => f.name === file.name && f.size === file.size)) {
                return;
            }
            
            selectedNewFiles.push(file);
        });
        
        updateNewFileList();
    }
    
    function updateNewFileList() {
        if (!newFileList) return;
        
        newFileList.innerHTML = '';
        selectedNewFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'new-file-item';
            item.innerHTML = `
                <span class="new-file-item-name">${escapeHtml(file.name)}</span>
                <span class="new-file-item-size">${formatFileSize(file.size)}</span>
                <button type="button" class="new-file-item-remove" onclick="removeNewFile(${index})">✕</button>
            `;
            newFileList.appendChild(item);
        });
        
        if (uploadNewVideosBtn) {
            uploadNewVideosBtn.disabled = selectedNewFiles.length === 0;
        }
    }
    
    window.removeNewFile = function(index) {
        selectedNewFiles.splice(index, 1);
        updateNewFileList();
        if (newVideosInput) {
            const dataTransfer = new DataTransfer();
            selectedNewFiles.forEach(file => dataTransfer.items.add(file));
            newVideosInput.files = dataTransfer.files;
        }
    };
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showStatus(message, type) {
        if (!uploadStatus) return;
        uploadStatus.style.display = 'block';
        uploadStatus.className = 'alert alert-' + (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'));
        uploadStatus.textContent = message;
    }
    
    // Загрузка новых видео
    if (uploadNewVideosBtn) {
        uploadNewVideosBtn.addEventListener('click', function() {
            if (selectedNewFiles.length === 0) {
                showStatus('Выберите файлы для загрузки', 'error');
                return;
            }
            
            uploadNewVideosBtn.disabled = true;
            uploadNewVideosBtn.innerHTML = 'Загрузка...';
            showStatus('Загрузка файлов...', 'info');
            
            const csrfToken = <?= json_encode($csrfToken) ?>;
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('group_id', <?= $group['id'] ?>);
            
            selectedNewFiles.forEach((file, index) => {
                formData.append('videos[]', file);
            });
            
            fetch('/videos/upload-multiple', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus('Видео успешно загружены и добавлены в группу!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showStatus('Ошибка: ' + (data.message || 'Не удалось загрузить видео'), 'error');
                    uploadNewVideosBtn.disabled = false;
                    uploadNewVideosBtn.innerHTML = '<?= \App\Helpers\IconHelper::render('upload', 16, 'icon-inline') ?> Загрузить и добавить в группу';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatus('Произошла ошибка при загрузке видео', 'error');
                uploadNewVideosBtn.disabled = false;
                uploadNewVideosBtn.innerHTML = '<?= \App\Helpers\IconHelper::render('upload', 16, 'icon-inline') ?> Загрузить и добавить в группу';
            });
        });
    }
    
    // Добавление существующих видео
    const addVideosBtn = document.getElementById('add-videos-btn');
    const videoSelect = document.getElementById('video-select');
    const addVideosStatus = document.getElementById('add-videos-status');
    
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
            addVideosStatus.style.display = 'block';
            addVideosStatus.className = 'alert';
            addVideosStatus.textContent = 'Добавление видео...';
            
            const csrfToken = <?= json_encode($csrfToken) ?>;
            
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
                    addVideosStatus.className = 'alert alert-success';
                    addVideosStatus.textContent = 'Видео успешно добавлены в группу!';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    addVideosStatus.className = 'alert alert-error';
                    addVideosStatus.textContent = 'Ошибка: ' + (data.message || 'Не удалось добавить видео');
                    addVideosBtn.disabled = false;
                    addVideosBtn.style.opacity = '1';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                addVideosStatus.className = 'alert alert-error';
                addVideosStatus.textContent = 'Произошла ошибка при добавлении видео';
                addVideosBtn.disabled = false;
                addVideosBtn.style.opacity = '1';
            });
        });
    }
});

// Переключение видимости шаблона при изменении автогенерации
document.addEventListener('DOMContentLoaded', function() {
    const autoGenCheckbox = document.getElementById('use_auto_generation');
    const templateGroup = document.getElementById('template_group');
    
    if (autoGenCheckbox && templateGroup) {
        autoGenCheckbox.addEventListener('change', function() {
            templateGroup.style.display = this.checked ? 'none' : 'block';
        });
    }
});
</script>

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
