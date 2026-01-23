<?php

namespace App\Modules\ContentGroups\Controllers;

use Core\Controller;
use App\Modules\ContentGroups\Services\TemplateService;

/**
 * Контроллер для управления шаблонами
 */
class TemplateController extends Controller
{
    private TemplateService $templateService;

    public function __construct()
    {
        parent::__construct();
        $this->templateService = new TemplateService();
    }

    /**
     * Список шаблонов
     */
    public function index(): void
    {
        error_log("TemplateController::index: START - " . date('Y-m-d H:i:s'));
        
        try {
            // Проверяем сессию
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            error_log("TemplateController::index: userId = " . ($userId ?? 'NULL'));
            
            if (!$userId) {
                error_log("TemplateController::index: No user ID, redirecting to login");
                header('Location: /login');
                exit;
            }
            
            // Инициализируем переменные
            $templates = [];
            
            try {
                error_log("TemplateController::index: Loading templates for user {$userId}");
                $templates = $this->templateService->getUserTemplates($userId);
                error_log("TemplateController::index: Loaded " . count($templates) . " templates");
                
                if (!isset($templates) || !is_array($templates)) {
                    $templates = [];
                }
            } catch (\Exception $e) {
                error_log("TemplateController::index: Error loading templates: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                $templates = [];
            }
            
            // Проверяем существование файла представления
            $viewPath = __DIR__ . '/../../../../views/content_groups/templates/index.php';
            if (!file_exists($viewPath)) {
                throw new \Exception("View file not found: {$viewPath}");
            }
            
            // Включаем представление
            include $viewPath;
            
        } catch (\Exception $e) {
            error_log("TemplateController::index: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            // Очищаем буфер вывода, если он был начат
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Показываем HTML страницу с ошибкой
            $title = 'Ошибка';
            ob_start();
            ?>
            <div class="alert alert-error">
                <h2>Ошибка при загрузке шаблонов</h2>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
                <p><a href="/dashboard" class="btn btn-secondary">Вернуться на главную</a></p>
            </div>
            <?php
            $content = ob_get_clean();
            
            $layoutPath = __DIR__ . '/../../../../views/layout.php';
            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content;
            }
        } catch (\Throwable $e) {
            error_log("TemplateController::index: FATAL - " . $e->getMessage());
            http_response_code(500);
            echo "Internal Server Error. Please check server logs.";
        }
    }

    /**
     * Показать форму создания шаблона
     */
    public function showCreate(): void
    {
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        include __DIR__ . '/../../../../views/content_groups/templates/create.php';
    }

    /**
     * Создать шаблон
     */
    public function create(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                $_SESSION['error'] = 'Необходима авторизация';
                header('Location: /content-groups/templates/create');
                exit;
            }
            
            $emojiList = $this->getParam('emoji_list', '');
            $emojiArray = !empty($emojiList) ? array_filter(array_map('trim', explode(',', $emojiList))) : [];

            $variants = [];
            if ($this->getParam('variant_1')) {
                $variants['description'][] = $this->getParam('variant_1');
            }
            if ($this->getParam('variant_2')) {
                $variants['description'][] = $this->getParam('variant_2');
            }
            if ($this->getParam('variant_3')) {
                $variants['description'][] = $this->getParam('variant_3');
            }

            $data = [
                'name' => $this->getParam('name', ''),
                'description' => $this->getParam('description', ''),
                'title_template' => $this->getParam('title_template', ''),
                'description_template' => $this->getParam('description_template', ''),
                'tags_template' => $this->getParam('tags_template', ''),
                'emoji_list' => $emojiArray,
                'variants' => !empty($variants) ? $variants : null,
                'is_active' => $this->getParam('is_active', '1') === '1',
            ];

            $result = $this->templateService->createTemplate($userId, $data);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'] ?? 'Шаблон успешно создан';
                header('Location: /content-groups/templates');
            } else {
                $_SESSION['error'] = $result['message'] ?? 'Ошибка при создании шаблона';
                header('Location: /content-groups/templates/create');
            }
        } catch (\Exception $e) {
            error_log('Error creating template: ' . $e->getMessage());
            $_SESSION['error'] = 'Произошла ошибка при сохранении шаблона: ' . $e->getMessage();
            header('Location: /content-groups/templates/create');
        }
        exit;
    }

    /**
     * Превью шаблона
     */
    public function preview(int $id): void
    {
        $sampleData = [
            'title' => $this->getParam('sample_title', 'Пример видео'),
            'group_name' => $this->getParam('sample_group', 'Пример группы'),
            'index' => $this->getParam('sample_index', '1'),
            'platform' => $this->getParam('sample_platform', 'youtube'),
        ];

        $result = $this->templateService->previewTemplate($id, $sampleData);
        
        if ($result['success']) {
            $this->success($result['data'], 'Template preview');
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Удалить шаблон
     */
    public function delete(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
        $template = $templateRepo->findById($id);

        if (!$template || $template['user_id'] !== $userId) {
            $this->error('Template not found', 404);
            return;
        }

        $templateRepo->delete($id);
        $this->success([], 'Template deleted successfully');
    }
}
