<?php
$title = 'История публикаций';
ob_start();
?>

<h1>История публикаций</h1>

<?php if (empty($publications)): ?>
    <p>Нет опубликованных видео</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Платформа</th>
                <th>Видео ID</th>
                <th>Статус</th>
                <th>Дата публикации</th>
                <th>Ссылка</th>
                <th>Ошибка</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($publications as $publication): ?>
                <tr>
                    <td><?= ucfirst($publication['platform']) ?></td>
                    <td><?= $publication['video_id'] ?></td>
                    <td>
                        <?php if ($publication['status'] === 'success'): ?>
                            <span class="status-success">✓ Успешно</span>
                        <?php else: ?>
                            <span class="status-error">✗ Ошибка</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $publication['published_at'] ? date('d.m.Y H:i', strtotime($publication['published_at'])) : '-' ?></td>
                    <td>
                        <?php if ($publication['platform_url']): ?>
                            <a href="<?= htmlspecialchars($publication['platform_url']) ?>" target="_blank">Открыть</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($publication['error_message']): ?>
                            <small class="error-message"><?= htmlspecialchars($publication['error_message']) ?></small>
                        <?php else: ?>
                            -
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
