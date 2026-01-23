<?php

namespace App\Modules\ContentGroups\Services;

use Core\Service;
use App\Modules\ContentGroups\Repositories\PublicationTemplateRepository;

/**
 * Сервис для работы с шаблонами публикаций
 */
class TemplateService extends Service
{
    private PublicationTemplateRepository $templateRepo;

    public function __construct()
    {
        parent::__construct();
        $this->templateRepo = new PublicationTemplateRepository();
    }

    /**
     * Создать шаблон
     */
    public function createTemplate(int $userId, array $data): array
    {
        try {
            // Валидация обязательных полей
            if (empty($data['name'])) {
                return [
                    'success' => false,
                    'message' => 'Название шаблона обязательно'
                ];
            }

            $templateId = $this->templateRepo->create([
                'user_id' => $userId,
                'name' => trim($data['name'] ?? ''),
                'description' => !empty($data['description']) ? trim($data['description']) : null,
                'title_template' => !empty($data['title_template']) ? trim($data['title_template']) : null,
                'description_template' => !empty($data['description_template']) ? trim($data['description_template']) : null,
                'tags_template' => !empty($data['tags_template']) ? trim($data['tags_template']) : null,
                'emoji_list' => !empty($data['emoji_list']) && is_array($data['emoji_list']) ? json_encode($data['emoji_list'], JSON_UNESCAPED_UNICODE) : null,
                'variants' => !empty($data['variants']) && is_array($data['variants']) ? json_encode($data['variants'], JSON_UNESCAPED_UNICODE) : null,
                'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            ]);

            if (!$templateId) {
                return [
                    'success' => false,
                    'message' => 'Не удалось создать шаблон. Попробуйте снова.'
                ];
            }

            return [
                'success' => true,
                'data' => ['id' => $templateId],
                'message' => 'Шаблон успешно создан'
            ];
        } catch (\Exception $e) {
            error_log('Error in createTemplate: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка при создании шаблона: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Применить шаблон к видео
     */
    public function applyTemplate(?int $templateId, array $video, array $context = []): array
    {
        if (!$templateId) {
            return [
                'title' => $video['title'] ?? '',
                'description' => $video['description'] ?? '',
                'tags' => $video['tags'] ?? '',
            ];
        }

        $template = $this->templateRepo->findById($templateId);
        if (!$template) {
            return [
                'title' => $video['title'] ?? '',
                'description' => $video['description'] ?? '',
                'tags' => $video['tags'] ?? '',
            ];
        }

        // Подготовка контекста для переменных
        $vars = array_merge([
            'title' => $video['title'] ?? '',
            'group_name' => $context['group_name'] ?? '',
            'index' => $context['index'] ?? '',
            'date' => date('d.m.Y'),
            'platform' => $context['platform'] ?? '',
        ], $context);

        // Обработка emoji
        $emojiList = !empty($template['emoji_list']) ? json_decode($template['emoji_list'], true) : [];
        if (!empty($emojiList)) {
            $vars['random_emoji'] = $emojiList[array_rand($emojiList)];
        } else {
            $vars['random_emoji'] = '🎬';
        }

        // Применение шаблонов
        $result = [
            'title' => $this->processTemplate($template['title_template'] ?? '', $vars, $video['title'] ?? ''),
            'description' => $this->processTemplate($template['description_template'] ?? '', $vars, $video['description'] ?? ''),
            'tags' => $this->processTemplate($template['tags_template'] ?? '', $vars, $video['tags'] ?? ''),
        ];

        // Обработка вариантов (рандомизация)
        if (!empty($template['variants'])) {
            $variants = json_decode($template['variants'], true);
            if (!empty($variants['description'])) {
                $result['description'] = $variants['description'][array_rand($variants['description'])];
                $result['description'] = $this->processTemplate($result['description'], $vars);
            }
        }

        return $result;
    }

    /**
     * Обработать шаблон с переменными
     */
    private function processTemplate(string $template, array $vars, string $default = ''): string
    {
        if (empty($template)) {
            return $default;
        }

        // Замена переменных {var}
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        // Очистка неиспользованных переменных
        $template = preg_replace('/\{[^}]+\}/', '', $template);

        return trim($template);
    }

    /**
     * Получить шаблоны пользователя
     */
    public function getUserTemplates(int $userId, bool $activeOnly = false): array
    {
        try {
            error_log("TemplateService::getUserTemplates: userId={$userId}, activeOnly=" . ($activeOnly ? 'true' : 'false'));
            
            $templates = $this->templateRepo->findByUserId($userId, $activeOnly);
            
            if (!is_array($templates)) {
                error_log("TemplateService::getUserTemplates: Repository returned non-array, returning empty array");
                return [];
            }
            
            error_log("TemplateService::getUserTemplates: Found " . count($templates) . " templates");
            return $templates;
        } catch (\Exception $e) {
            error_log("TemplateService::getUserTemplates: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return [];
        }
    }

    /**
     * Превью шаблона
     */
    public function previewTemplate(int $templateId, array $sampleData): array
    {
        $template = $this->templateRepo->findById($templateId);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found'];
        }

        $context = [
            'title' => $sampleData['title'] ?? 'Пример видео',
            'group_name' => $sampleData['group_name'] ?? 'Пример группы',
            'index' => $sampleData['index'] ?? '1',
            'date' => date('d.m.Y'),
            'platform' => $sampleData['platform'] ?? 'youtube',
        ];

        $result = $this->applyTemplate($templateId, $sampleData, $context);

        return [
            'success' => true,
            'data' => $result
        ];
    }
}
