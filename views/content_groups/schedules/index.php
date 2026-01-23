<?php
$title = 'Умные расписания';
ob_start();
?>

<h1>Умные расписания</h1>

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

<a href="/content-groups/schedules/create" class="btn btn-primary">Создать умное расписание</a>

<?php 
// Убеждаемся, что переменные определены
if (!isset($smartSchedules)) {
    $smartSchedules = [];
}
if (!isset($groups)) {
    $groups = [];
}
?>

<?php if (empty($smartSchedules)): ?>
    <p style="margin-top: 2rem;">Нет созданных умных расписаний. <a href="/content-groups/schedules/create">Создать расписание</a></p>
<?php else: ?>
    <div style="margin-top: 2rem;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Группа</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Платформа</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Тип</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Следующая публикация</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Следующие публикации</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Статус</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($smartSchedules as $schedule): 
                    $groupId = isset($schedule['content_group_id']) ? (int)$schedule['content_group_id'] : 0;
                    $group = isset($groups[$groupId]) ? $groups[$groupId] : null;
                    $scheduleTypeNames = [
                        'fixed' => 'Фиксированное',
                        'interval' => 'Интервальное',
                        'batch' => 'Пакетное',
                        'random' => 'Случайное',
                        'wave' => 'Волновое'
                    ];
                    $scheduleType = isset($schedule['schedule_type']) && isset($scheduleTypeNames[$schedule['schedule_type']]) 
                        ? $scheduleTypeNames[$schedule['schedule_type']] 
                        : ($schedule['schedule_type'] ?? 'Неизвестно');
                ?>
                    <tr style="border-bottom: 1px solid #dee2e6;" data-publish-at="<?= $nextPublishAt ? date('Y-m-d H:i:s', $nextPublishAt) : '' ?>" data-status="<?= $schedule['status'] ?? '' ?>">
                        <td style="padding: 0.75rem;">
                            <?php if ($group && isset($group['id']) && isset($group['name'])): ?>
                                <a href="/content-groups/<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
                            <?php else: ?>
                                <span style="color: #95a5a6;">Группа не найдена (ID: <?= $groupId ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-info"><?= isset($schedule['platform']) ? ucfirst($schedule['platform']) : 'Неизвестно' ?></span>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?= htmlspecialchars($scheduleType) ?>
                            <?php if (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'interval' && isset($schedule['interval_minutes']) && $schedule['interval_minutes']): ?>
                                <br><small style="color: #95a5a6;">Каждые <?= (int)$schedule['interval_minutes'] ?> мин.</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php 
                            // Для интервальных расписаний вычисляем следующее время публикации
                            $nextPublishAt = null;
                            $overdueReason = null;
                            
                            if (isset($schedule['schedule_type']) && $schedule['schedule_type'] === 'interval' && !empty($schedule['interval_minutes'])) {
                                $baseTime = strtotime($schedule['publish_at'] ?? 'now');
                                $interval = (int)$schedule['interval_minutes'] * 60;
                                $now = time();
                                
                                // Вычисляем следующее время публикации
                                if ($baseTime <= $now) {
                                    // Если базовое время прошло, вычисляем следующее
                                    $elapsed = $now - $baseTime;
                                    $intervalsPassed = floor($elapsed / $interval);
                                    $nextPublishAt = $baseTime + (($intervalsPassed + 1) * $interval);
                                } else {
                                    $nextPublishAt = $baseTime;
                                }
                            } elseif (isset($schedule['publish_at']) && $schedule['publish_at']) {
                                $nextPublishAt = strtotime($schedule['publish_at']);
                            }
                            
                            // Определяем причину просрочки, если время прошло
                            if ($nextPublishAt !== null):
                                $now = time();
                                if ($nextPublishAt <= $now):
                                    // Время прошло, определяем причину
                                    $reasons = [];
                                    
                                    // Проверяем статус расписания
                                    if (isset($schedule['status']) && $schedule['status'] === 'paused') {
                                        $reasons[] = 'Расписание на паузе';
                                    }
                                    
                                    // Проверяем группу
                                    if ($group) {
                                        if (isset($group['status']) && $group['status'] !== 'active') {
                                            $reasons[] = 'Группа неактивна';
                                        }
                                        
                                        // Проверяем наличие доступных видео
                                        try {
                                            $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
                                            $nextFile = $fileRepo->findNextUnpublished((int)$group['id']);
                                            if (!$nextFile) {
                                                $reasons[] = 'Нет доступных видео';
                                            }
                                        } catch (\Exception $e) {
                                            error_log("Error checking files: " . $e->getMessage());
                                            $reasons[] = 'Ошибка проверки видео';
                                        }
                                        
                                        // Проверяем подключенные интеграции
                                        try {
                                            $platform = $schedule['platform'] ?? 'youtube';
                                            $integrationRepo = null;
                                            
                                            switch ($platform) {
                                                case 'youtube':
                                                    $integrationRepo = new \App\Repositories\YoutubeIntegrationRepository();
                                                    break;
                                                case 'telegram':
                                                    $integrationRepo = new \App\Repositories\TelegramIntegrationRepository();
                                                    break;
                                                case 'tiktok':
                                                    $integrationRepo = new \App\Repositories\TiktokIntegrationRepository();
                                                    break;
                                                case 'instagram':
                                                    $integrationRepo = new \App\Repositories\InstagramIntegrationRepository();
                                                    break;
                                                case 'pinterest':
                                                    $integrationRepo = new \App\Repositories\PinterestIntegrationRepository();
                                                    break;
                                            }
                                            
                                            if ($integrationRepo) {
                                                $integration = $integrationRepo->findDefaultByUserId($schedule['user_id'] ?? 0);
                                                if (!$integration || ($integration['status'] ?? '') !== 'connected') {
                                                    $reasons[] = 'Интеграция не подключена';
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            error_log("Error checking integration: " . $e->getMessage());
                                        }
                                    } else {
                                        $reasons[] = 'Группа не найдена';
                                    }
                                    
                                    // Если нет специфических причин, указываем общую
                                    if (empty($reasons)) {
                                        $reasons[] = 'Время публикации прошло';
                                    }
                                    
                                    $overdueReason = implode(', ', $reasons);
                            ?>
                                    <div>
                                        <span style="color: #e74c3c; font-weight: 500;">Просрочено</span>
                                        <?php if ($overdueReason): ?>
                                            <br><small style="color: #e74c3c; font-size: 0.75rem;"><?= htmlspecialchars($overdueReason) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <div style="color: #3498db; font-weight: 500;">
                                            <?= date('d.m.Y H:i', $nextPublishAt) ?>
                                        </div>
                                        <?php if (isset($schedule['status']) && $schedule['status'] === 'pending'): ?>
                                            <div class="countdown-timer" 
                                                 data-publish-at="<?= date('Y-m-d H:i:s', $nextPublishAt) ?>" 
                                                 data-overdue-reason="<?= htmlspecialchars($overdueReason ?? '', ENT_QUOTES) ?>"
                                                 style="margin-top: 0.5rem; font-size: 0.85rem; color: #3498db; font-weight: 500;">
                                                <span class="countdown-text">Осталось: </span>
                                                <span class="countdown-value">-</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #95a5a6;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php 
                            $scheduleId = (int)($schedule['id'] ?? 0);
                            $publications = isset($nextPublications[$scheduleId]) ? $nextPublications[$scheduleId] : [];
                            if (!empty($publications)):
                            ?>
                                <div style="font-size: 0.85rem; max-width: 300px;">
                                    <?php 
                                    // Показываем первые 5 публикаций
                                    $showCount = min(5, count($publications));
                                    for ($i = 0; $i < $showCount; $i++):
                                        $pub = $publications[$i];
                                        $isNext = ($i === 0);
                                    ?>
                                        <div style="margin-bottom: 0.5rem; padding: 0.25rem 0.5rem; background: <?= $isNext ? '#e3f2fd' : '#f5f5f5' ?>; border-radius: 4px; <?= $isNext ? 'border-left: 3px solid #3498db;' : '' ?>">
                                            <div style="font-weight: <?= $isNext ? '500' : '400' ?>; color: <?= $isNext ? '#3498db' : '#555' ?>;">
                                                <?= htmlspecialchars($pub['formatted']) ?>
                                            </div>
                                            <?php if ($isNext && $pub['time'] > time()): ?>
                                                <div class="countdown-timer-small" 
                                                     data-publish-at="<?= htmlspecialchars($pub['date']) ?>" 
                                                     style="font-size: 0.75rem; color: #3498db; margin-top: 0.25rem;">
                                                    <span class="countdown-text-small">Осталось: </span>
                                                    <span class="countdown-value-small">-</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                    <?php if (count($publications) > 5): ?>
                                        <div style="font-size: 0.75rem; color: #95a5a6; margin-top: 0.5rem;">
                                            И еще <?= count($publications) - 5 ?> публикаций...
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #95a5a6; font-size: 0.85rem;">Нет запланированных публикаций</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-<?= 
                                (isset($schedule['status']) && $schedule['status'] === 'pending') ? 'warning' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'published') ? 'success' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'failed') ? 'danger' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'paused') ? 'info' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'processing') ? 'primary' : 'secondary')))) 
                            ?>">
                                <?php 
                                $statusNames = [
                                    'pending' => 'Ожидает',
                                    'published' => 'Опубликовано',
                                    'failed' => 'Ошибка',
                                    'paused' => 'Приостановлено',
                                    'processing' => 'Обработка'
                                ];
                                echo $statusNames[$schedule['status'] ?? ''] ?? ucfirst($schedule['status'] ?? 'Неизвестно');
                                ?>
                            </span>
                        </td>
                        <td style="padding: 0.5rem;">
                            <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                <?php if (isset($schedule['id'])): ?>
                                    <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>" class="btn btn-xs btn-primary" title="Просмотр">
                                        <?= \App\Helpers\IconHelper::render('view', 14, 'icon-inline') ?>
                                    </a>
                                    <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>/edit" class="btn btn-xs btn-secondary" title="Редактировать">
                                        <?= \App\Helpers\IconHelper::render('edit', 14, 'icon-inline') ?>
                                    </a>
                                    <?php if (isset($schedule['status']) && $schedule['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-xs btn-warning" onclick="toggleSchedulePause(<?= (int)$schedule['id'] ?>, 'pause')" title="Приостановить">
                                            <?= \App\Helpers\IconHelper::render('pause', 14, 'icon-inline') ?>
                                        </button>
                                    <?php elseif (isset($schedule['status']) && $schedule['status'] === 'paused'): ?>
                                        <button type="button" class="btn btn-xs btn-success" onclick="toggleSchedulePause(<?= (int)$schedule['id'] ?>, 'resume')" title="Возобновить">
                                            <?= \App\Helpers\IconHelper::render('play', 14, 'icon-inline') ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-xs btn-danger" onclick="deleteSchedule(<?= (int)$schedule['id'] ?>)" title="Удалить">
                                        <?= \App\Helpers\IconHelper::render('delete', 14, 'icon-inline') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
function deleteSchedule(id) {
    if (!confirm('Удалить умное расписание?')) {
        return;
    }
    
    fetch('/content-groups/schedules/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Расписание удалено');
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось удалить расписание'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

function toggleSchedulePause(id, action) {
    const actionText = action === 'pause' ? 'приостановить' : 'возобновить';
    if (!confirm('Вы уверены, что хотите ' + actionText + ' это расписание?')) {
        return;
    }
    
    fetch('/content-groups/schedules/' + id + '/' + action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось изменить статус расписания'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка');
    });
}

// Обратный отсчет до публикации
function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown-timer');
    
    countdowns.forEach(timer => {
        const publishAtStr = timer.getAttribute('data-publish-at');
        if (!publishAtStr) return;
        
        const publishAt = new Date(publishAtStr.replace(' ', 'T'));
        const now = new Date();
        const diff = publishAt - now;
        
        if (diff <= 0) {
            // Время прошло - показываем причину, если есть
            const overdueReason = timer.getAttribute('data-overdue-reason');
            if (overdueReason && overdueReason.trim()) {
                timer.querySelector('.countdown-text').textContent = 'Причина: ';
                timer.querySelector('.countdown-value').textContent = overdueReason;
            } else {
                timer.querySelector('.countdown-text').textContent = '';
                timer.querySelector('.countdown-value').textContent = 'Время прошло';
            }
            timer.style.color = '#e74c3c';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        let countdownText = '';
        if (days > 0) {
            countdownText = `${days}д ${hours}ч ${minutes}м`;
        } else if (hours > 0) {
            countdownText = `${hours}ч ${minutes}м ${seconds}с`;
        } else if (minutes > 0) {
            countdownText = `${minutes}м ${seconds}с`;
        } else {
            countdownText = `${seconds}с`;
        }
        
        timer.querySelector('.countdown-text').textContent = 'Осталось: ';
        timer.querySelector('.countdown-value').textContent = countdownText;
        
        // Меняем цвет при приближении времени
        if (diff < 3600000) { // Меньше часа
            timer.style.color = '#e74c3c';
        } else if (diff < 86400000) { // Меньше суток
            timer.style.color = '#f39c12';
        } else {
            timer.style.color = '#3498db';
        }
    });
}

// Обновляем обратный отсчет каждую секунду
setInterval(updateCountdowns, 1000);
updateCountdowns(); // Первый запуск сразу

// Обратный отсчет для маленьких таймеров в списке следующих публикаций
function updateSmallCountdowns() {
    const countdowns = document.querySelectorAll('.countdown-timer-small');
    
    countdowns.forEach(timer => {
        const publishAtStr = timer.getAttribute('data-publish-at');
        if (!publishAtStr) return;
        
        const publishAt = new Date(publishAtStr.replace(' ', 'T'));
        const now = new Date();
        const diff = publishAt - now;
        
        if (diff <= 0) {
            timer.querySelector('.countdown-text-small').textContent = '';
            timer.querySelector('.countdown-value-small').textContent = 'Время прошло';
            timer.style.color = '#e74c3c';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        let countdownText = '';
        if (days > 0) {
            countdownText = `${days}д ${hours}ч ${minutes}м`;
        } else if (hours > 0) {
            countdownText = `${hours}ч ${minutes}м ${seconds}с`;
        } else if (minutes > 0) {
            countdownText = `${minutes}м ${seconds}с`;
        } else {
            countdownText = `${seconds}с`;
        }
        
        timer.querySelector('.countdown-text-small').textContent = 'Осталось: ';
        timer.querySelector('.countdown-value-small').textContent = countdownText;
        
        // Меняем цвет при приближении времени
        if (diff < 3600000) { // Меньше часа
            timer.style.color = '#e74c3c';
        } else if (diff < 86400000) { // Меньше суток
            timer.style.color = '#f39c12';
        } else {
            timer.style.color = '#3498db';
        }
    });
}

// Обновляем маленькие таймеры каждую секунду
setInterval(updateSmallCountdowns, 1000);
updateSmallCountdowns(); // Первый запуск сразу
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
