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
                    $group = $groups[$schedule['content_group_id']] ?? null;
                    $scheduleTypeNames = [
                        'fixed' => 'Фиксированное',
                        'interval' => 'Интервальное',
                        'batch' => 'Пакетное',
                        'random' => 'Случайное',
                        'wave' => 'Волновое'
                    ];
                    $scheduleType = $scheduleTypeNames[$schedule['schedule_type']] ?? $schedule['schedule_type'];
                ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 0.75rem;">
                            <?php if ($group): ?>
                                <a href="/content-groups/<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
                            <?php else: ?>
                                <span style="color: #95a5a6;">Группа не найдена</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <span class="badge badge-info"><?= ucfirst($schedule['platform']) ?></span>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?= htmlspecialchars($scheduleType) ?>
                            <?php if ($schedule['schedule_type'] === 'interval' && $schedule['interval_minutes']): ?>
                                <br><small style="color: #95a5a6;">Каждые <?= $schedule['interval_minutes'] ?> мин.</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem;">
                            <?php if ($schedule['publish_at']): ?>
                                <?php 
                                $publishTime = strtotime($schedule['publish_at']);
                                $now = time();
                                if ($publishTime > $now):
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
                                $schedule['status'] === 'pending' ? 'warning' : 
                                ($schedule['status'] === 'published' ? 'success' : 
                                ($schedule['status'] === 'failed' ? 'danger' : 'secondary')) 
                            ?>">
                                <?= ucfirst($schedule['status']) ?>
                            </span>
                        </td>
                        <td style="padding: 0.75rem;">
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteSchedule(<?= $schedule['id'] ?>)">
                                <?= \App\Helpers\IconHelper::render('delete', 16, 'icon-inline') ?> Удалить
                            </button>
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
