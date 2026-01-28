<?php
$title = 'Редактировать расписание';
ob_start();
?>

<div class="page-header">
    <div class="page-header-main">
        <h1 class="page-title">Редактировать расписание публикации</h1>
        <p class="page-subtitle">
            Обновите видео, платформу и временные параметры этого расписания.
        </p>
    </div>
</div>

<form method="POST" action="/schedules/<?= $schedule['id'] ?>/update" class="form-card schedule-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="video_id">Видео</label>
        <select id="video_id" name="video_id" required>
            <option value="">Выберите видео</option>
            <?php foreach ($videos as $video): ?>
                <option value="<?= $video['id'] ?>" <?= ($schedule['video_id'] == $video['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($video['title'] ?? $video['file_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="platform">Платформа</label>
        <select id="platform" name="platform" required>
            <option value="youtube" <?= ($schedule['platform'] === 'youtube') ? 'selected' : '' ?>>YouTube</option>
            <option value="telegram" <?= ($schedule['platform'] === 'telegram') ? 'selected' : '' ?>>Telegram</option>
            <option value="tiktok" <?= ($schedule['platform'] === 'tiktok') ? 'selected' : '' ?>>TikTok</option>
            <option value="instagram" <?= ($schedule['platform'] === 'instagram') ? 'selected' : '' ?>>Instagram Reels</option>
            <option value="pinterest" <?= ($schedule['platform'] === 'pinterest') ? 'selected' : '' ?>>Pinterest (Idea Pins / Video Pins)</option>
            <option value="both" <?= ($schedule['platform'] === 'both') ? 'selected' : '' ?>>YouTube + Telegram</option>
        </select>
    </div>

    <div class="form-group">
        <label for="schedule_type">Тип расписания</label>
        <select id="schedule_type" name="schedule_type" onchange="toggleScheduleType()">
            <option value="single" <?= (empty($schedule['daily_time_points'])) ? 'selected' : '' ?>>Одна публикация</option>
            <option value="daily_points" <?= (!empty($schedule['daily_time_points'])) ? 'selected' : '' ?>>Несколько точек в день</option>
        </select>
    </div>

    <div class="form-group" id="single_time_group" style="<?= (!empty($schedule['daily_time_points'])) ? 'display: none;' : '' ?>">
        <label for="publish_at">Дата и время публикации</label>
        <?php
        $publishAt = $schedule['publish_at'] ?? '';
        $publishAtLocal = $publishAt ? date('Y-m-d\TH:i', strtotime($publishAt)) : '';
        ?>
        <input type="datetime-local" id="publish_at" name="publish_at" value="<?= htmlspecialchars($publishAtLocal) ?>" required>
    </div>

    <div class="form-group" id="daily_points_group" style="<?= (empty($schedule['daily_time_points'])) ? 'display: none;' : '' ?>">
        <label>Точки времени в день</label>
        <div id="time-points-container">
            <?php
            $timePoints = [];
            if (!empty($schedule['daily_time_points'])) {
                $timePoints = json_decode($schedule['daily_time_points'], true) ?: [];
            }
            if (empty($timePoints)) {
                $timePoints = [''];
            }
            foreach ($timePoints as $time): ?>
                <div class="time-point-item">
                    <input type="time" class="time-point-input" name="daily_time_points[]" value="<?= htmlspecialchars($time) ?>" placeholder="HH:MM">
                    <button type="button" class="btn-remove-time" onclick="removeTimePoint(this)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 16) ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" onclick="addTimePoint()" style="margin-top: 0.5rem;"><?= \App\Helpers\IconHelper::render('add', 16, 'icon-inline') ?> Добавить время</button>
        <div class="form-group" style="margin-top: 1rem;">
            <label for="daily_points_start_date">Начальная дата</label>
            <?php
            $startDate = $schedule['daily_points_start_date'] ?? '';
            $startDateLocal = $startDate ? date('Y-m-d', strtotime($startDate)) : '';
            ?>
            <input type="date" id="daily_points_start_date" name="daily_points_start_date" value="<?= htmlspecialchars($startDateLocal) ?>">
        </div>
        <div class="form-group">
            <label for="daily_points_end_date">Конечная дата (опционально)</label>
            <?php
            $endDate = $schedule['daily_points_end_date'] ?? '';
            $endDateLocal = $endDate ? date('Y-m-d', strtotime($endDate)) : '';
            ?>
            <input type="date" id="daily_points_end_date" name="daily_points_end_date" value="<?= htmlspecialchars($endDateLocal) ?>">
        </div>
        <small>Укажите несколько временных точек для публикации в течение дня. Расписание будет создано для каждой точки на каждый день в указанном диапазоне.</small>
    </div>

    <div class="form-group">
        <label for="timezone">Часовой пояс</label>
        <input type="text" id="timezone" name="timezone" value="<?= htmlspecialchars($schedule['timezone'] ?? 'UTC') ?>" required>
    </div>

    <div class="form-group">
        <label for="repeat_type">Тип повторения</label>
        <select id="repeat_type" name="repeat_type">
            <option value="once" <?= ($schedule['repeat_type'] === 'once') ? 'selected' : '' ?>>Один раз</option>
            <option value="daily" <?= ($schedule['repeat_type'] === 'daily') ? 'selected' : '' ?>>Ежедневно</option>
            <option value="weekly" <?= ($schedule['repeat_type'] === 'weekly') ? 'selected' : '' ?>>Еженедельно</option>
            <option value="monthly" <?= ($schedule['repeat_type'] === 'monthly') ? 'selected' : '' ?>>Ежемесячно</option>
        </select>
    </div>

    <div class="form-group" id="repeat_until_group" style="<?= ($schedule['repeat_type'] === 'once') ? 'display: none;' : '' ?>">
        <label for="repeat_until">Повторять до</label>
        <?php
        $repeatUntil = $schedule['repeat_until'] ?? '';
        $repeatUntilLocal = $repeatUntil ? date('Y-m-d\TH:i', strtotime($repeatUntil)) : '';
        ?>
        <input type="datetime-local" id="repeat_until" name="repeat_until" value="<?= htmlspecialchars($repeatUntilLocal) ?>">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="/schedules/<?= $schedule['id'] ?>" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<script>
function toggleScheduleType() {
    const scheduleType = document.getElementById('schedule_type').value;
    const singleGroup = document.getElementById('single_time_group');
    const dailyPointsGroup = document.getElementById('daily_points_group');
    const publishAtInput = document.getElementById('publish_at');
    const timePointsInputs = document.querySelectorAll('.time-point-input');
    const startDateInput = document.getElementById('daily_points_start_date');
    const endDateInput = document.getElementById('daily_points_end_date');
    
    if (scheduleType === 'single') {
        singleGroup.style.display = 'block';
        dailyPointsGroup.style.display = 'none';
        publishAtInput.required = true;
        publishAtInput.disabled = false;
        timePointsInputs.forEach(input => {
            input.required = false;
            input.disabled = true;
        });
        startDateInput.required = false;
        startDateInput.disabled = true;
        endDateInput.disabled = true;
    } else {
        singleGroup.style.display = 'none';
        dailyPointsGroup.style.display = 'block';
        publishAtInput.required = false;
        publishAtInput.disabled = true;
        timePointsInputs.forEach(input => {
            input.required = true;
            input.disabled = false;
        });
        startDateInput.required = true;
        startDateInput.disabled = false;
        endDateInput.disabled = false;
    }
}

function addTimePoint() {
    const container = document.getElementById('time-points-container');
    const newItem = document.createElement('div');
    newItem.className = 'time-point-item';
    newItem.innerHTML = `
        <input type="time" class="time-point-input" name="daily_time_points[]" placeholder="HH:MM">
        <button type="button" class="btn-remove-time" onclick="removeTimePoint(this)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 16) ?></button>
    `;
    container.appendChild(newItem);
    
    // Обновляем required/disabled для новых полей
    const scheduleType = document.getElementById('schedule_type').value;
    if (scheduleType === 'daily_points') {
        newItem.querySelector('.time-point-input').required = true;
        newItem.querySelector('.time-point-input').disabled = false;
    }
}

function removeTimePoint(button) {
    const container = document.getElementById('time-points-container');
    if (container.children.length > 1) {
        button.parentElement.remove();
    } else {
        alert('Должна быть хотя бы одна точка времени');
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    toggleScheduleType();
    
    const repeatType = document.getElementById('repeat_type');
    const repeatUntilGroup = document.getElementById('repeat_until_group');
    
    repeatType.addEventListener('change', function() {
        if (this.value === 'once') {
            repeatUntilGroup.style.display = 'none';
        } else {
            repeatUntilGroup.style.display = 'block';
        }
    });
    
    // Обработка отправки формы - отключаем disabled поля
    document.querySelector('.schedule-form').addEventListener('submit', function(e) {
        const disabledInputs = this.querySelectorAll('input[disabled], select[disabled]');
        disabledInputs.forEach(input => {
            input.disabled = false;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
