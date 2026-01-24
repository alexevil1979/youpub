<?php

namespace App\Controllers;

use Core\Controller;
use App\Services\VideoService;

/**
 * Контроллер для работы с видео
 */
class VideoController extends Controller
{
    private VideoService $videoService;

    public function __construct()
    {
        parent::__construct();
        $this->videoService = new VideoService();
    }

    /**
     * Список видео
     */
    public function index(): void
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                header('Location: /login');
                exit;
            }
            
            $userId = $_SESSION['user_id'];
            $videos = $this->videoService->getUserVideos($userId);
        
        // Получаем группы для отображения в модальном окне
        $groupService = new \App\Modules\ContentGroups\Services\GroupService();
        $groups = $groupService->getUserGroups($userId);
        
        // Получаем публикации для всех видео
        $publicationRepo = new \App\Repositories\PublicationRepository();
        $videoPublications = [];
        foreach ($videos as $video) {
            $publications = $publicationRepo->findSuccessfulByVideoId($video['id']);
            if (!empty($publications)) {
                // Берем первую (последнюю по дате) успешную публикацию
                $videoPublications[$video['id']] = $publications[0];
            }
        }
        
        include __DIR__ . '/../../views/videos/index.php';
        } catch (\Throwable $e) {
            error_log("VideoController::index error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo "Ошибка при загрузке страницы. Пожалуйста, попробуйте позже.";
        }
    }

    /**
     * Показать форму загрузки
     */
    public function showUpload(): void
    {
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../views/videos/upload.php';
    }

    /**
     * Загрузка видео
     */
    public function upload(): void
    {
        $userId = $_SESSION['user_id'];

        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $this->error('File upload failed');
            return;
        }

        $title = $this->getParam('title', '');
        $description = $this->getParam('description', '');
        $tags = $this->getParam('tags', '');

        $result = $this->videoService->uploadVideo(
            $userId,
            $_FILES['video'],
            $title,
            $description,
            $tags
        );

        if ($result['success']) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->success($result['data'], $result['message']);
            } else {
                header('Location: /videos');
            }
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Множественная загрузка видео
     */
    public function uploadMultiple(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                $this->error('Необходима авторизация', 401);
                return;
            }

            // Проверка наличия файлов
            if (!isset($_FILES['videos'])) {
                error_log('Upload error: $_FILES[videos] not set');
                $this->error('Файлы не загружены. Проверьте настройки PHP (upload_max_filesize, post_max_size, max_file_uploads)', 400);
                return;
            }

            if (!is_array($_FILES['videos']['name'])) {
                error_log('Upload error: $_FILES[videos][name] is not an array. Structure: ' . print_r($_FILES, true));
                $this->error('Неверный формат загружаемых файлов', 400);
                return;
            }

            // Проверка размера POST запроса
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
                $postMaxSize = ini_get('post_max_size');
                $uploadMaxSize = ini_get('upload_max_filesize');
                $maxFileUploads = ini_get('max_file_uploads');
                error_log('Upload error: POST data is empty. Check post_max_size: ' . $postMaxSize);
                $this->error(
                    'Размер запроса превышает лимит PHP. ' .
                    'Текущие настройки: post_max_size=' . $postMaxSize . ', upload_max_filesize=' . $uploadMaxSize . ', max_file_uploads=' . $maxFileUploads . '. ' .
                    'Рекомендуется установить: post_max_size=5120M, upload_max_filesize=5120M, max_file_uploads=50. ' .
                    'См. инструкцию: FIX_PHP_UPLOAD_SETTINGS.md',
                    400
                );
                return;
            }
            
            // Проверка размера загружаемых файлов перед обработкой
            $totalSize = 0;
            $postMaxSizeBytes = $this->parseSize(ini_get('post_max_size'));
            $uploadMaxSizeBytes = $this->parseSize(ini_get('upload_max_filesize'));
            
            foreach ($_FILES['videos']['size'] as $size) {
                $totalSize += $size;
                if ($size > $uploadMaxSizeBytes) {
                    $this->error(
                        'Один из файлов превышает upload_max_filesize (' . ini_get('upload_max_filesize') . '). ' .
                        'Увеличьте upload_max_filesize в настройках PHP.',
                        400
                    );
                    return;
                }
            }
            
            if ($totalSize > $postMaxSizeBytes) {
                $this->error(
                    'Общий размер файлов (' . $this->formatBytes($totalSize) . ') превышает post_max_size (' . ini_get('post_max_size') . '). ' .
                    'Увеличьте post_max_size в настройках PHP. См. FIX_PHP_UPLOAD_SETTINGS.md',
                    400
                );
                return;
            }

            // Преобразуем массив файлов в удобный формат
            $files = [];
            $fileCount = count($_FILES['videos']['name']);
            
            error_log("Processing {$fileCount} files for upload");
            
            for ($i = 0; $i < $fileCount; $i++) {
                // Пропускаем файлы с ошибкой UPLOAD_ERR_NO_FILE
                if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                
                // Логируем ошибки загрузки
                if ($_FILES['videos']['error'][$i] !== UPLOAD_ERR_OK) {
                    $errorMsg = $this->getUploadErrorMessage($_FILES['videos']['error'][$i]);
                    error_log("File upload error for {$_FILES['videos']['name'][$i]}: {$errorMsg} (code: {$_FILES['videos']['error'][$i]})");
                }
                
                $files[] = [
                    'name' => $_FILES['videos']['name'][$i] ?? 'unknown',
                    'type' => $_FILES['videos']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['videos']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['videos']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['videos']['size'][$i] ?? 0,
                ];
            }

            if (empty($files)) {
                error_log('Upload error: No valid files after processing');
                $this->error('Не выбрано ни одного файла для загрузки', 400);
                return;
            }

            $groupId = $this->getParam('group_id', null);
            $groupId = $groupId ? (int)$groupId : null;
            
            $titleTemplate = $this->getParam('title_template', '');
            $description = $this->getParam('description', '');
            $tags = $this->getParam('tags', '');

            error_log("Calling uploadMultipleVideos with " . count($files) . " files");

            $result = $this->videoService->uploadMultipleVideos(
                $userId,
                $files,
                $groupId,
                $titleTemplate,
                $description,
                $tags
            );

            // Всегда возвращаем JSON для множественной загрузки
            if ($result['success']) {
                $this->success($result['data'], $result['message']);
            } else {
                error_log('Upload failed: ' . $result['message']);
                $this->error($result['message'], 400);
            }
        } catch (\Exception $e) {
            error_log('Exception in uploadMultiple: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->error('Произошла ошибка при загрузке: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Преобразовать размер из строки (например, "8M") в байты
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $value = (int)$size;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Форматировать байты в читаемый формат
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Получить сообщение об ошибке загрузки
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Файл превышает upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Файл превышает MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE => 'Файл не загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением',
        ];
        
        return $messages[$errorCode] ?? 'Неизвестная ошибка загрузки (код: ' . $errorCode . ')';
    }

    /**
     * Показать видео
     */
    public function show(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $video = $this->videoService->getVideo($id, $userId);

        if (!$video) {
            http_response_code(404);
            echo 'Video not found';
            return;
        }

        // Проверяем, что файл существует
        if (!file_exists($video['file_path'])) {
            error_log('Video file not found: ' . $video['file_path']);
        }

        // Получаем публикации для этого видео
        $publicationRepo = new \App\Repositories\PublicationRepository();
        $publications = $publicationRepo->findSuccessfulByVideoId($id);

        // Получаем группы пользователя для кнопки "Добавить в группу"
        $groupService = new \App\Modules\ContentGroups\Services\GroupService();
        $groups = $groupService->getUserGroups($userId);

        include __DIR__ . '/../../views/videos/show.php';
    }

    /**
     * Опубликовать видео сейчас
     */
    public function publishNow(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->videoService->publishNow($id, $userId);

        if ($result['success']) {
            // Получаем обновленный список публикаций
            $publicationRepo = new \App\Repositories\PublicationRepository();
            $publications = $publicationRepo->findSuccessfulByVideoId($id);
            
            $result['data']['publications'] = $publications;
            $this->success($result['data'] ?? [], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Показать форму редактирования
     */
    public function showEdit(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $video = $this->videoService->getVideo($id, $userId);

        if (!$video) {
            http_response_code(404);
            echo 'Video not found';
            return;
        }

        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../views/videos/edit.php';
    }

    /**
     * Обновить видео
     */
    public function update(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $video = $this->videoService->getVideo($id, $userId);

        if (!$video) {
            $this->error('Video not found', 404);
            return;
        }

        $title = $this->getParam('title', '');
        $description = $this->getParam('description', '');
        $tags = $this->getParam('tags', '');

        $result = $this->videoService->updateVideo($id, $userId, [
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
        ]);

        if ($result['success']) {
            header('Location: /videos/' . $id);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Удалить видео
     */
    public function delete(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->videoService->deleteVideo($id, $userId);

        if ($result['success']) {
            $this->success([], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Переключить статус видео
     */
    public function toggleStatus(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $result = $this->videoService->toggleVideoStatus($id, $userId);

        if ($result['success']) {
            $this->success(['status' => $result['data']['status'] ?? null], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }
}
