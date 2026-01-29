<?php
$title = 'Загрузка видео';
ob_start();

// Получаем группы пользователя для выбора
$userId = $_SESSION['user_id'] ?? null;
$groups = [];
if ($userId) {
    $groupService = new \App\Modules\ContentGroups\Services\GroupService();
    $groups = $groupService->getUserGroups($userId);
}
?>

<h1>Загрузка видео</h1>

<div class="upload-container">
    <form id="uploadForm" method="POST" action="/videos/upload-multiple" enctype="multipart/form-data" class="upload-form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        
        <div class="form-group">
            <label for="videos">Видео файлы (до 50 файлов)</label>
            <div class="file-upload-area" id="fileUploadArea">
                <input type="file" id="videos" name="videos[]" accept="video/*" multiple required>
                <div class="file-upload-dropzone">
                    <div class="dropzone-content">
                        <?= \App\Helpers\IconHelper::render('upload', 48) ?>
                        <p>Перетащите файлы сюда или <span class="file-select-link">выберите файлы</span></p>
                        <small>Можно выбрать до 50 файлов. Максимальный размер каждого файла: 5GB</small>
                    </div>
                </div>
                <div id="fileList" class="file-list"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="group_id">Группа контента (опционально)</label>
            <select id="group_id" name="group_id">
                <option value="">Не добавлять в группу</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Выберите группу, в которую будут добавлены все загруженные видео</small>
        </div>

        <div class="form-group">
            <label for="title_template">Шаблон названия (опционально)</label>
            <input type="text" id="title_template" name="title_template" placeholder="Например: Видео {index} или оставьте пустым для использования имени файла">
            <small>Доступные переменные: {index} - номер файла, {filename} - имя файла</small>
        </div>

        <div class="form-group">
            <label for="description">Описание (для всех видео)</label>
            <textarea id="description" name="description" rows="5" placeholder="Описание будет применено ко всем загруженным видео"></textarea>
        </div>

        <div class="form-group">
            <label for="tags">Теги (через запятую, для всех видео)</label>
            <input type="text" id="tags" name="tags" placeholder="tag1, tag2, tag3">
        </div>

        <div id="uploadProgress" class="upload-progress" style="display: none;">
            <div class="progress-header">
                <span id="progressText">Загрузка...</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
            </div>
            <div id="fileProgressList" class="file-progress-list"></div>
        </div>

        <button type="submit" class="btn btn-primary" id="uploadBtn">
            <?= \App\Helpers\IconHelper::render('upload', 20, 'icon-inline') ?> Загрузить файлы
        </button>
    </form>
</div>

<style>
.upload-container {
    max-width: 800px;
    margin: 0 auto;
}

.file-upload-area {
    position: relative;
}

.file-upload-area input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-upload-dropzone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    background: #f9f9f9;
    transition: all 0.3s;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-upload-dropzone:hover,
.file-upload-area.dragover .file-upload-dropzone {
    border-color: #007bff;
    background: #f0f7ff;
}

.dropzone-content svg {
    margin-bottom: 1rem;
    opacity: 0.6;
}

.file-select-link {
    color: #007bff;
    text-decoration: underline;
    cursor: pointer;
}

.file-list {
    margin-top: 1rem;
    max-height: 300px;
    overflow-y: auto;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: #f5f5f5;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: all 0.3s;
}

.file-item:hover {
    background: #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.file-item-preview {
    width: 120px;
    height: 68px;
    border-radius: 4px;
    overflow: hidden;
    background: #000;
    position: relative;
    flex-shrink: 0;
    cursor: pointer;
    transition: all 0.3s;
}

.file-item-preview video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.file-item-preview.expanded {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 80vw;
    max-width: 800px;
    height: auto;
    max-height: 80vh;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    cursor: pointer;
}

.file-item-preview.expanded::after {
    content: '✕';
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    z-index: 1001;
}

.file-item-preview.expanded video {
    width: 100%;
    height: auto;
    object-fit: contain;
}

.file-item-info {
    flex: 1;
    min-width: 0;
}

.file-item-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.25rem;
}

.file-item-meta {
    display: flex;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: #666;
    flex-wrap: wrap;
}

.file-item-size,
.file-item-duration,
.file-item-type {
    display: inline-block;
}

.file-item-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.file-item-preview-btn,
.file-item-remove {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 0.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.file-item-preview-btn {
    background: #007bff;
}

.file-item-preview-btn:hover {
    background: #0056b3;
}

.file-item-remove:hover {
    background: #c82333;
}

.upload-progress {
    margin: 1.5rem 0;
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 8px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.progress-bar {
    width: 100%;
    height: 24px;
    background: #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #0056b3);
    transition: width 0.3s;
    border-radius: 12px;
}

.file-progress-list {
    max-height: 200px;
    overflow-y: auto;
}

.file-progress-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem;
    border-bottom: 1px solid #e0e0e0;
}

.file-progress-item:last-child {
    border-bottom: none;
}

.file-progress-name {
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.875rem;
}

.file-progress-status {
    margin-left: 1rem;
    font-size: 0.875rem;
}

.file-progress-status.success {
    color: #28a745;
}

.file-progress-status.error {
    color: #dc3545;
}

.file-progress-status.uploading {
    color: #007bff;
}

.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    background: #333;
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 10000;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s;
    max-width: 400px;
    white-space: pre-line;
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast-success {
    background: #28a745;
}

.toast-error {
    background: #dc3545;
}

.toast-warning {
    background: #ffc107;
    color: #333;
}

.toast-info {
    background: #17a2b8;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('videos');
    const fileList = document.getElementById('fileList');
    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressText = document.getElementById('progressText');
    const progressPercent = document.getElementById('progressPercent');
    const progressFill = document.getElementById('progressFill');
    const fileProgressList = document.getElementById('fileProgressList');
    const dropzone = document.querySelector('.file-upload-dropzone');
    const fileUploadArea = document.getElementById('fileUploadArea');
    
    let selectedFiles = [];
    const MAX_FILES = 50;

    // Обработка выбора файлов
    fileInput.addEventListener('change', function(e) {
        handleFiles(Array.from(e.target.files));
    });

    // Drag and drop
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
        handleFiles(files);
    });

    function handleFiles(files) {
        // Ограничение до 50 файлов
        if (selectedFiles.length + files.length > MAX_FILES) {
            showToast(`Можно загрузить максимум ${MAX_FILES} файлов. Уже выбрано: ${selectedFiles.length}`, 'warning');
            files = files.slice(0, MAX_FILES - selectedFiles.length);
        }

        const MAX_FILE_SIZE = 5 * 1024 * 1024 * 1024; // 5GB
        const validVideoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
        
        files.forEach(file => {
            if (selectedFiles.length >= MAX_FILES) return;
            
            // Проверка типа файла
            if (!file.type.startsWith('video/') && !validVideoTypes.includes(file.type)) {
                showToast(`Файл ${file.name} не является поддерживаемым видео файлом`, 'error');
                return;
            }
            
            // Проверка размера файла
            if (file.size > MAX_FILE_SIZE) {
                showToast(`Файл ${file.name} слишком большой (максимум 5GB)`, 'error');
                return;
            }
            
            // Проверка на дубликаты
            if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                showToast(`Файл ${file.name} уже добавлен`, 'warning');
                return;
            }
            
            selectedFiles.push(file);
        });

        updateFileList();
        updateFileInput();
    }

    function updateFileList() {
        fileList.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.dataset.index = index;
            
            // Создаем превью видео
            const preview = document.createElement('div');
            preview.className = 'file-item-preview';
            const video = document.createElement('video');
            video.src = URL.createObjectURL(file);
            video.preload = 'metadata';
            video.muted = true;
            video.onloadedmetadata = function() {
                const duration = formatDuration(video.duration);
                const durationEl = fileItem.querySelector('.file-item-duration');
                if (durationEl) durationEl.textContent = duration;
            };
            preview.appendChild(video);
            
            const info = document.createElement('div');
            info.className = 'file-item-info';
            info.innerHTML = `
                <div class="file-item-name">${escapeHtml(file.name)}</div>
                <div class="file-item-meta">
                    <span class="file-item-size">${formatFileSize(file.size)}</span>
                    <span class="file-item-duration">Загрузка...</span>
                    <span class="file-item-type">${file.type || 'video/mp4'}</span>
                </div>
            `;
            
            const actions = document.createElement('div');
            actions.className = 'file-item-actions';
            actions.innerHTML = `
                <button type="button" class="file-item-preview-btn" onclick="togglePreview(${index})" title="Предпросмотр"><?= \App\Helpers\IconHelper::render('view', 16) ?></button>
                <button type="button" class="file-item-remove" onclick="removeFile(${index})" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 16) ?></button>
            `;
            
            fileItem.appendChild(preview);
            fileItem.appendChild(info);
            fileItem.appendChild(actions);
            fileList.appendChild(fileItem);
        });
    }
    
    function formatDuration(seconds) {
        if (!seconds || isNaN(seconds)) return '--:--';
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${minutes}:${String(secs).padStart(2, '0')}`;
    }
    
    window.togglePreview = function(index) {
        const fileItem = document.querySelector(`.file-item[data-index="${index}"]`);
        if (!fileItem) return;
        
        const preview = fileItem.querySelector('.file-item-preview');
        const video = preview.querySelector('video');
        
        // Закрываем все другие открытые превью
        document.querySelectorAll('.file-item-preview.expanded').forEach(p => {
            if (p !== preview) {
                p.classList.remove('expanded');
                p.querySelector('video').pause();
            }
        });
        
        if (preview.classList.contains('expanded')) {
            preview.classList.remove('expanded');
            video.pause();
        } else {
            preview.classList.add('expanded');
            video.play().catch(e => console.error('Error playing video:', e));
        }
    };
    
    // Закрытие превью при клике вне его
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.file-item-preview.expanded')) {
            document.querySelectorAll('.file-item-preview.expanded').forEach(preview => {
                preview.classList.remove('expanded');
                preview.querySelector('video').pause();
            });
        }
    });

    function updateFileInput() {
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        fileInput.files = dataTransfer.files;
    }

    window.removeFile = function(index) {
        selectedFiles.splice(index, 1);
        updateFileList();
        updateFileInput();
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

    // Валидация перед отправкой
    function validateFiles() {
        if (selectedFiles.length === 0) {
            showToast('Выберите хотя бы один файл', 'error');
            return false;
        }
        
        const MAX_FILE_SIZE = 5 * 1024 * 1024 * 1024; // 5GB
        const invalidFiles = [];
        
        selectedFiles.forEach((file, index) => {
            if (file.size > MAX_FILE_SIZE) {
                invalidFiles.push({index, name: file.name, reason: 'Слишком большой размер'});
            }
            if (!file.type.startsWith('video/')) {
                invalidFiles.push({index, name: file.name, reason: 'Неверный тип файла'});
            }
        });
        
        if (invalidFiles.length > 0) {
            const message = invalidFiles.map(f => `${f.name}: ${f.reason}`).join('\n');
            showToast('Обнаружены невалидные файлы:\n' + message, 'error');
            return false;
        }
        
        return true;
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Обработка отправки формы
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!validateFiles()) {
            return;
        }

        uploadBtn.disabled = true;
        uploadProgress.style.display = 'block';
        progressFill.style.width = '0%';
        progressPercent.textContent = '0%';
        fileProgressList.innerHTML = '';

        const formData = new FormData(uploadForm);
        
        // Добавляем информацию о файлах для отслеживания
        selectedFiles.forEach((file, index) => {
            formData.append(`file_info[${index}][name]`, file.name);
            formData.append(`file_info[${index}][size]`, file.size);
        });

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = percent + '%';
                progressPercent.textContent = percent + '%';
                progressText.textContent = `Загрузка: ${percent}%`;
            }
        });

        xhr.addEventListener('load', function() {
            let response = null;
            try {
                response = xhr.responseText ? JSON.parse(xhr.responseText) : null;
            } catch (e) {
                console.error('Error parsing response JSON:', e, xhr.responseText);
            }

            if (xhr.status === 200 && response && response.success) {
                progressFill.style.width = '100%';
                progressPercent.textContent = '100%';
                progressText.textContent = 'Загрузка завершена!';
                
                // Показываем результаты
                if (response.data && response.data.results) {
                    response.data.results.forEach(result => {
                        const item = document.createElement('div');
                        item.className = 'file-progress-item';
                        const statusClass = result.success ? 'success' : 'error';
                        const statusText = result.success ? '✓ Загружено' : '✗ Ошибка: ' + (result.message || 'Неизвестная ошибка');
                        item.innerHTML = `
                            <span class="file-progress-name">${escapeHtml(result.fileName || '')}</span>
                            <span class="file-progress-status ${statusClass}">${statusText}</span>
                        `;
                        fileProgressList.appendChild(item);
                    });
                }
                
                setTimeout(() => {
                    window.location.href = '/videos';
                }, 2000);
            } else {
                const message = response && response.message
                    ? response.message
                    : 'Ошибка загрузки. Код: ' + xhr.status;
                alert(message);
                uploadBtn.disabled = false;
            }
        });

        xhr.addEventListener('error', function() {
            alert('Произошла ошибка при загрузке файлов');
            uploadBtn.disabled = false;
        });

        xhr.open('POST', uploadForm.action);
        xhr.send(formData);
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
