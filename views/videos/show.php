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
            alert('Видео успешно опубликовано!');
            window.location.reload();
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
