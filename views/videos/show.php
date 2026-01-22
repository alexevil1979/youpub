<?php
$title = 'Просмотр видео';
ob_start();
?>

<h1><?= htmlspecialchars($video['title']) ?></h1>

<div class="video-container">
    <div class="video-player">
        <?php
        // Формируем правильный путь к видео для веб-доступа
        // file_path содержит полный путь на сервере, нужно преобразовать в веб-путь
        $filePath = $video['file_path'];
        $basePath = '/ssd/www/youpub';
        
        // Убираем базовый путь и заменяем на веб-путь
        if (strpos($filePath, $basePath) === 0) {
            $videoPath = str_replace($basePath, '', $filePath);
            $videoPath = str_replace('\\', '/', $videoPath);
        } else {
            // Если путь относительный или другой формат, формируем из структуры
            $videoPath = '/storage/uploads/' . $video['user_id'] . '/' . basename($filePath);
        }
        
        // Убеждаемся, что путь начинается с /
        if (strpos($videoPath, '/') !== 0) {
            $videoPath = '/' . $videoPath;
        }
        ?>
        <video controls width="100%" style="max-width: 800px;">
            <source src="<?= htmlspecialchars($videoPath) ?>" type="<?= htmlspecialchars($video['mime_type']) ?>">
            Ваш браузер не поддерживает видео.
        </video>
    </div>

    <div class="video-info">
        <div class="video-meta">
            <p><strong>Описание:</strong></p>
            <p><?= nl2br(htmlspecialchars($video['description'] ?: 'Нет описания')) ?></p>
        </div>

        <?php if ($video['tags']): ?>
        <div class="video-tags">
            <p><strong>Теги:</strong></p>
            <p>
                <?php
                $tags = explode(',', $video['tags']);
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if ($tag) {
                        echo '<span class="tag">' . htmlspecialchars($tag) . '</span> ';
                    }
                }
                ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="video-details">
            <p><strong>Размер файла:</strong> <?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB</p>
            <p><strong>Статус:</strong> <?= htmlspecialchars($video['status']) ?></p>
            <p><strong>Загружено:</strong> <?= date('d.m.Y H:i', strtotime($video['created_at'])) ?></p>
        </div>

        <div class="video-publications" id="video-publications" style="<?= empty($publications) ? 'display: none;' : '' ?>">
            <h3>Опубликовано на платформах:</h3>
            <div class="publications-list" id="publications-list">
                <?php if (!empty($publications)): ?>
                    <?php foreach ($publications as $publication): ?>
                        <?php include __DIR__ . '/_publication_item.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="video-actions">
            <a href="/videos" class="btn btn-secondary">Назад к списку</a>
            <button type="button" class="btn btn-success" onclick="publishNow(<?= $video['id'] ?>)">Опубликовать сейчас</button>
            <a href="/videos/<?= $video['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
            <button type="button" class="btn btn-danger" onclick="deleteVideo(<?= $video['id'] ?>)">Удалить</button>
        </div>
    </div>
</div>

<script>
function deleteVideo(id) {
    if (confirm('Вы уверены, что хотите удалить это видео?')) {
        fetch('/videos/' + id, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/videos';
            } else {
                alert('Ошибка: ' + (data.message || 'Не удалось удалить видео'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при удалении видео');
        });
    }
}

function publishNow(id) {
    if (!confirm('Опубликовать видео сейчас на все подключенные платформы?')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Публикация...';
    
    fetch('/videos/' + id + '/publish', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = originalText;
        
        if (data.success) {
            // Добавляем новые публикации без перезагрузки страницы
            if (data.data && data.data.publications) {
                addPublications(data.data.publications);
            }
            
            // Показываем уведомление
            showNotification('Видео успешно опубликовано!', 'success');
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось опубликовать видео'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.textContent = originalText;
        alert('Произошла ошибка при публикации видео');
    });
}

function addPublications(publications) {
    const publicationsList = document.getElementById('publications-list');
    const publicationsContainer = document.getElementById('video-publications');
    
    if (!publicationsList || !publicationsContainer) {
        return;
    }
    
    // Получаем существующие платформы
    const existingPlatforms = new Set();
    publicationsList.querySelectorAll('.publication-item').forEach(item => {
        existingPlatforms.add(item.getAttribute('data-platform'));
    });
    
    // Добавляем новые публикации
    publications.forEach(publication => {
        // Пропускаем, если уже есть публикация для этой платформы
        if (existingPlatforms.has(publication.platform)) {
            return;
        }
        
        // Формируем URL
        let url = publication.platform_url || '';
        if (!url && publication.platform_id) {
            switch (publication.platform) {
                case 'youtube':
                    url = 'https://youtube.com/watch?v=' + publication.platform_id;
                    break;
                case 'telegram':
                    url = 'https://t.me/' + publication.platform_id;
                    break;
            }
        }
        
        // Форматируем дату
        let dateStr = '';
        if (publication.published_at) {
            const date = new Date(publication.published_at);
            dateStr = date.toLocaleDateString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Создаем элемент
        const item = document.createElement('div');
        item.className = 'publication-item';
        item.setAttribute('data-platform', publication.platform);
        
        let html = '<div class="publication-platform">';
        html += '<strong>' + capitalizeFirst(publication.platform) + '</strong>';
        if (dateStr) {
            html += '<span class="publication-date">' + dateStr + '</span>';
        }
        html += '</div>';
        
        if (url) {
            html += '<a href="' + escapeHtml(url) + '" target="_blank" class="btn btn-primary btn-sm">';
            html += 'Открыть на ' + capitalizeFirst(publication.platform);
            html += '</a>';
        }
        
        item.innerHTML = html;
        
        // Добавляем с анимацией
        item.style.opacity = '0';
        publicationsList.appendChild(item);
        
        // Показываем контейнер
        publicationsContainer.style.display = 'block';
        
        // Анимация появления
        setTimeout(() => {
            item.style.transition = 'opacity 0.3s';
            item.style.opacity = '1';
        }, 10);
        
        existingPlatforms.add(publication.platform);
    });
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type) {
    // Создаем элемент уведомления
    const notification = document.createElement('div');
    notification.className = 'alert alert-' + (type === 'success' ? 'success' : 'error');
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '10000';
    notification.style.minWidth = '300px';
    
    document.body.appendChild(notification);
    
    // Удаляем через 3 секунды
    setTimeout(() => {
        notification.style.transition = 'opacity 0.3s';
        notification.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
