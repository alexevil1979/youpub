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
                    <tr style="border-bottom: 1px solid #dee2e6;">
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
                            <?php if (isset($schedule['publish_at']) && $schedule['publish_at']): ?>
                                <?php 
                                $publishTime = strtotime($schedule['publish_at']);
                                $now = time();
                                if ($publishTime !== false && $publishTime > $now):
                                ?>
                                    <span style="color: #3498db; font-weight: 500;">
                                        <?= date('d.m.Y H:i', $publishTime) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #e74c3c;">Просрочено</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #95a5a6;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-<?= 
                                (isset($schedule['status']) && $schedule['status'] === 'pending') ? 'warning' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'published') ? 'success' : 
                                ((isset($schedule['status']) && $schedule['status'] === 'failed') ? 'danger' : 'secondary')) 
                            ?>">
                                <?= isset($schedule['status']) ? ucfirst($schedule['status']) : 'Неизвестно' ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php if (isset($schedule['id'])): ?>
                                <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>" class="btn btn-sm btn-primary" style="margin-right: 0.5rem;">
                                    <?= \App\Helpers\IconHelper::render('view', 16, 'icon-inline') ?> Просмотр
                                </a>
                                <a href="/content-groups/schedules/<?= (int)$schedule['id'] ?>/edit" class="btn btn-sm btn-secondary" style="margin-right: 0.5rem;">
                                    <?= \App\Helpers\IconHelper::render('edit', 16, 'icon-inline') ?> Редактировать
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteSchedule(<?= (int)$schedule['id'] ?>)">
                                    <?= \App\Helpers\IconHelper::render('delete', 16, 'icon-inline') ?> Удалить
                                </button>
                            <?php endif; ?>
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
