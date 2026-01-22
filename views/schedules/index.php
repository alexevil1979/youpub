<?php
$title = 'Расписания публикаций';
ob_start();
?>

<h1>Расписания публикаций</h1>
<a href="/schedules/create" class="btn btn-primary">Создать расписание</a>

<?php if (empty($schedules)): ?>
    <p>Нет созданных расписаний</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Видео</th>
                <th>Платформа</th>
                <th>Дата публикации</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedules as $schedule): ?>
            <tr>
                <td>ID: <?= $schedule['video_id'] ?></td>
                <td><?= ucfirst($schedule['platform']) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($schedule['publish_at'])) ?></td>
                <td><?= ucfirst($schedule['status']) ?></td>
                <td>
                    <a href="/schedules/<?= $schedule['id'] ?>">Просмотр</a>
                    <?php if ($schedule['status'] === 'pending'): ?>
                        <a href="/schedules/<?= $schedule['id'] ?>" onclick="return confirm('Удалить?')">Удалить</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
