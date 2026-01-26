<?php
$title = 'Просмотр расписания';
ob_start();
?>

<h1>Просмотр расписания</h1>

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

<div class="form-actions" style="margin-bottom: 2rem;">
    <a href="/content-groups/schedules" class="btn btn-secondary">← Назад к списку</a>
    <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>/edit" class="btn btn-primary">Редактировать</a>
</div>

<div class="info-card">
    <h2>Информация о расписании</h2>
    <div class="info-card-grid">
        <?php if (!empty($schedule['name'])): ?>
        <div style="grid-column: 1 / -1;">
            <strong>Название:</strong><br>
            <span style="font-size: 1.1rem; font-weight: 600;"><?= htmlspecialchars($schedule['name']) ?></span>
        </div>
        <?php endif; ?>
        <div>
            <strong>Группа:</strong><br>
            <?php if ($group): ?>
                <a href="/content-groups/<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
            <?php else: ?>
                <span style="color: #95a5a6;">Не указана</span>
            <?php endif; ?>
        </div>
        <div>
            <strong>Платформа:</strong><br>
            <span class="badge badge-info"><?= ucfirst($schedule['platform'] ?? 'Неизвестно') ?></span>
        </div>
        <div>
            <strong>Тип расписания:</strong><br>
            <?php
            $scheduleTypeNames = [
                'fixed' => 'Фиксированное',
                'interval' => 'Интервальное',
                'batch' => 'Пакетное',
                'random' => 'Случайное',
                'wave' => 'Волновое'
            ];
            echo htmlspecialchars($scheduleTypeNames[$schedule['schedule_type'] ?? ''] ?? $schedule['schedule_type'] ?? 'Неизвестно');
            ?>
        </div>
        <div>
            <strong>Статус:</strong><br>
            <span class="badge badge-<?= 
                ($schedule['status'] ?? '') === 'pending' ? 'warning' : 
                (($schedule['status'] ?? '') === 'published' ? 'success' : 
                (($schedule['status'] ?? '') === 'failed' ? 'danger' : 'secondary')) 
            ?>">
                <?= ucfirst($schedule['status'] ?? 'Неизвестно') ?>
            </span>
        </div>
        <div>
            <strong>Текущее время:</strong><br>
            <span style="color: #555; font-weight: 500;" id="current-time">
                <?= date('d.m.Y H:i:s') ?>
            </span>
        </div>
        <div>
            <strong>Следующая публикация:</strong><br>
            <?php 
            // Берем время первой неопубликованной публикации из списка файлов, которая еще не прошла
            $nextPublishTime = null;
            $now = time();
            if (!empty($scheduledFiles)) {
                foreach ($scheduledFiles as $item) {
                    if (!isset($item['is_published']) || !$item['is_published']) {
                        if (!empty($item['publish_at'])) {
                            $publishTime = strtotime($item['publish_at']);
                            // Берем только будущие публикации
                            if ($publishTime > $now) {
                                $nextPublishTime = $publishTime;
                                break;
                            }
                        }
                    }
                }
            }
            
            // Если не нашли в файлах, вычисляем из расписания
            if (!$nextPublishTime) {
                $scheduleType = $schedule['schedule_type'] ?? 'fixed';
                
                if ($scheduleType === 'interval' && !empty($schedule['interval_minutes'])) {
                    $baseTime = strtotime($schedule['publish_at'] ?? 'now');
                    $interval = (int)$schedule['interval_minutes'] * 60;
                    
                    if ($baseTime <= $now) {
                        $elapsed = $now - $baseTime;
                        $intervalsPassed = floor($elapsed / $interval);
                        $nextPublishTime = $baseTime + (($intervalsPassed + 1) * $interval);
                    } else {
                        $nextPublishTime = $baseTime;
                    }
                } elseif (!empty($schedule['publish_at'])) {
                    $scheduleTime = strtotime($schedule['publish_at']);
                    // Если время расписания прошло, ищем следующее доступное время
                    if ($scheduleTime <= $now && $scheduleType === 'fixed') {
                        // Для фиксированных расписаний с просроченным временем берем следующее время из файлов
                        // или пересчитываем с учетом задержки
                        $delayMinutes = isset($schedule['delay_between_posts']) ? (int)$schedule['delay_between_posts'] : 0;
                        if ($delayMinutes > 0) {
                            // Добавляем задержку к текущему времени
                            $nextPublishTime = $now + ($delayMinutes * 60);
                        } else {
                            $nextPublishTime = $scheduleTime;
                        }
                    } else {
                        $nextPublishTime = $scheduleTime;
                    }
                }
            }
            
            if ($nextPublishTime && $nextPublishTime > $now):
            ?>
                <span style="color: #3498db; font-weight: 500;">
                    <?= date('d.m.Y H:i', $nextPublishTime) ?>
                </span>
                <br><small style="color: #95a5a6;">
                    <?php
                    $diff = $nextPublishTime - $now;
                    $days = floor($diff / 86400);
                    $hours = floor(($diff % 86400) / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    if ($days > 0) {
                        echo "через {$days} дн. ";
                    }
                    if ($hours > 0) {
                        echo "{$hours} ч. ";
                    }
                    echo "{$minutes} мин.";
                    ?>
                </small>
            <?php elseif ($nextPublishTime && $nextPublishTime <= $now): ?>
                <span style="color: #f39c12; font-weight: 500;">Готово к публикации</span>
                <br><small style="color: #95a5a6;">Ожидает публикации воркером</small>
            <?php else: ?>
                <span style="color: #95a5a6;">Не запланировано</span>
            <?php endif; ?>
        </div>
        <?php if ($template): ?>
        <div>
            <strong>Шаблон:</strong><br>
            <?= htmlspecialchars($template['name'] ?? 'Без названия') ?>
        </div>
        <?php endif; ?>
        <?php if (($schedule['schedule_type'] ?? '') === 'fixed'): ?>
        <div>
            <strong>Параметры расписания:</strong><br>
            <?php if (!empty($schedule['active_hours_start']) && !empty($schedule['active_hours_end'])): ?>
                <small>Активные часы: <?= htmlspecialchars($schedule['active_hours_start']) ?> - <?= htmlspecialchars($schedule['active_hours_end']) ?></small><br>
            <?php endif; ?>
            <?php if (!empty($schedule['delay_between_posts'])): ?>
                <small>Задержка между публикациями: <?= (int)$schedule['delay_between_posts'] ?> мин.</small><br>
            <?php endif; ?>
            <?php if (!empty($schedule['daily_limit'])): ?>
                <small>Дневной лимит: <?= (int)$schedule['daily_limit'] ?> видео</small><br>
            <?php endif; ?>
            <?php if (!empty($schedule['hourly_limit'])): ?>
                <small>Часовой лимит: <?= (int)$schedule['hourly_limit'] ?> видео</small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($group && !empty($scheduledFiles)): ?>
    <h2>Каталог файлов и расписание публикации</h2>
    <div style="margin-top: 1rem;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">#</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Файл</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Статус</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Время публикации</th>
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #dee2e6;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduledFiles as $index => $item): 
                    $file = $item['file'];
                    $publishAt = $item['publish_at'];
                ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 0.75rem;">
                            <?= $index + 1 ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <strong><?= htmlspecialchars($file['title'] ?? $file['file_name'] ?? 'Без названия') ?></strong>
                            <?php if (!empty($file['file_name'])): ?>
                                <br><small style="color: #95a5a6;"><?= htmlspecialchars($file['file_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-<?= 
                                ($file['status'] ?? '') === 'published' ? 'success' : 
                                (($file['status'] ?? '') === 'queued' ? 'warning' : 
                                (($file['status'] ?? '') === 'error' ? 'danger' : 'secondary')) 
                            ?>">
                                <?= ucfirst($file['status'] ?? 'new') ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php if (isset($item['is_published']) && $item['is_published']): ?>
                                <span style="color: #27ae60; font-weight: 500;">Опубликовано</span>
                            <?php elseif ($publishAt): 
                                $publishTime = strtotime($publishAt);
                                $now = time();
                            ?>
                                <?php if ($publishTime !== false && $publishTime > $now): ?>
                                    <span style="color: #3498db; font-weight: 500;">
                                        <?= date('d.m.Y H:i', $publishTime) ?>
                                    </span>
                                    <br><small style="color: #95a5a6;">
                                        <?php
                                        $diff = $publishTime - $now;
                                        $days = floor($diff / 86400);
                                        $hours = floor(($diff % 86400) / 3600);
                                        $minutes = floor(($diff % 3600) / 60);
                                        if ($days > 0) {
                                            echo "через {$days} дн. ";
                                        }
                                        if ($hours > 0) {
                                            echo "{$hours} ч. ";
                                        }
                                        echo "{$minutes} мин.";
                                        ?>
                                    </small>
                                <?php elseif ($publishTime !== false && $publishTime <= $now): ?>
                                    <span style="color: #f39c12; font-weight: 500;">Готово к публикации</span>
                                    <br><small style="color: #95a5a6;">
                                        <?= date('d.m.Y H:i', $publishTime) ?>
                                        <br>Ожидает публикации воркером
                                    </small>
                                <?php else: ?>
                                    <span style="color: #e74c3c;">Ошибка времени</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #95a5a6;">Не запланировано</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php if (!empty($file['video_id'])): ?>
                                <a href="/videos/<?= (int)$file['video_id'] ?>" class="btn btn-sm btn-secondary">
                                    <?= \App\Helpers\IconHelper::render('view', 16, 'icon-inline') ?> Просмотр
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($group && empty($files)): ?>
    <div class="alert alert-info">
        <p>В группе нет файлов. <a href="/content-groups/<?= (int)$group['id'] ?>">Добавить файлы в группу</a></p>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <p>Группа не указана или не найдена.</p>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
