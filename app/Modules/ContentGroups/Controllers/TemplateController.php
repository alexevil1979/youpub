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
        $userId = $_SESSION['user_id'];
        $templates = $this->templateService->getUserTemplates($userId);
        
        if (!isset($templates)) {
            $templates = [];
        }
        
        include __DIR__ . '/../../../../views/content_groups/templates/index.php';
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
        $userId = $_SESSION['user_id'];
        
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
            $_SESSION['success'] = $result['message'];
            header('Location: /content-groups/templates');
        } else {
            $_SESSION['error'] = $result['message'];
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
