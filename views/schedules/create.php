<?php
$title = 'Создать расписание';
ob_start();
?>

<h1>Создать расписание публикации</h1>

<form method="POST" action="/schedules/create" class="schedule-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="video_id">Видео</label>
        <select id="video_id" name="video_id" required>
            <option value="">Выберите видео</option>
            <?php foreach ($videos as $video): ?>
                <option value="<?= $video['id'] ?>" <?= (isset($_GET['video_id']) && $_GET['video_id'] == $video['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($video['title'] ?? $video['file_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="platform">Платформа</label>
        <select id="platform" name="platform" required>
            <option value="youtube">YouTube</option>
            <option value="telegram">Telegram</option>
            <option value="both">Обе платформы</option>
        </select>
    </div>

    <div class="form-group">
        <label for="publish_at">Дата и время публикации</label>
        <input type="datetime-local" id="publish_at" name="publish_at" required>
    </div>

    <div class="form-group">
        <label for="timezone">Часовой пояс</label>
        <input type="text" id="timezone" name="timezone" value="UTC" required>
    </div>

    <div class="form-group">
        <label for="repeat_type">Повтор</label>
        <select id="repeat_type" name="repeat_type">
            <option value="once">Один раз</option>
            <option value="daily">Ежедневно</option>
            <option value="weekly">Еженедельно</option>
            <option value="monthly">Ежемесячно</option>
        </select>
    </div>

    <div class="form-group">
        <label for="repeat_until">Повторять до</label>
        <input type="datetime-local" id="repeat_until" name="repeat_until">
    </div>

    <button type="submit" class="btn btn-primary">Создать расписание</button>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
