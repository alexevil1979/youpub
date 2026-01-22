<?php
$title = 'Создать умное расписание';
ob_start();
?>

<h1>Создать умное расписание</h1>

<form method="POST" action="/content-groups/schedules/create" class="schedule-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="content_group_id">Группа контента *</label>
        <select id="content_group_id" name="content_group_id" required>
            <option value="">Выберите группу</option>
            <?php foreach ($groups as $group): ?>
                <option value="<?= $group['id'] ?>" <?= (isset($_GET['group_id']) && $_GET['group_id'] == $group['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($group['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Или <a href="/content-groups/create">создайте новую группу</a></small>
    </div>

    <div class="form-group">
        <label for="platform">Платформа *</label>
        <select id="platform" name="platform" required>
            <option value="youtube">YouTube</option>
            <option value="telegram">Telegram</option>
            <option value="both">YouTube + Telegram</option>
        </select>
    </div>

    <div class="form-group">
        <label for="template_id">Шаблон оформления (опционально)</label>
        <select id="template_id" name="template_id">
            <option value="">Без шаблона</option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="schedule_type">Тип расписания *</label>
        <select id="schedule_type" name="schedule_type" required onchange="toggleScheduleOptions()">
            <option value="fixed">Фиксированное (каждый день в определенное время)</option>
            <option value="interval">Интервальное (каждые N минут)</option>
            <option value="batch">Пакетное (X видео за Y часов)</option>
            <option value="random">Случайное (рандом в пределах окна)</option>
            <option value="wave">Волновое (активные и тихие периоды)</option>
        </select>
    </div>

    <div class="form-group" id="fixed_options">
        <label for="publish_at">Дата и время первой публикации *</label>
        <input type="datetime-local" id="publish_at" name="publish_at" required>
    </div>

    <div class="form-group" id="interval_options" style="display: none;">
        <label for="interval_minutes">Интервал (минуты) *</label>
        <input type="number" id="interval_minutes" name="interval_minutes" min="1" placeholder="30">
        <small>Например: 30 (каждые 30 минут)</small>
    </div>

    <div class="form-group" id="batch_options" style="display: none;">
        <label for="batch_count">Количество видео *</label>
        <input type="number" id="batch_count" name="batch_count" min="1" placeholder="5">
        <label for="batch_window_hours" style="margin-top: 0.5rem; display: block;">Окно (часы) *</label>
        <input type="number" id="batch_window_hours" name="batch_window_hours" min="1" placeholder="2">
        <small>Например: 5 видео за 2 часа</small>
    </div>

    <div class="form-group" id="random_options" style="display: none;">
        <label for="random_window_start">Начало окна *</label>
        <input type="time" id="random_window_start" name="random_window_start">
        <label for="random_window_end" style="margin-top: 0.5rem; display: block;">Конец окна *</label>
        <input type="time" id="random_window_end" name="random_window_end">
        <small>Например: с 10:00 до 18:00</small>
    </div>

    <div class="form-group">
        <label for="weekdays">Дни недели (опционально)</label>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <label><input type="checkbox" name="weekdays[]" value="1"> Пн</label>
            <label><input type="checkbox" name="weekdays[]" value="2"> Вт</label>
            <label><input type="checkbox" name="weekdays[]" value="3"> Ср</label>
            <label><input type="checkbox" name="weekdays[]" value="4"> Чт</label>
            <label><input type="checkbox" name="weekdays[]" value="5"> Пт</label>
            <label><input type="checkbox" name="weekdays[]" value="6"> Сб</label>
            <label><input type="checkbox" name="weekdays[]" value="7"> Вс</label>
        </div>
        <small>Если не выбрано, публикация каждый день</small>
    </div>

    <div class="form-group">
        <label for="active_hours_start">Начало активных часов (опционально)</label>
        <input type="time" id="active_hours_start" name="active_hours_start">
        <label for="active_hours_end" style="margin-top: 0.5rem; display: block;">Конец активных часов (опционально)</label>
        <input type="time" id="active_hours_end" name="active_hours_end">
    </div>

    <div class="form-group">
        <label for="daily_limit">Дневной лимит (опционально)</label>
        <input type="number" id="daily_limit" name="daily_limit" min="1" placeholder="10">
        <small>Максимальное количество видео в день</small>
    </div>

    <div class="form-group">
        <label for="hourly_limit">Часовой лимит (опционально)</label>
        <input type="number" id="hourly_limit" name="hourly_limit" min="1" placeholder="2">
        <small>Максимальное количество видео в час</small>
    </div>

    <div class="form-group">
        <label for="delay_between_posts">Задержка между публикациями (минуты, опционально)</label>
        <input type="number" id="delay_between_posts" name="delay_between_posts" min="1" placeholder="30">
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="skip_published" value="1" checked> Пропускать уже опубликованные видео
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Создать расписание</button>
        <a href="/schedules" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<script>
function toggleScheduleOptions() {
    const type = document.getElementById('schedule_type').value;
    
    // Скрываем все опции
    document.getElementById('fixed_options').style.display = 'none';
    document.getElementById('interval_options').style.display = 'none';
    document.getElementById('batch_options').style.display = 'none';
    document.getElementById('random_options').style.display = 'none';
    
    // Показываем нужные опции
    if (type === 'fixed') {
        document.getElementById('fixed_options').style.display = 'block';
    } else if (type === 'interval') {
        document.getElementById('interval_options').style.display = 'block';
    } else if (type === 'batch') {
        document.getElementById('batch_options').style.display = 'block';
    } else if (type === 'random') {
        document.getElementById('random_options').style.display = 'block';
    }
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    toggleScheduleOptions();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
