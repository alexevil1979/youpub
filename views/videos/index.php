<?php
$title = 'Мои видео';
ob_start();
?>

<h1>Мои видео</h1>
<a href="/videos/upload" class="btn btn-primary">Загрузить видео</a>

<?php if (empty($videos)): ?>
    <p>Нет загруженных видео</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Название</th>
                <th>Размер</th>
                <th>Статус</th>
                <th>Дата загрузки</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($videos as $video): ?>
            <tr>
                <td><?= htmlspecialchars($video['title'] ?? $video['file_name']) ?></td>
                <td><?= number_format($video['file_size'] / 1024 / 1024, 2) ?> MB</td>
                <td><?= ucfirst($video['status']) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($video['created_at'])) ?></td>
                <td>
                    <a href="/videos/<?= $video['id'] ?>">Просмотр</a>
                    <a href="/schedules/create?video_id=<?= $video['id'] ?>">Запланировать</a>
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
