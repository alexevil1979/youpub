<?php
$title = 'Создать расписание';
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Создать расписание публикации</h1>
        <p class="page-subtitle">
            Выберите видео, платформу и задайте дату, время или несколько точек публикации.
        </p>
    </div>
</div>

<form method="POST" action="/schedules/create" class="form-card schedule-form">
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
            <option value="tiktok">TikTok</option>
            <option value="instagram">Instagram Reels</option>
            <option value="pinterest">Pinterest (Idea Pins / Video Pins)</option>
            <option value="both">YouTube + Telegram</option>
        </select>
    </div>

    <div class="form-group">
        <label for="schedule_type">Тип расписания</label>
        <select id="schedule_type" name="schedule_type" onchange="toggleScheduleType()">
            <option value="single">Одна публикация</option>
            <option value="daily_points">Несколько точек в день</option>
        </select>
    </div>

    <div class="form-group" id="single_time_group">
        <label for="publish_at">Дата и время публикации</label>
        <input type="datetime-local" id="publish_at" name="publish_at">
    </div>

    <div class="form-group" id="daily_points_group" style="display: none;">
        <label>Точки времени в день</label>
        <div id="time-points-container">
            <div class="time-point-item">
                <input type="time" class="time-point-input" name="daily_time_points[]" placeholder="HH:MM">
                <button type="button" class="btn-remove-time" onclick="removeTimePoint(this)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 16) ?></button>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" onclick="addTimePoint()" style="margin-top: 0.5rem;"><?= \App\Helpers\IconHelper::render('add', 16, 'icon-inline') ?> Добавить время</button>
        <div class="form-group" style="margin-top: 1rem;">
            <label for="daily_points_start_date">Начальная дата</label>
            <input type="date" id="daily_points_start_date" name="daily_points_start_date">
        </div>
        <div class="form-group">
            <label for="daily_points_end_date">Конечная дата (опционально)</label>
            <input type="date" id="daily_points_end_date" name="daily_points_end_date">
        </div>
        <small>Укажите несколько временных точек для публикации в течение дня. Расписание будет создано для каждой точки на каждый день в указанном диапазоне.</small>
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

<script>
function toggleScheduleType() {
    const type = document.getElementById('schedule_type').value;
    const singleGroup = document.getElementById('single_time_group');
    const dailyPointsGroup = document.getElementById('daily_points_group');
    const publishAtInput = document.getElementById('publish_at');
    
    if (type === 'daily_points') {
        singleGroup.style.display = 'none';
        dailyPointsGroup.style.display = 'block';
        publishAtInput.removeAttribute('required');
    } else {
        singleGroup.style.display = 'block';
        dailyPointsGroup.style.display = 'none';
        publishAtInput.setAttribute('required', 'required');
    }
}

function addTimePoint() {
    const container = document.getElementById('time-points-container');
    const newItem = document.createElement('div');
    newItem.className = 'time-point-item';
    const deleteIcon = '<?= str_replace("'", "\\'", \App\Helpers\IconHelper::render('delete', 16)) ?>';
    newItem.innerHTML = `
        <input type="time" class="time-point-input" name="daily_time_points[]" placeholder="HH:MM">
        <button type="button" class="btn-remove-time" onclick="removeTimePoint(this)" title="Удалить">${deleteIcon}</button>
    `;
    container.appendChild(newItem);
}

function removeTimePoint(btn) {
    const container = document.getElementById('time-points-container');
    if (container.children.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('Должна быть хотя бы одна точка времени');
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    toggleScheduleType();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
