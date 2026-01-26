<?php
$title = 'Создать расписание';
ob_start();
$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
?>

<h1>Создать расписание</h1>

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

<form method="POST" action="/content-groups/schedules/create" class="schedule-form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    
    <div class="form-group">
        <label for="content_group_id">Группа контента *</label>
        <select id="content_group_id" name="content_group_id" required>
            <option value="">Выберите группу</option>
            <?php foreach ($groups as $group): ?>
                <option value="<?= $group['id'] ?>" <?= ($selectedGroupId === (int)$group['id']) ? 'selected' : '' ?>>
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
        <label>Режим времени</label>
        <select id="fixed_time_mode" name="fixed_time_mode" onchange="toggleFixedTimeMode()">
            <option value="single">Одна точка времени</option>
            <option value="multiple">Несколько точек в день</option>
        </select>
        
        <div id="single_time_fixed" style="margin-top: 1rem;">
            <label for="publish_at">Дата и время первой публикации *</label>
            <input type="datetime-local" id="publish_at" name="publish_at" required>
        </div>
        
        <div id="multiple_times_fixed" style="display: none; margin-top: 1rem;">
            <label>Точки времени в день *</label>
            <div id="fixed-time-points-container">
                <div class="time-point-item">
                    <input type="time" class="time-point-input" name="daily_time_points[]" placeholder="HH:MM" required>
                    <button type="button" class="btn-remove-time" onclick="removeFixedTimePoint(this)" title="Удалить"><?= \App\Helpers\IconHelper::render('delete', 16) ?></button>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addFixedTimePoint()" style="margin-top: 0.5rem;"><?= \App\Helpers\IconHelper::render('add', 16, 'icon-inline') ?> Добавить время</button>
            <div class="form-group" style="margin-top: 1rem;">
                <label for="fixed_start_date">Начальная дата *</label>
                <input type="date" id="fixed_start_date" name="fixed_start_date">
            </div>
            <div class="form-group">
                <label for="fixed_end_date">Конечная дата (опционально)</label>
                <input type="date" id="fixed_end_date" name="fixed_end_date">
            </div>
            <small>Укажите несколько временных точек для публикации в течение дня. Расписание будет создано для каждой точки на каждый день в указанном диапазоне.</small>
        </div>
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
