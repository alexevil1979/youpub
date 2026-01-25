<?php
$title = 'Редактировать расписание';
ob_start();
?>

<h1>Редактировать расписание</h1>

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

<form method="POST" action="/content-groups/schedules/<?= (int)$schedule['id'] ?>/edit" class="schedule-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="content_group_id">Группа контента *</label>
        <select id="content_group_id" name="content_group_id" required>
            <option value="">Выберите группу</option>
            <?php foreach ($groups as $group): ?>
                <option value="<?= $group['id'] ?>" <?= (isset($schedule['content_group_id']) && $schedule['content_group_id'] == $group['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($group['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="platform">Платформа *</label>
        <select id="platform" name="platform" required>
            <option value="youtube" <?= (isset($schedule['platform']) && $schedule['platform'] === 'youtube') ? 'selected' : '' ?>>YouTube</option>
            <option value="telegram" <?= (isset($schedule['platform']) && $schedule['platform'] === 'telegram') ? 'selected' : '' ?>>Telegram</option>
            <option value="both" <?= (isset($schedule['platform']) && $schedule['platform'] === 'both') ? 'selected' : '' ?>>YouTube + Telegram</option>
        </select>
    </div>

    <div class="form-group">
        <label for="template_id">Шаблон оформления (опционально)</label>
        <select id="template_id" name="template_id">
            <option value="">Без шаблона</option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= $template['id'] ?>" <?= (isset($schedule['template_id']) && $schedule['template_id'] == $template['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($template['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="schedule_type">Тип расписания *</label>
        <select id="schedule_type" name="schedule_type" required onchange="toggleScheduleOptions()">
            <option value="fixed" <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'fixed') ? 'selected' : '' ?>>Фиксированное (каждый день в определенное время)</option>
            <option value="interval" <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'interval') ? 'selected' : '' ?>>Интервальное (каждые N минут)</option>
            <option value="batch" <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'batch') ? 'selected' : '' ?>>Пакетное (X видео за Y часов)</option>
            <option value="random" <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'random') ? 'selected' : '' ?>>Случайное (рандом в пределах окна)</option>
            <option value="wave" <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'wave') ? 'selected' : '' ?>>Волновое (активные и тихие периоды)</option>
        </select>
    </div>

    <div class="form-group" id="fixed_options" style="<?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'fixed') ? '' : 'display: none;' ?>">
        <label>Режим времени</label>
        <select id="fixed_time_mode" name="fixed_time_mode" onchange="toggleFixedTimeMode()">
            <option value="single" <?= (empty($schedule['daily_time_points'])) ? 'selected' : '' ?>>Одна точка времени</option>
            <option value="multiple" <?= (!empty($schedule['daily_time_points'])) ? 'selected' : '' ?>>Несколько точек в день</option>
        </select>
        
        <div id="single_time_fixed" style="margin-top: 1rem; <?= (!empty($schedule['daily_time_points'])) ? 'display: none;' : '' ?>">
            <label for="publish_at">Дата и время первой публикации *</label>
            <input type="datetime-local" id="publish_at" name="publish_at" value="<?= isset($schedule['publish_at']) ? date('Y-m-d\TH:i', strtotime($schedule['publish_at'])) : '' ?>" required>
        </div>
        
        <div id="multiple_times_fixed" style="display: <?= (!empty($schedule['daily_time_points'])) ? 'block' : 'none'; ?>; margin-top: 1rem;">
            <label>Точки времени в день *</label>
            <div id="fixed-time-points-container">
                <?php 
                $timePoints = [];
                if (!empty($schedule['daily_time_points'])) {
                    $timePoints = json_decode($schedule['daily_time_points'], true) ?: [];
                }
                if (empty($timePoints)) {
                    $timePoints = ['00:00'];
                }
                foreach ($timePoints as $timePoint): 
                ?>
                    <div class="time-point-item">
                        <input type="time" class="time-point-input" name="daily_time_points[]" value="<?= htmlspecialchars($timePoint) ?>" placeholder="HH:MM" required>
                        <button type="button" class="btn-remove-time" onclick="removeFixedTimePoint(this)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 16) ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addFixedTimePoint()" style="margin-top: 0.5rem;"><?= \App\Helpers\IconHelper::render('add', 16, 'icon-inline') ?> Добавить время</button>
            <div class="form-group" style="margin-top: 1rem;">
                <label for="fixed_start_date">Начальная дата *</label>
                <input type="date" id="fixed_start_date" name="fixed_start_date" value="<?= isset($schedule['daily_points_start_date']) ? date('Y-m-d', strtotime($schedule['daily_points_start_date'])) : '' ?>">
            </div>
            <div class="form-group">
                <label for="fixed_end_date">Конечная дата (опционально)</label>
                <input type="date" id="fixed_end_date" name="fixed_end_date" value="<?= isset($schedule['daily_points_end_date']) ? date('Y-m-d', strtotime($schedule['daily_points_end_date'])) : '' ?>">
            </div>
        </div>
    </div>

    <div class="form-group" id="interval_options" style="display: <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'interval') ? 'block' : 'none'; ?>;">
        <label for="interval_minutes">Интервал (минуты) *</label>
        <input type="number" id="interval_minutes" name="interval_minutes" min="1" value="<?= isset($schedule['interval_minutes']) ? (int)$schedule['interval_minutes'] : '' ?>" placeholder="30">
        <small>Например: 30 (каждые 30 минут)</small>
    </div>

    <div class="form-group" id="batch_options" style="display: <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'batch') ? 'block' : 'none'; ?>;">
        <label for="batch_count">Количество видео *</label>
        <input type="number" id="batch_count" name="batch_count" min="1" value="<?= isset($schedule['batch_count']) ? (int)$schedule['batch_count'] : '' ?>" placeholder="5">
        <label for="batch_window_hours" style="margin-top: 0.5rem; display: block;">Окно (часы) *</label>
        <input type="number" id="batch_window_hours" name="batch_window_hours" min="1" value="<?= isset($schedule['batch_window_hours']) ? (int)$schedule['batch_window_hours'] : '' ?>" placeholder="2">
        <small>Например: 5 видео за 2 часа</small>
    </div>

    <div class="form-group" id="random_options" style="display: <?= (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'random') ? 'block' : 'none'; ?>;">
        <label for="random_window_start">Начало окна *</label>
        <input type="time" id="random_window_start" name="random_window_start" value="<?= isset($schedule['random_window_start']) ? htmlspecialchars($schedule['random_window_start']) : '' ?>">
        <label for="random_window_end" style="margin-top: 0.5rem; display: block;">Конец окна *</label>
        <input type="time" id="random_window_end" name="random_window_end" value="<?= isset($schedule['random_window_end']) ? htmlspecialchars($schedule['random_window_end']) : '' ?>">
        <small>Например: с 10:00 до 18:00</small>
    </div>

    <div class="form-group">
        <label for="weekdays">Дни недели (опционально)</label>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <?php 
            $selectedWeekdays = [];
            if (!empty($schedule['weekdays'])) {
                $selectedWeekdays = explode(',', $schedule['weekdays']);
            }
            ?>
            <label><input type="checkbox" name="weekdays[]" value="1" <?= in_array('1', $selectedWeekdays) ? 'checked' : '' ?>> Пн</label>
            <label><input type="checkbox" name="weekdays[]" value="2" <?= in_array('2', $selectedWeekdays) ? 'checked' : '' ?>> Вт</label>
            <label><input type="checkbox" name="weekdays[]" value="3" <?= in_array('3', $selectedWeekdays) ? 'checked' : '' ?>> Ср</label>
            <label><input type="checkbox" name="weekdays[]" value="4" <?= in_array('4', $selectedWeekdays) ? 'checked' : '' ?>> Чт</label>
            <label><input type="checkbox" name="weekdays[]" value="5" <?= in_array('5', $selectedWeekdays) ? 'checked' : '' ?>> Пт</label>
            <label><input type="checkbox" name="weekdays[]" value="6" <?= in_array('6', $selectedWeekdays) ? 'checked' : '' ?>> Сб</label>
            <label><input type="checkbox" name="weekdays[]" value="7" <?= in_array('7', $selectedWeekdays) ? 'checked' : '' ?>> Вс</label>
        </div>
        <small>Если не выбрано, публикация каждый день</small>
    </div>

    <div class="form-group">
        <label for="active_hours_start">Начало активных часов (опционально)</label>
        <input type="time" id="active_hours_start" name="active_hours_start" value="<?= isset($schedule['active_hours_start']) ? htmlspecialchars($schedule['active_hours_start']) : '' ?>">
        <label for="active_hours_end" style="margin-top: 0.5rem; display: block;">Конец активных часов (опционально)</label>
        <input type="time" id="active_hours_end" name="active_hours_end" value="<?= isset($schedule['active_hours_end']) ? htmlspecialchars($schedule['active_hours_end']) : '' ?>">
    </div>

    <div class="form-group">
        <label for="daily_limit">Дневной лимит (опционально)</label>
        <input type="number" id="daily_limit" name="daily_limit" min="1" value="<?= isset($schedule['daily_limit']) ? (int)$schedule['daily_limit'] : '' ?>" placeholder="10">
        <small>Максимальное количество видео в день</small>
    </div>

    <div class="form-group">
        <label for="hourly_limit">Часовой лимит (опционально)</label>
        <input type="number" id="hourly_limit" name="hourly_limit" min="1" value="<?= isset($schedule['hourly_limit']) ? (int)$schedule['hourly_limit'] : '' ?>" placeholder="2">
        <small>Максимальное количество видео в час</small>
    </div>

    <div class="form-group">
        <label for="delay_between_posts">Задержка между публикациями (минуты, опционально)</label>
        <input type="number" id="delay_between_posts" name="delay_between_posts" min="1" value="<?= isset($schedule['delay_between_posts']) ? (int)$schedule['delay_between_posts'] : '' ?>" placeholder="30">
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="skip_published" value="1" <?= (isset($schedule['skip_published']) && $schedule['skip_published']) ? 'checked' : '' ?>> Пропускать уже опубликованные видео
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<script>
function toggleScheduleOptions() {
    const type = document.getElementById('schedule_type').value;
    const publishAtInput = document.getElementById('publish_at');
    const timePointInputs = document.querySelectorAll('.time-point-input');
    
    // Скрываем все опции и убираем required у всех полей
    document.getElementById('fixed_options').style.display = 'none';
    document.getElementById('interval_options').style.display = 'none';
    document.getElementById('batch_options').style.display = 'none';
    document.getElementById('random_options').style.display = 'none';
    
    publishAtInput.removeAttribute('required');
    publishAtInput.disabled = true;
    timePointInputs.forEach(input => {
        input.removeAttribute('required');
        input.disabled = true;
    });
    
    // Показываем нужные опции
    if (type === 'fixed') {
        document.getElementById('fixed_options').style.display = 'block';
        toggleFixedTimeMode(); // Управляем required в зависимости от режима
    } else if (type === 'interval') {
        document.getElementById('interval_options').style.display = 'block';
        const intervalInput = document.getElementById('interval_minutes');
        if (intervalInput) {
            intervalInput.setAttribute('required', 'required');
            intervalInput.disabled = false;
        }
    } else if (type === 'batch') {
        document.getElementById('batch_options').style.display = 'block';
        const batchCount = document.getElementById('batch_count');
        const batchWindow = document.getElementById('batch_window_hours');
        if (batchCount) {
            batchCount.setAttribute('required', 'required');
            batchCount.disabled = false;
        }
        if (batchWindow) {
            batchWindow.setAttribute('required', 'required');
            batchWindow.disabled = false;
        }
    } else if (type === 'random') {
        document.getElementById('random_options').style.display = 'block';
        const randomStart = document.getElementById('random_window_start');
        const randomEnd = document.getElementById('random_window_end');
        if (randomStart) {
            randomStart.setAttribute('required', 'required');
            randomStart.disabled = false;
        }
        if (randomEnd) {
            randomEnd.setAttribute('required', 'required');
            randomEnd.disabled = false;
        }
    }
}

function toggleFixedTimeMode() {
    const mode = document.getElementById('fixed_time_mode').value;
    const singleTime = document.getElementById('single_time_fixed');
    const multipleTimes = document.getElementById('multiple_times_fixed');
    const publishAtInput = document.getElementById('publish_at');
    const timePointInputs = document.querySelectorAll('.time-point-input');
    
    if (mode === 'multiple') {
        singleTime.style.display = 'none';
        multipleTimes.style.display = 'block';
        // Убираем required у скрытого поля publish_at
        publishAtInput.removeAttribute('required');
        publishAtInput.disabled = true;
        // Добавляем required к видимым полям времени
        timePointInputs.forEach(input => {
            input.setAttribute('required', 'required');
            input.disabled = false;
        });
    } else {
        singleTime.style.display = 'block';
        multipleTimes.style.display = 'none';
        // Добавляем required к видимому полю publish_at
        publishAtInput.setAttribute('required', 'required');
        publishAtInput.disabled = false;
        // Убираем required у скрытых полей времени
        timePointInputs.forEach(input => {
            input.removeAttribute('required');
            input.disabled = true;
        });
    }
}

function addFixedTimePoint() {
    const container = document.getElementById('fixed-time-points-container');
    const newItem = document.createElement('div');
    newItem.className = 'time-point-item';
    const deleteIcon = '<?= str_replace("'", "\\'", \App\Helpers\IconHelper::render('delete', 16)) ?>';
    newItem.innerHTML = `
        <input type="time" class="time-point-input" name="daily_time_points[]" placeholder="HH:MM" required>
        <button type="button" class="btn-remove-time" onclick="removeFixedTimePoint(this)" title="Удалить">${deleteIcon}</button>
    `;
    container.appendChild(newItem);
}

function removeFixedTimePoint(btn) {
    const container = document.getElementById('fixed-time-points-container');
    if (container.children.length > 1) {
        btn.parentElement.remove();
    } else {
        alert('Должна быть хотя бы одна точка времени');
    }
}

// Обработчик отправки формы - убираем required у скрытых полей
document.querySelector('.schedule-form').addEventListener('submit', function(e) {
    // Убираем required у всех скрытых полей
    const form = this;
    const allInputs = form.querySelectorAll('input, select, textarea');
    allInputs.forEach(input => {
        const parent = input.closest('div');
        if (parent && parent.style.display === 'none') {
            input.removeAttribute('required');
            input.disabled = true;
        }
    });
    
    // Убираем required у publish_at если режим multiple
    const fixedMode = document.getElementById('fixed_time_mode');
    if (fixedMode && fixedMode.value === 'multiple') {
        const publishAt = document.getElementById('publish_at');
        if (publishAt) {
            publishAt.removeAttribute('required');
            publishAt.disabled = true;
        }
    }
    
    // Убираем required у daily_time_points если режим single
    if (fixedMode && fixedMode.value === 'single') {
        const timePoints = document.querySelectorAll('.time-point-input');
        timePoints.forEach(input => {
            input.removeAttribute('required');
            input.disabled = true;
        });
    }
});

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    toggleScheduleOptions();
    toggleFixedTimeMode();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
