<?php
$title = 'Просмотр умного расписания';
ob_start();
?>

<h1>Просмотр умного расписания</h1>

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
            <strong>Следующая публикация:</strong><br>
            <?php 
            // Для интервальных расписаний вычисляем следующее время публикации
            $nextPublishTime = null;
            $scheduleType = $schedule['schedule_type'] ?? 'fixed';
            $now = time();
            
            if ($scheduleType === 'interval' && !empty($schedule['interval_minutes'])) {
                $baseTime = strtotime($schedule['publish_at'] ?? 'now');
                $interval = (int)$schedule['interval_minutes'] * 60;
                
                if ($baseTime <= $now) {
                    // Базовое время прошло, вычисляем следующий интервал
                    $elapsed = $now - $baseTime;
                    $intervalsPassed = floor($elapsed / $interval);
                    $nextPublishTime = $baseTime + (($intervalsPassed + 1) * $interval);
                } else {
                    // Базовое время еще не наступило
                    $nextPublishTime = $baseTime;
                }
            } elseif (!empty($schedule['publish_at'])) {
                $nextPublishTime = strtotime($schedule['publish_at']);
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
            <?php elseif ($nextPublishTime): ?>
                <span style="color: #e74c3c;">Просрочено</span>
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
                                <?php else: ?>
                                    <span style="color: #e74c3c;">Просрочено</span>
                                    <?php if ($publishTime !== false): ?>
                                        <br><small style="color: #95a5a6;"><?= date('d.m.Y H:i', $publishTime) ?></small>
                                    <?php endif; ?>
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
