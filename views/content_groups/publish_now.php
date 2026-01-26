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
                <?= htmlspecialchars(trim($preview['description'] ?? '') ?: 'Посмотрите это видео! 🎬') ?>
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

<?php if ($templateData): ?>
<div class="info-card" style="margin-bottom: 1.5rem; background-color: #f8f9fa; border: 1px solid #dee2e6;">
    <h3 style="color: #495057; margin-bottom: 1rem;">🔍 Отладочная информация: Доступные варианты шаблона</h3>
    
    <?php
    $titleVariants = !empty($templateData['title_variants']) ? json_decode($templateData['title_variants'], true) : [];
    $descriptionVariants = !empty($templateData['description_variants']) ? json_decode($templateData['description_variants'], true) : [];
    $tagVariants = !empty($templateData['tag_variants']) ? json_decode($templateData['tag_variants'], true) : [];
    $baseTags = !empty($templateData['base_tags']) ? array_map('trim', explode(',', $templateData['base_tags'])) : [];
    $emojiGroups = !empty($templateData['emoji_groups']) ? json_decode($templateData['emoji_groups'], true) : [];
    $hookType = $templateData['hook_type'] ?? 'emotional';
    
    // Маппинг между значениями hook_type из БД и ключами в description_variants
    $hookTypeMapping = [
        'atmospheric' => 'atmosphere',
        'intriguing' => 'intrigue',
        'emotional' => 'emotional',
        'visual' => 'visual',
        'educational' => 'educational',
        'question' => 'question',
        'cta' => 'cta',
    ];
    $normalizedHookType = $hookTypeMapping[$hookType] ?? $hookType;
    ?>
    
    <div style="margin-bottom: 1rem;">
        <strong style="color: #495057;">Названия (title_variants):</strong>
        <?php if (empty($titleVariants)): ?>
            <div style="color: #dc3545; margin-top: 0.5rem;">⚠️ Вариантов нет (массив пуст)</div>
        <?php else: ?>
            <div style="margin-top: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($titleVariants as $index => $variant): ?>
                        <li style="margin-bottom: 0.25rem; color: #495057;">
                            <span style="color: #6c757d; font-size: 0.9em;">[<?= $index ?>]</span> 
                            <?= htmlspecialchars($variant) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div style="margin-top: 0.5rem; color: #28a745; font-size: 0.9em;">
                ✅ Всего вариантов: <?= count($titleVariants) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom: 1rem;">
        <strong style="color: #495057;">Описания (description_variants) для типа "<?= htmlspecialchars($hookType) ?>" (нормализован: "<?= htmlspecialchars($normalizedHookType) ?>"):</strong>
        <?php 
        $hookDescriptions = isset($descriptionVariants[$normalizedHookType]) ? $descriptionVariants[$normalizedHookType] : [];
        ?>
        <?php if (empty($hookDescriptions)): ?>
            <div style="color: #dc3545; margin-top: 0.5rem;">
                ⚠️ Вариантов нет для типа "<?= htmlspecialchars($hookType) ?>" (нормализован: "<?= htmlspecialchars($normalizedHookType) ?>")
                <?php if (!empty($descriptionVariants)): ?>
                    <div style="margin-top: 0.25rem; font-size: 0.9em;">
                        Доступные типы: <?= implode(', ', array_keys($descriptionVariants)) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="margin-top: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($hookDescriptions as $index => $variant): ?>
                        <li style="margin-bottom: 0.5rem; color: #495057; white-space: pre-wrap;">
                            <span style="color: #6c757d; font-size: 0.9em;">[<?= $index ?>]</span> 
                            <?= htmlspecialchars($variant) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div style="margin-top: 0.5rem; color: #28a745; font-size: 0.9em;">
                ✅ Всего вариантов: <?= count($hookDescriptions) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom: 1rem;">
        <strong style="color: #495057;">Emoji группы для типа "<?= htmlspecialchars($hookType) ?>" (нормализован: "<?= htmlspecialchars($normalizedHookType) ?>"):</strong>
        <?php 
        $hookEmojis = isset($emojiGroups[$normalizedHookType]) ? (is_array($emojiGroups[$normalizedHookType]) ? $emojiGroups[$normalizedHookType] : explode(',', $emojiGroups[$normalizedHookType])) : [];
        ?>
        <?php if (empty($hookEmojis)): ?>
            <div style="color: #dc3545; margin-top: 0.5rem;">
                ⚠️ Emoji нет для типа "<?= htmlspecialchars($hookType) ?>" (нормализован: "<?= htmlspecialchars($normalizedHookType) ?>")
            </div>
        <?php else: ?>
            <div style="margin-top: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px;">
                <div style="font-size: 1.2em; word-break: break-word;">
                    <?= htmlspecialchars(implode(' ', $hookEmojis)) ?>
                </div>
            </div>
            <div style="margin-top: 0.5rem; color: #28a745; font-size: 0.9em;">
                ✅ Всего emoji: <?= count($hookEmojis) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom: 1rem;">
        <strong style="color: #495057;">Базовые теги (base_tags):</strong>
        <?php if (empty($baseTags)): ?>
            <div style="color: #dc3545; margin-top: 0.5rem;">⚠️ Базовых тегов нет</div>
        <?php else: ?>
            <div style="margin-top: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px;">
                <?= htmlspecialchars(implode(', ', $baseTags)) ?>
            </div>
            <div style="margin-top: 0.5rem; color: #28a745; font-size: 0.9em;">
                ✅ Всего базовых тегов: <?= count($baseTags) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom: 1rem;">
        <strong style="color: #495057;">Варианты наборов тегов (tag_variants):</strong>
        <?php if (empty($tagVariants)): ?>
            <div style="color: #dc3545; margin-top: 0.5rem;">⚠️ Вариантов наборов тегов нет</div>
        <?php else: ?>
            <div style="margin-top: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                <?php foreach ($tagVariants as $index => $tagSet): ?>
                    <div style="margin-bottom: 0.75rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                        <strong style="color: #6c757d; font-size: 0.9em;">Набор <?= $index + 1 ?>:</strong>
                        <div style="color: #495057; margin-top: 0.25rem;">
                            <?= htmlspecialchars(is_array($tagSet) ? implode(', ', $tagSet) : $tagSet) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 0.5rem; color: #28a745; font-size: 0.9em;">
                ✅ Всего наборов: <?= count($tagVariants) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 1rem; padding: 0.75rem; background: #e7f3ff; border-radius: 4px; border-left: 4px solid #007bff;">
        <strong style="color: #004085;">Текущий выбранный вариант:</strong>
        <div style="margin-top: 0.5rem; color: #004085;">
            <strong>Название:</strong> <span id="debug-current-title"><?= htmlspecialchars($preview['title'] ?? '—') ?></span><br>
            <strong>Описание:</strong> <span id="debug-current-description"><?= htmlspecialchars(mb_substr(trim($preview['description'] ?? ''), 0, 100)) ?><?= mb_strlen(trim($preview['description'] ?? '')) > 100 ? '...' : '' ?></span><br>
            <strong>Теги:</strong> <span id="debug-current-tags"><?= htmlspecialchars($preview['tags'] ?? '—') ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="form-actions">
    <a href="/content-groups/<?= (int)$group['id'] ?>" class="btn btn-secondary">Назад к группе</a>
    <button type="button"
            class="btn btn-success"
            id="publish-now-btn"
            <?= $canPublish ? '' : 'disabled' ?>
            title="Опубликовать сейчас"
            aria-label="Опубликовать сейчас">
        <?= \App\Helpers\IconHelper::render('publish', 16, 'icon-inline') ?>
    </button>
    <?php if (!$canPublish): ?>
        <span style="margin-left: 0.75rem; color: #e74c3c; font-size: 0.9rem;">Этот файл нельзя опубликовать сейчас</span>
    <?php endif; ?>
    <div id="publish-status" style="margin-top: 1rem; display: none;"></div>
</div>

<script>
// Защита от двойного клика на кнопку публикации
let isPublishing = false;

function showStatus(message, isError = false) {
    const statusDiv = document.getElementById('publish-status');
    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.className = isError ? 'alert alert-error' : 'alert alert-success';
        statusDiv.textContent = message;
        
        if (!isError) {
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }
    }
}

function publishVideo() {
    if (isPublishing) {
        return;
    }
    
    if (!confirm('Опубликовать видео сейчас?')) {
        return;
    }
    
    isPublishing = true;
    const btn = document.getElementById('publish-now-btn');
    const statusDiv = document.getElementById('publish-status');
    const originalText = btn ? btn.innerHTML : '';
    
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.innerHTML = 'Публикация...';
    }
    
    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.className = 'alert';
        statusDiv.textContent = 'Публикация видео...';
    }
    
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    
    fetch('/content-groups/<?= (int)$group['id'] ?>/files/<?= (int)$file['id'] ?>/publish-now', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => {
        // Проверяем, это редирект или JSON ответ
        if (response.redirected) {
            // Если редирект, значит это обычный POST запрос
            // Нужно перезагрузить страницу, чтобы увидеть сообщение
            window.location.href = response.url;
            return;
        }
        
        // Пытаемся получить JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        }
        
        // Если не JSON, значит HTML (редирект произошел)
        window.location.reload();
        return null;
    })
    .then(data => {
        if (data === null) {
            // Редирект произошел, страница перезагрузится
            return;
        }
        
        if (data && data.success) {
            showStatus('Видео успешно опубликовано!', false);
            // Перезагружаем страницу через 2 секунды, чтобы увидеть обновленный статус
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            const errorMsg = data && data.message ? data.message : 'Не удалось опубликовать видео';
            showStatus('Ошибка: ' + errorMsg, true);
            isPublishing = false;
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.innerHTML = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Publish error:', error);
        showStatus('Ошибка при публикации: ' + error.message, true);
        isPublishing = false;
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '';
            btn.innerHTML = originalText;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const publishBtn = document.getElementById('publish-now-btn');
    if (publishBtn) {
        publishBtn.addEventListener('click', publishVideo);
    }

    const regenerateBtn = document.getElementById('regenerate-preview-btn');
    if (!regenerateBtn) {
        return;
    }

    const csrfToken = <?= json_encode($csrfToken) ?>;
    const previewTitle = document.getElementById('publish-preview-title');
    const previewDescription = document.getElementById('publish-preview-description');
    const previewTags = document.getElementById('publish-preview-tags');

    regenerateBtn.addEventListener('click', () => {
        const originalTitle = regenerateBtn.title;
        regenerateBtn.disabled = true;
        regenerateBtn.title = 'Генерация...';
        regenerateBtn.style.opacity = '0.6';
        
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
                // TemplateService всегда генерирует описание с fallback, но на всякий случай проверяем
                const description = preview.description || 'Посмотрите это видео! 🎬';
                previewDescription.textContent = description.trim() || 'Посмотрите это видео! 🎬';
            }
            if (previewTags) {
                previewTags.textContent = preview.tags || '—';
            }
            
            // Обновляем отладочные поля
            const debugTitle = document.getElementById('debug-current-title');
            const debugDescription = document.getElementById('debug-current-description');
            const debugTags = document.getElementById('debug-current-tags');
            if (debugTitle) {
                debugTitle.textContent = preview.title || '—';
            }
            if (debugDescription) {
                const desc = preview.description || '';
                debugDescription.textContent = desc.length > 100 ? desc.substring(0, 100) + '...' : desc;
            }
            if (debugTags) {
                debugTags.textContent = preview.tags || '—';
            }
            
            // Визуальная обратная связь - кратковременное выделение
            [previewTitle, previewDescription, previewTags].forEach(el => {
                if (el) {
                    el.style.transition = 'background-color 0.3s';
                    el.style.backgroundColor = '#d4edda';
                    setTimeout(() => {
                        el.style.backgroundColor = '';
                    }, 500);
                }
            });
        })
        .catch(error => {
            console.error('Preview regeneration error:', error);
            alert('Не удалось перегенерировать оформление: ' + error.message);
        })
        .finally(() => {
            regenerateBtn.disabled = false;
            regenerateBtn.title = originalTitle;
            regenerateBtn.style.opacity = '';
        });
    });
});
</script>

<?php
try {
    $content = ob_get_clean();
    if ($content === false) {
        error_log("publish_now view: Failed to get buffer content");
        $content = '<div class="alert alert-error">Ошибка при загрузке содержимого</div>';
    }
    
    $layoutPath = __DIR__ . '/../layout.php';
    if (!file_exists($layoutPath)) {
        error_log("publish_now view: Layout file not found: {$layoutPath}");
        http_response_code(500);
        echo "Layout file not found. Please check server logs.";
        exit;
    }
    
    include $layoutPath;
} catch (\Throwable $e) {
    error_log("Templates create_v2 view: Fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    ob_end_clean();
    http_response_code(500);
    echo "Fatal error loading template creation page. Please check server logs.";
    exit;
}
?>
