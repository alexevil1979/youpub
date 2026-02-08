<?php
// Убеждаемся, что сессия инициализирована
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Проверяем, что $template определен (может быть null для режима создания)
    $isEdit = isset($template) && $template !== null && is_array($template);
    $pageTitle = $isEdit ? 'Редактировать шаблон Shorts' : 'Создать шаблон Shorts (улучшенный)';
    $title = $pageTitle;
    $formAction = $isEdit ? '/content-groups/templates/' . ($template['id'] ?? '') . '/update' : '/content-groups/templates/create-shorts';
    
    // Проверяем, что $csrfToken определен (должен быть передан из контроллера)
    if (!isset($csrfToken) || empty($csrfToken)) {
        error_log("Templates create_v2 view: csrfToken not set, generating new one");
        try {
            $csrfToken = (new \Core\Auth())->generateCsrfToken();
        } catch (\Throwable $csrfError) {
            error_log("Templates create_v2 view: Error generating CSRF token: " . $csrfError->getMessage());
            throw new \RuntimeException("Failed to generate CSRF token: " . $csrfError->getMessage());
        }
    }
} catch (\Throwable $e) {
    error_log("Templates create_v2 view: Error at start: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Templates create_v2 view: Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "Error loading template creation page. Please check server logs.";
    exit;
}

$decodeJson = static function ($value): array {
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
};

// Безопасное получение значений из $template (может быть null)
$nameValue = $isEdit && isset($template['name']) ? (string)$template['name'] : '';
$descriptionValue = $isEdit && isset($template['description']) ? (string)$template['description'] : '';
$hookTypeValue = $isEdit && isset($template['hook_type']) ? (string)$template['hook_type'] : '';
$focusPoints = $isEdit && isset($template['focus_points']) ? $decodeJson($template['focus_points']) : [];
$titleVariants = $isEdit && isset($template['title_variants']) ? $decodeJson($template['title_variants']) : [];
$descriptionVariants = $isEdit && isset($template['description_variants']) ? $decodeJson($template['description_variants']) : [];
$emojiGroups = $isEdit && isset($template['emoji_groups']) ? $decodeJson($template['emoji_groups']) : [];
$baseTagsValue = $isEdit && isset($template['base_tags']) ? (string)$template['base_tags'] : 'неон, голос, вокал, атмосфера, музыка';
$tagVariants = $isEdit && isset($template['tag_variants']) ? $decodeJson($template['tag_variants']) : [];
$questions = $isEdit && isset($template['questions']) ? $decodeJson($template['questions']) : [];
$pinnedComments = $isEdit && isset($template['pinned_comments']) ? $decodeJson($template['pinned_comments']) : [];
$ctaTypes = $isEdit && isset($template['cta_types']) ? $decodeJson($template['cta_types']) : [];
$enableAbTesting = $isEdit && isset($template['enable_ab_testing']) ? !empty($template['enable_ab_testing']) : true;
$isActive = $isEdit && isset($template['is_active']) ? !empty($template['is_active']) : true;

$descriptionItems = [];
foreach ($descriptionVariants as $type => $variants) {
    if (is_array($variants)) {
        foreach ($variants as $variant) {
            $descriptionItems[] = ['type' => $type, 'text' => $variant];
        }
    }
}
if (empty($descriptionItems)) {
    $descriptionItems[] = ['type' => '', 'text' => ''];
}

if (empty($titleVariants)) {
    $titleVariants = ['', '', ''];
}
if (empty($tagVariants)) {
    $tagVariants = [
        'неоновые огни, женский вокал, эмоции',
        'красный неон, спокойная музыка, чувства',
        'синий неон, уникальный голос, настроение',
    ];
}
if (empty($questions)) {
    $questions = [
        'Какое сочетание цветов тебе больше всего понравилось?',
        'Чувствовал ли ты мурашки от голоса?',
        'Какая часть видео тебя зацепила больше всего?',
    ];
}
if (empty($pinnedComments)) {
    $pinnedComments = [
        '🎵 Слушай плейлист в моём профиле',
        '🔥 Все видео этой серии здесь 👇',
    ];
}

$emojiDefaults = [
    'emotional' => '😱,😲,❤️,💙,💜,🔥,✨,🌟',
    'intrigue' => '🤔,❓,🔍,🎭,🎪,🎨,🌈,⭐',
    'atmosphere' => '🌙,🌃,🌌,💫,🌠,🎵,🎶,🎼',
    'question' => '❓,🤔,💭,💡,🔮,🎯,🎪,🎨',
    'cta' => '👇,💬,📝,✍️,💭,🔥,👍,❤️',
];

$formatEmojiGroup = static function ($value, string $fallback): string {
    if (is_array($value)) {
        return implode(',', $value);
    }
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return $fallback;
};

// Начинаем буферизацию вывода перед HTML
// Убеждаемся, что нет активного буфера (если есть - это ошибка)
if (ob_get_level() > 0) {
    error_log("Templates create_v2 view: WARNING - Output buffer already active (level: " . ob_get_level() . "), cleaning");
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
ob_start();
?>

<h1><?= $isEdit ? '✏️ Редактировать шаблон для YouTube Shorts' : '🎯 Создать шаблон для YouTube Shorts' ?></h1>

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

<form method="POST" action="<?= htmlspecialchars($formAction) ?>" class="template-form-shorts" id="templateForm">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <!-- ОСНОВНАЯ ИНФОРМАЦИЯ -->
    <div class="form-section">
        <h3>📋 Основная информация</h3>

        <div class="form-group">
            <label for="name">Название шаблона *</label>
            <input type="text" id="name" name="name" required placeholder="Например: Неон + Голос (Эмоциональный)" value="<?= htmlspecialchars($nameValue) ?>">
            <small>Уникальное название для идентификации шаблона</small>
        </div>

        <div class="form-group">
            <label for="description">Описание шаблона</label>
            <textarea id="description" name="description" rows="2" placeholder="Для чего используется этот шаблон"><?= htmlspecialchars($descriptionValue) ?></textarea>
        </div>

        <!-- Переключатель автогенерации (шаблонная) -->
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" id="use_auto_generation" name="use_auto_generation">
                🚀 Использовать автогенерацию контента (шаблонная)
            </label>
            <small>Автоматически сгенерировать контент из одной идеи (шаблонный движок, без AI)</small>
        </div>

        <!-- Переключатель автогенерации через AI GROQ -->
        <div class="form-group">
            <label class="checkbox-label checkbox-label-groq">
                <input type="checkbox" id="use_groq_ai" name="use_groq_ai">
                🤖 Использовать автогенерацию контента ИИ GROQ
            </label>
            <small>Генерация через нейросеть Groq AI (LLaMA 3.3 70B) — более креативные и уникальные результаты</small>
        </div>

        <!-- Переключатель автогенерации через GigaChat -->
        <div class="form-group">
            <label class="checkbox-label checkbox-label-gigachat">
                <input type="checkbox" id="use_gigachat_ai" name="use_gigachat_ai">
                🧠 Использовать автогенерацию контента ИИ GigaChat (Сбер)
            </label>
            <small>Генерация через GigaChat (Сбер) — русскоязычная нейросеть, отлично понимает русский контекст</small>
        </div>

        <!-- Поле для идеи (скрыто по умолчанию) -->
        <div class="form-group auto-gen-field" id="idea_field" style="display: none;">
            <label for="video_idea">💡 Базовая идея видео *</label>
            <input type="text" id="video_idea" name="video_idea" placeholder="Например: Девушка поёт под неоном" maxlength="100">
            <small>Опишите суть видео в 3-7 словах</small>
            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" id="btn_generate_template" onclick="generateFromIdea()" style="display:none;">
                    🎯 Сгенерировать (шаблон)
                </button>
                <button type="button" class="btn btn-primary" id="btn_generate_groq" onclick="generateFromGroq()" style="display:none;">
                    🤖 Сгенерировать (AI GROQ)
                </button>
                <button type="button" class="btn btn-gigachat" id="btn_generate_gigachat" onclick="generateFromGigaChat()" style="display:none;">
                    🧠 Сгенерировать (GigaChat)
                </button>
            </div>
        </div>
    </div>

    <!-- Ручные поля формы (скрываются при автогенерации) -->
    <div id="manual_fields">

    <!-- ТИП КОНТЕНТА -->
    <div class="form-section">
        <h3>🎭 Тип контента</h3>

        <div class="form-group">
            <label for="hook_type">Основной тип контента *</label>
            <select id="hook_type" name="hook_type" required>
                <option value="">Выберите тип</option>
                <option value="emotional" <?= $hookTypeValue === 'emotional' ? 'selected' : '' ?>>😱 Эмоциональный (мурашки, слезы, восторг)</option>
                <option value="intriguing" <?= $hookTypeValue === 'intriguing' ? 'selected' : '' ?>>🤔 Интригующий (секрет, загадка, интрига)</option>
                <option value="atmospheric" <?= $hookTypeValue === 'atmospheric' ? 'selected' : '' ?>>🌙 Атмосферный (настроение, атмосфера, чувство)</option>
                <option value="visual" <?= $hookTypeValue === 'visual' ? 'selected' : '' ?>>🎨 Визуальный (красиво, эстетика, цвета)</option>
                <option value="educational" <?= $hookTypeValue === 'educational' ? 'selected' : '' ?>>📚 Образовательный (узнаешь, откроешь, поймешь)</option>
            </select>
            <small>Определяет стиль подачи контента</small>
        </div>

        <div class="form-group">
            <label>Фокус видео (можно выбрать несколько)</label>
            <div class="checkbox-grid">
                <label><input type="checkbox" name="focus_points[]" value="voice" <?= in_array('voice', $focusPoints, true) ? 'checked' : '' ?>> 🎤 Голос/вокал</label>
                <label><input type="checkbox" name="focus_points[]" value="neon" <?= in_array('neon', $focusPoints, true) ? 'checked' : '' ?>> 💡 Неоновые огни/цвета</label>
                <label><input type="checkbox" name="focus_points[]" value="atmosphere" <?= in_array('atmosphere', $focusPoints, true) ? 'checked' : '' ?>> 🌫️ Атмосфера/настроение</label>
                <label><input type="checkbox" name="focus_points[]" value="effects" <?= in_array('effects', $focusPoints, true) ? 'checked' : '' ?>> ✨ Визуальные эффекты</label>
                <label><input type="checkbox" name="focus_points[]" value="combination" <?= in_array('combination', $focusPoints, true) ? 'checked' : '' ?>> 🔄 Комбинация всего</label>
            </div>
        </div>
    </div>

    <!-- ШАБЛОН НАЗВАНИЯ -->
    <div class="form-section">
        <h3>📝 Варианты названий (A/B тестирование)</h3>

        <div class="form-group">
            <div id="titleVariants">
                <?php
                $titlePlaceholders = [
                    'Вариант 1: Неон + голос = мурашки по коже',
                    'Вариант 2: Этот вокал заставляет светиться ярче',
                    'Вариант 3: Когда голос встречает неоновый свет',
                ];
                foreach ($titleVariants as $index => $value):
                    $placeholder = $titlePlaceholders[$index] ?? ('Вариант ' . ($index + 1));
                ?>
                <div class="variant-item">
                    <input type="text" name="title_variants[]" placeholder="<?= htmlspecialchars($placeholder) ?>" value="<?= htmlspecialchars($value) ?>" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addTitleVariant" class="btn btn-sm btn-secondary">➕ Добавить вариант</button>
        </div>

        <div class="validation-warnings" id="titleWarnings"></div>

        <div class="form-help">
            <small>
                <strong>✅ ПРАВИЛЬНО:</strong> "Неон + голос = мурашки", "Этот вокал странно успокаивает"<br>
                <strong>❌ ЗАПРЕЩЕНО:</strong> "Часть 1:", "Серия 2:", "{index}", одинаковые начала
            </small>
        </div>
    </div>

    <!-- ШАБЛОН ОПИСАНИЯ -->
    <div class="form-section">
        <h3>📋 Варианты описаний</h3>

        <div class="form-group">
            <div id="descriptionVariants">
                <?php foreach ($descriptionItems as $item): ?>
                <div class="variant-item description-variant">
                    <select name="description_types[]" class="description-type" required>
                        <option value="">Тип триггера</option>
                        <option value="emotional" <?= $item['type'] === 'emotional' ? 'selected' : '' ?>>😱 Эмоция</option>
                        <option value="intrigue" <?= $item['type'] === 'intrigue' ? 'selected' : '' ?>>🤔 Интрига</option>
                        <option value="atmosphere" <?= $item['type'] === 'atmosphere' ? 'selected' : '' ?>>🌙 Атмосфера</option>
                        <option value="question" <?= $item['type'] === 'question' ? 'selected' : '' ?>>❓ Вопрос</option>
                        <option value="cta" <?= $item['type'] === 'cta' ? 'selected' : '' ?>>👇 CTA</option>
                    </select>
                    <textarea name="description_texts[]" rows="2" placeholder="Текст описания (1-2 строки)" required><?= htmlspecialchars($item['text']) ?></textarea>
                    <button type="button" class="btn btn-sm btn-danger remove-variant">❌</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addDescriptionVariant" class="btn btn-sm btn-secondary">➕ Добавить вариант описания</button>
        </div>

        <div class="form-help">
            <small>
                <strong>Рекомендации по триггерам:</strong><br>
                • <strong>Эмоция:</strong> "Этот голос вызывает мурашки 😱"<br>
                • <strong>Интрига:</strong> "Знаешь, что бывает, когда неон встречает вокал?"<br>
                • <strong>Атмосфера:</strong> "Такая атмосфера, что хочется замереть 🌙"<br>
                • <strong>Вопрос:</strong> "Как тебе эта комбинация? 💭"<br>
                • <strong>CTA:</strong> "Досмотрел до конца? Расскажи в комментариях!"
            </small>
        </div>
    </div>

    <!-- EMOJI ГРУППЫ -->
    <div class="form-section">
        <h3>😊 Контекстные emoji</h3>

        <div class="emoji-groups">
            <div class="emoji-group">
                <label>Эмоциональные (😱❤️🔥)</label>
                <input type="text" name="emoji_emotional" value="<?= htmlspecialchars($formatEmojiGroup($emojiGroups['emotional'] ?? null, $emojiDefaults['emotional'])) ?>" placeholder="😱,😲,❤️,💙,💜,🔥,✨,🌟">
            </div>
            <div class="emoji-group">
                <label>Интригующие (🤔❓🎭)</label>
                <input type="text" name="emoji_intrigue" value="<?= htmlspecialchars($formatEmojiGroup($emojiGroups['intrigue'] ?? null, $emojiDefaults['intrigue'])) ?>" placeholder="🤔,❓,🔍,🎭,🎪,🎨,🌈,⭐">
            </div>
            <div class="emoji-group">
                <label>Атмосферные (🌙🌃💫)</label>
                <input type="text" name="emoji_atmosphere" value="<?= htmlspecialchars($formatEmojiGroup($emojiGroups['atmosphere'] ?? null, $emojiDefaults['atmosphere'])) ?>" placeholder="🌙,🌃,🌌,💫,🌠,🎵,🎶,🎼">
            </div>
            <div class="emoji-group">
                <label>Вопросительные (❓💭💡)</label>
                <input type="text" name="emoji_question" value="<?= htmlspecialchars($formatEmojiGroup($emojiGroups['question'] ?? null, $emojiDefaults['question'])) ?>" placeholder="❓,🤔,💭,💡,🔮,🎯,🎪,🎨">
            </div>
            <div class="emoji-group">
                <label>CTA (👇💬📝)</label>
                <input type="text" name="emoji_cta" value="<?= htmlspecialchars($formatEmojiGroup($emojiGroups['cta'] ?? null, $emojiDefaults['cta'])) ?>" placeholder="👇,💬,📝,✍️,💭,🔥,👍,❤️">
            </div>
        </div>

        <div class="form-help">
            <small>Emoji выбираются автоматически в зависимости от типа описания. Максимум 2 emoji на описание.</small>
        </div>
    </div>

    <!-- ТЕГИ -->
    <div class="form-section">
        <h3>🏷️ Теги</h3>

        <div class="form-group">
            <label>Основные теги (всегда присутствуют)</label>
            <input type="text" name="base_tags" value="<?= htmlspecialchars($baseTagsValue) ?>" placeholder="неон, голос, вокал, атмосфера, музыка" required>
            <small>Эти теги будут в каждом видео</small>
        </div>

        <div class="form-group">
            <label>Вариативные теги (ротация)</label>
            <div id="tagVariants">
                <?php foreach ($tagVariants as $value): ?>
                <div class="variant-item">
                    <input type="text" name="tag_variants[]" value="<?= htmlspecialchars($value) ?>" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addTagVariant" class="btn btn-sm btn-secondary">➕ Добавить вариант тегов</button>
            <small>Из этих наборов выбирается 3-5 тегов для каждого видео</small>
        </div>
    </div>

    <!-- ВОВЛЕЧЁННОСТЬ -->
    <div class="form-section">
        <h3>💬 Вовлечённость</h3>

        <div class="form-group">
            <label>Вопросы для комментариев (рандомизация)</label>
            <div id="questionVariants">
                <?php foreach ($questions as $value): ?>
                <div class="variant-item">
                    <input type="text" name="questions[]" value="<?= htmlspecialchars($value) ?>" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addQuestionVariant" class="btn btn-sm btn-secondary">➕ Добавить вопрос</button>
        </div>

        <div class="form-group">
            <label>Закреплённый комментарий (варианты)</label>
            <div id="pinnedCommentVariants">
                <?php foreach ($pinnedComments as $value): ?>
                <div class="variant-item">
                    <input type="text" name="pinned_comments[]" value="<?= htmlspecialchars($value) ?>" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addPinnedCommentVariant" class="btn btn-sm btn-secondary">➕ Добавить вариант</button>
        </div>

        <div class="form-group">
            <label>Типы CTA (Call to Action)</label>
            <div class="checkbox-grid">
                <label><input type="checkbox" name="cta_types[]" value="subscribe" <?= empty($ctaTypes) || in_array('subscribe', $ctaTypes, true) ? 'checked' : '' ?>> 📺 Подписка на канал</label>
                <label><input type="checkbox" name="cta_types[]" value="playlist" <?= in_array('playlist', $ctaTypes, true) ? 'checked' : '' ?>> 🎵 Просмотр плейлиста</label>
                <label><input type="checkbox" name="cta_types[]" value="like_comment" <?= in_array('like_comment', $ctaTypes, true) ? 'checked' : '' ?>> 👍 Лайк и комментарий</label>
                <label><input type="checkbox" name="cta_types[]" value="link_bio" <?= in_array('link_bio', $ctaTypes, true) ? 'checked' : '' ?>> 🔗 Ссылка в описании</label>
                <label><input type="checkbox" name="cta_types[]" value="next_video" <?= in_array('next_video', $ctaTypes, true) ? 'checked' : '' ?>> ⏭️ Следующее видео</label>
            </div>
        </div>
    </div>

    <!-- НАСТРОЙКИ -->
    <div class="form-section">
        <h3>⚙️ Настройки</h3>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>> Активен
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="enable_ab_testing" value="1" <?= $enableAbTesting ? 'checked' : '' ?>> Включить A/B тестирование названий
            </label>
            <small>Разные видео получат разные варианты названий для сравнения CTR</small>
        </div>
    </div>

    <!-- ВАЛИДАЦИЯ -->
    <div class="form-section">
        <h3>✅ Валидация шаблона</h3>
        <div id="validationResults" class="validation-results">
            <!-- Результаты валидации будут показаны здесь -->
        </div>
        <button type="button" id="validateTemplate" class="btn btn-secondary">🔍 Проверить шаблон</button>
    </div>

    </div> <!-- Закрываем manual_fields -->

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '💾 Сохранить изменения' : '🎯 Создать шаблон' ?></button>
        <button type="button" class="btn btn-outline" onclick="suggestContent()">
            🚀 Предложить контент
        </button>
        <a href="/content-groups/templates" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<style>
.template-form-shorts .form-section {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    background: #fafafa;
}

.template-form-shorts .form-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 0.5rem;
}

.variant-item {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    align-items: center;
}

.variant-item input, .variant-item textarea, .variant-item select {
    flex: 1;
}

.description-variant {
    display: grid;
    grid-template-columns: 200px 1fr 50px;
    gap: 0.5rem;
    align-items: start;
}

.emoji-groups {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.emoji-group {
    background: white;
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid #eee;
}

.emoji-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
}

.checkbox-grid label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.validation-results {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 1rem;
    min-height: 100px;
}

.form-help {
    background: #e7f3ff;
    border-left: 4px solid #007bff;
    padding: 1rem;
    margin-top: 1rem;
}

.validation-warnings {
    margin-top: 0.5rem;
}

.warning-item {
    background: #fff3cd;
    color: #856404;
    padding: 0.5rem;
    margin: 0.25rem 0;
    border-radius: 4px;
    border-left: 4px solid #ffc107;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: normal;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
    width: auto;
}

.auto-gen-field {
    background: #e8f5e8;
    border: 2px solid #28a745;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.auto-gen-field.groq-mode {
    background: #e8e5f5;
    border-color: #7c3aed;
}

.checkbox-label-groq {
    color: #7c3aed;
    font-weight: bold;
}

.btn-groq {
    background: linear-gradient(135deg, #7c3aed, #a855f7);
    color: #fff;
    border: none;
    padding: 0.5rem 1.2rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}
.btn-groq:hover {
    background: linear-gradient(135deg, #6d28d9, #9333ea);
}
.btn-groq:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.groq-badge {
    display: inline-block;
    background: linear-gradient(135deg, #7c3aed, #a855f7);
    color: #fff;
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
    margin-left: 0.5rem;
    vertical-align: middle;
}

/* GigaChat styles */
.auto-gen-field.gigachat-mode {
    background: #e5f3e8;
    border-color: #21a038;
}

.checkbox-label-gigachat {
    color: #21a038;
    font-weight: bold;
}

.btn-gigachat {
    background: linear-gradient(135deg, #21a038, #4eca68);
    color: #fff;
    border: none;
    padding: 0.5rem 1.2rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}
.btn-gigachat:hover {
    background: linear-gradient(135deg, #1a8030, #3db858);
}
.btn-gigachat:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<script>
// Валидация шаблона
document.getElementById('validateTemplate').addEventListener('click', function() {
    validateTemplate();
});

document.getElementById('templateForm').addEventListener('submit', function(e) {
    if (!validateTemplate()) {
        e.preventDefault();
        alert('Исправьте ошибки валидации перед сохранением');
    }
});

// Функции для динамического добавления вариантов
function addVariant(containerId, template, minItems = 1, silent = false) {
    const container = document.getElementById(containerId);
    const items = container.querySelectorAll('.variant-item');

    if (items.length >= 25) { // Максимум 25 вариантов
        if (!silent) {
            alert('Максимум 25 вариантов');
        }
        return;
    }

    const newItem = document.createElement('div');
    newItem.className = 'variant-item';
    newItem.innerHTML = template;

    // Показываем кнопки удаления если больше минимума
    if (items.length >= minItems) {
        items.forEach(item => {
            const removeBtn = item.querySelector('.remove-variant');
            if (removeBtn) removeBtn.style.display = 'block';
        });
    }

    container.appendChild(newItem);
}

function removeVariant(button) {
    const item = button.closest('.variant-item');
    const container = item.parentElement;
    const items = container.querySelectorAll('.variant-item');

    if (items.length <= 1) {
        alert('Нужен минимум 1 вариант');
        return;
    }

    item.remove();

    // Скрываем кнопки удаления если меньше или равно минимуму
    if (items.length <= 2) {
        container.querySelectorAll('.remove-variant').forEach(btn => {
            btn.style.display = 'none';
        });
    }
}

// Добавление вариантов названия
document.getElementById('addTitleVariant').addEventListener('click', function() {
    addVariant('titleVariants',
        '<input type="text" name="title_variants[]" placeholder="Новый вариант названия" required>' +
        '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
        3
    );
});

// Добавление вариантов описания
document.getElementById('addDescriptionVariant').addEventListener('click', function() {
    addVariant('descriptionVariants',
        '<select name="description_types[]" class="description-type" required>' +
            '<option value="">Тип триггера</option>' +
            '<option value="emotional">😱 Эмоция</option>' +
            '<option value="intrigue">🤔 Интрига</option>' +
            '<option value="atmosphere">🌙 Атмосфера</option>' +
            '<option value="question">❓ Вопрос</option>' +
            '<option value="cta">👇 CTA</option>' +
        '</select>' +
        '<textarea name="description_texts[]" rows="2" placeholder="Текст описания" required></textarea>' +
        '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
        1
    );
});

// Добавление вариантов тегов
document.getElementById('addTagVariant').addEventListener('click', function() {
    addVariant('tagVariants',
        '<input type="text" name="tag_variants[]" placeholder="Новый набор тегов" required>' +
        '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
        3
    );
});

// Добавление вопросов
document.getElementById('addQuestionVariant').addEventListener('click', function() {
    addVariant('questionVariants',
        '<input type="text" name="questions[]" placeholder="Новый вопрос для вовлечённости" required>' +
        '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
        3
    );
});

// Добавление закрепленных комментариев
document.getElementById('addPinnedCommentVariant').addEventListener('click', function() {
    addVariant('pinnedCommentVariants',
        '<input type="text" name="pinned_comments[]" placeholder="Новый вариант закрепленного комментария" required>' +
        '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
        2
    );
});

// Валидация шаблона
function validateTemplate() {
    const results = document.getElementById('validationResults');
    const warnings = document.getElementById('titleWarnings');
    const errors = [];
    const warnings_list = [];

    // Проверка названий
    const titleInputs = document.querySelectorAll('input[name="title_variants[]"]');
    const titles = Array.from(titleInputs).map(input => input.value.trim());

    if (titles.length < 3) {
        errors.push('Минимум 3 варианта названий');
    }

    // Проверка на запрещенные слова
    const forbiddenWords = ['часть', 'серия', 'эпизод', 'номер', 'выпуск', '{index}'];
    titles.forEach((title, index) => {
        forbiddenWords.forEach(word => {
            if (title.toLowerCase().includes(word)) {
                errors.push(`Название ${index + 1} содержит запрещенное слово "${word}"`);
            }
        });
    });

    // Проверка одинаковых начал
    const starts = titles.map(title => title.split(' ')[0]?.toLowerCase());
    const startCounts = {};
    starts.forEach(start => {
        startCounts[start] = (startCounts[start] || 0) + 1;
    });

    Object.entries(startCounts).forEach(([start, count]) => {
        if (count > 1) {
            warnings_list.push(`Слово "${start}" используется в начале ${count} названий`);
        }
    });

    // Проверка описаний
    const descriptionTypes = document.querySelectorAll('select[name="description_types[]"]');
    const descriptionTexts = document.querySelectorAll('textarea[name="description_texts[]"]');

    if (descriptionTypes.length < 4) {
        warnings_list.push('Рекомендуется минимум 4 варианта описаний');
    }

    // Проверка уникальности описаний
    const descriptions = Array.from(descriptionTexts).map(textarea => textarea.value.trim());
    const uniqueDescriptions = new Set(descriptions);
    if (uniqueDescriptions.size < descriptions.length) {
        errors.push('Все описания должны быть уникальными');
    }

    // Проверка emoji
    const emojiInputs = document.querySelectorAll('input[name^="emoji_"]');
    emojiInputs.forEach(input => {
        const emojis = input.value.split(',').map(e => e.trim());
        if (emojis.length < 3) {
            warnings_list.push(`Группа "${input.previousElementSibling.textContent}" имеет мало emoji (${emojis.length})`);
        }
    });

    // Вывод результатов
    results.innerHTML = '';

    if (errors.length > 0) {
        results.innerHTML += '<div style="color: #dc3545; font-weight: bold;">❌ Ошибки:</div>';
        errors.forEach(error => {
            results.innerHTML += `<div style="color: #dc3545;">• ${error}</div>`;
        });
    }

    if (warnings_list.length > 0) {
        results.innerHTML += '<div style="color: #856404; font-weight: bold; margin-top: 1rem;">⚠️ Предупреждения:</div>';
        warnings_list.forEach(warning => {
            results.innerHTML += `<div style="color: #856404;">• ${warning}</div>`;
        });
    }

    if (errors.length === 0 && warnings_list.length === 0) {
        results.innerHTML = '<div style="color: #28a745;">✅ Шаблон прошёл валидацию!</div>';
    }

    // Вывод предупреждений для названий
    warnings.innerHTML = '';
    if (warnings_list.some(w => w.includes('начале'))) {
        warnings.innerHTML = warnings_list.filter(w => w.includes('начале')).map(w =>
            `<div class="warning-item">${w}</div>`
        ).join('');
    }

    return errors.length === 0;
}

// Текущий режим автогенерации: 'none', 'template', 'groq', 'gigachat'
let currentAutoGenMode = 'none';

// Функция для переключения режима автогенерации
function toggleAutoGeneration() {
    try {
        const useAutoGen = document.getElementById('use_auto_generation');
        const useGroqAi = document.getElementById('use_groq_ai');
        const useGigaChatAi = document.getElementById('use_gigachat_ai');
        const manualFields = document.getElementById('manual_fields');
        const ideaField = document.getElementById('idea_field');
        const btnTemplate = document.getElementById('btn_generate_template');
        const btnGroq = document.getElementById('btn_generate_groq');
        const btnGigaChat = document.getElementById('btn_generate_gigachat');

        if (!useAutoGen || !useGroqAi || !useGigaChatAi || !manualFields || !ideaField) {
            console.error('toggleAutoGeneration: required elements not found');
            return;
        }

        const templateChecked = useAutoGen.checked;
        const groqChecked = useGroqAi.checked;
        const gigachatChecked = useGigaChatAi.checked;

        // Определяем режим
        if (gigachatChecked) {
            currentAutoGenMode = 'gigachat';
        } else if (groqChecked) {
            currentAutoGenMode = 'groq';
        } else if (templateChecked) {
            currentAutoGenMode = 'template';
        } else {
            currentAutoGenMode = 'none';
        }

        console.log('🔄 Auto-gen mode:', currentAutoGenMode);

        // Скрываем все кнопки по умолчанию
        if (btnTemplate) btnTemplate.style.display = 'none';
        if (btnGroq) btnGroq.style.display = 'none';
        if (btnGigaChat) btnGigaChat.style.display = 'none';
        ideaField.classList.remove('groq-mode', 'gigachat-mode');

        if (currentAutoGenMode === 'none') {
            manualFields.style.display = 'block';
            ideaField.style.display = 'none';
        } else {
            manualFields.style.display = 'none';
            ideaField.style.display = 'block';
            ideaField.style.opacity = '1';
            ideaField.style.visibility = 'visible';

            if (currentAutoGenMode === 'groq') {
                ideaField.classList.add('groq-mode');
                if (btnGroq) btnGroq.style.display = 'inline-block';
            } else if (currentAutoGenMode === 'gigachat') {
                ideaField.classList.add('gigachat-mode');
                if (btnGigaChat) btnGigaChat.style.display = 'inline-block';
            } else {
                if (btnTemplate) btnTemplate.style.display = 'inline-block';
            }
        }
    } catch (error) {
        console.error('toggleAutoGeneration error:', error);
    }
}

// Сброс всех чекбоксов кроме указанного
function uncheckOtherAiCheckboxes(exceptId) {
    const ids = ['use_auto_generation', 'use_groq_ai', 'use_gigachat_ai'];
    ids.forEach(function(id) {
        if (id !== exceptId) {
            const el = document.getElementById(id);
            if (el) el.checked = false;
        }
    });
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    const checkboxTemplate = document.getElementById('use_auto_generation');
    const checkboxGroq = document.getElementById('use_groq_ai');
    const checkboxGigaChat = document.getElementById('use_gigachat_ai');

    if (checkboxTemplate) {
        checkboxTemplate.addEventListener('change', function() {
            if (this.checked) uncheckOtherAiCheckboxes('use_auto_generation');
            toggleAutoGeneration();
        });
    }

    if (checkboxGroq) {
        checkboxGroq.addEventListener('change', function() {
            if (this.checked) uncheckOtherAiCheckboxes('use_groq_ai');
            toggleAutoGeneration();
        });
    }

    if (checkboxGigaChat) {
        checkboxGigaChat.addEventListener('change', function() {
            if (this.checked) uncheckOtherAiCheckboxes('use_gigachat_ai');
            toggleAutoGeneration();
        });
    }

    // Начальное состояние
    toggleAutoGeneration();
});

// Функция для генерации контента из идеи
function generateFromIdea() {
    const idea = document.getElementById('video_idea').value.trim();

    if (!idea || idea.length < 3) {
        alert('Пожалуйста, введите идею минимум 3 символа');
        return;
    }

    console.log('Generating content for idea:', idea);

    // Показываем загрузку
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Генерирую...';
    button.disabled = true;

    // Отправляем запрос
    fetch('/content-groups/templates/suggest-content', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'idea=' + encodeURIComponent(idea) + '&csrf_token=' + document.querySelector('[name="csrf_token"]').value
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Received data:', data);
        if (data.success) {
            // Показываем результат в консоли
            console.log('🎯 Сгенерированный контент:');
            console.log('- Название:', data.content.title_template);
            console.log('- Описание:', data.content.description_template);
            console.log('- Теги:', data.content.tags_template);
            console.log('- Emoji:', data.content.emoji_list);
            console.log('- Тип контента:', data.intent.content_type);
            console.log('- Настроение:', data.intent.mood);

            // Автозаполняем поля
            fillFormWithSuggestion(data);

            // Показываем уведомление с кратким результатом
            const variantsCount = data.content.generated_variants || data.variants_count || 1;
            const titlesCount = data.content.title_variants ? data.content.title_variants.length : 0;
            const preview = `🎯 Сгенерировано ${variantsCount} вариантов контента!\n📝 Заголовков: ${titlesCount}, Описаний: ${data.content.unique_descriptions || 0}\n\nНазвание: "${data.content.title_template}"\nОписание: "${data.content.description_template}"\n\nПосмотрите в консоли (F12) для полного результата!`;
            alert('✅ Контент успешно сгенерирован!\n\n' + preview);
        } else {
            console.error('Server returned error:', data.message);
            alert('❌ Ошибка: ' + (data.message || 'Не удалось сгенерировать контент'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('❌ Произошла ошибка при генерации контента: ' + error.message);
    })
    .finally(() => {
        // Восстанавливаем кнопку
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Функция для генерации контента через Groq AI
function generateFromGroq() {
    const idea = document.getElementById('video_idea').value.trim();

    if (!idea || idea.length < 3) {
        alert('Пожалуйста, введите идею минимум 3 символа');
        return;
    }

    console.log('🤖 Generating content via Groq AI for idea:', idea);

    // Показываем загрузку
    const button = document.getElementById('btn_generate_groq');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ AI генерирует...';
    button.disabled = true;

    // Создаем AbortController для таймаута
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        controller.abort();
        console.warn('⏰ Groq request timed out (60s)');
    }, 60000);

    // Отправляем запрос
    fetch('/content-groups/templates/suggest-content', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'idea=' + encodeURIComponent(idea) +
              '&csrf_token=' + document.querySelector('[name="csrf_token"]').value +
              '&use_groq_ai=1',
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        console.log('📡 Groq response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log('🤖 Groq AI response:', data);
        if (data.success) {
            // Переключаемся на ручной режим для отображения полей
            const checkboxGroq = document.getElementById('use_groq_ai');
            if (checkboxGroq) checkboxGroq.checked = false;
            const checkboxTemplate = document.getElementById('use_auto_generation');
            if (checkboxTemplate) checkboxTemplate.checked = false;
            toggleAutoGeneration();

            // Заполняем форму
            fillFormWithSuggestion(data);

            const variantsCount = data.content.generated_variants || data.variants_count || 1;
            const titlesCount = data.content.title_variants ? data.content.title_variants.length : 0;
            const descriptionsCount = data.content.unique_descriptions || 0;
            alert('🤖 AI GROQ сгенерировал контент!\n' +
                  '📝 Заголовков: ' + titlesCount + '\n' +
                  '📋 Описаний: ' + descriptionsCount + '\n' +
                  '🎯 Всего вариантов: ' + variantsCount + '\n\n' +
                  'Форма заполнена. Проверьте и при необходимости отредактируйте.');
        } else {
            alert('❌ Ошибка Groq AI: ' + (data.message || 'Не удалось сгенерировать'));
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Groq generation error:', error);
        if (error.name === 'AbortError') {
            alert('⏰ AI генерация заняла слишком долго (60 сек). Попробуйте ещё раз.');
        } else {
            alert('❌ Ошибка при обращении к Groq AI: ' + error.message);
        }
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Функция для генерации контента через GigaChat AI (Сбер)
function generateFromGigaChat() {
    const idea = document.getElementById('video_idea').value.trim();

    if (!idea || idea.length < 3) {
        alert('Пожалуйста, введите идею минимум 3 символа');
        return;
    }

    console.log('🧠 Generating content via GigaChat for idea:', idea);

    const button = document.getElementById('btn_generate_gigachat');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ GigaChat генерирует...';
    button.disabled = true;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        controller.abort();
    }, 90000); // 90 сек — GigaChat может быть медленнее

    fetch('/content-groups/templates/suggest-content', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'idea=' + encodeURIComponent(idea) +
              '&csrf_token=' + document.querySelector('[name="csrf_token"]').value +
              '&use_gigachat_ai=1',
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log('🧠 GigaChat response:', data);
        if (data.success) {
            // Переключаемся на ручной режим
            const checkboxGigaChat = document.getElementById('use_gigachat_ai');
            if (checkboxGigaChat) checkboxGigaChat.checked = false;
            const checkboxGroq = document.getElementById('use_groq_ai');
            if (checkboxGroq) checkboxGroq.checked = false;
            const checkboxTemplate = document.getElementById('use_auto_generation');
            if (checkboxTemplate) checkboxTemplate.checked = false;
            toggleAutoGeneration();

            fillFormWithSuggestion(data);

            const variantsCount = data.content.generated_variants || data.variants_count || 1;
            const titlesCount = data.content.title_variants ? data.content.title_variants.length : 0;
            const descriptionsCount = data.content.unique_descriptions || 0;
            alert('🧠 GigaChat (Сбер) сгенерировал контент!\n' +
                  '📝 Заголовков: ' + titlesCount + '\n' +
                  '📋 Описаний: ' + descriptionsCount + '\n' +
                  '🎯 Всего вариантов: ' + variantsCount + '\n\n' +
                  'Форма заполнена. Проверьте и при необходимости отредактируйте.');
        } else {
            alert('❌ Ошибка GigaChat: ' + (data.message || 'Не удалось сгенерировать'));
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('GigaChat generation error:', error);
        if (error.name === 'AbortError') {
            alert('⏰ GigaChat генерация заняла слишком долго (90 сек). Попробуйте ещё раз.');
        } else {
            alert('❌ Ошибка при обращении к GigaChat: ' + error.message);
        }
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Функция для предложения контента через автогенерацию
function suggestContent() {
    const idea = prompt('Введите базовую идею видео (3-7 слов):\n\nПримеры:\n• Девушка поёт под неоном\n• Атмосферный вокал ночью\n• Спокойный голос и неон');

    if (!idea || idea.trim().length < 3) {
        alert('Пожалуйста, введите идею минимум 3 символа');
        return;
    }

    // Показываем загрузку
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Генерирую...';
    button.disabled = true;

    // Отправляем запрос
    console.log('🚀 Начинаем отправку запроса...');
    const csrfToken = document.querySelector('[name="csrf_token"]');
    if (!csrfToken) {
        alert('❌ Ошибка: CSRF токен не найден');
        button.innerHTML = originalText;
        button.disabled = false;
        return;
    }

    // Создаем AbortController для возможности отмены запроса
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        controller.abort();
        console.warn('⏰ Запрос отменен по таймауту (30 сек)');
    }, 30000);

    fetch('/content-groups/templates/suggest-content', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'idea=' + encodeURIComponent(idea.trim()) + '&csrf_token=' + csrfToken.value,
        signal: controller.signal
    })
    .then(response => {
        console.log('📡 Получен ответ сервера:', response.status, response.statusText);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('📦 Разобран JSON ответ:', data);
        if (data.success) {
            console.log('🎯 Начинаем заполнение формы...');
            try {
                // Автозаполняем поля
                fillFormWithSuggestion(data);
                console.log('✅ Форма успешно заполнена');
                const variantsCount = data.content.generated_variants || data.variants_count || 1;
                const titlesCount = data.content.title_variants ? data.content.title_variants.length : 0;
                const descriptionsCount = data.content.unique_descriptions || 0;
                const commentsCount = data.content.pinned_comments ? data.content.pinned_comments.length : 0;
                alert(`✅ Контент успешно сгенерирован и заполнен в форму!\n🎯 Сгенерировано ${variantsCount} вариантов контента\n📝 Заголовков: ${titlesCount}, Описаний: ${descriptionsCount}, Комментариев: ${commentsCount}`);
            } catch (fillError) {
                console.error('💥 Ошибка при заполнении формы:', fillError);
                alert('❌ Контент сгенерирован, но произошла ошибка при заполнении формы: ' + fillError.message);
            }
        } else {
            console.error('❌ Сервер вернул ошибку:', data);
            alert('❌ Ошибка: ' + (data.message || 'Не удалось сгенерировать контент'));
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('💥 Ошибка в процессе генерации:', error);

        if (error.name === 'AbortError') {
            alert('⏰ Время ожидания истекло (30 сек). Попробуйте еще раз.');
        } else {
            alert('❌ Произошла ошибка при генерации контента: ' + error.message);
        }
    })
    .finally(() => {
        clearTimeout(timeoutId);
        // Восстанавливаем кнопку
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Функция для автозаполнения формы предложенными данными
function fillFormWithSuggestion(data) {
    console.log('🎬 fillFormWithSuggestion: Начинаем работу');
    console.log('📦 Полученные данные:', data);

    try {
        const content = data.content;
        if (!content) {
            throw new Error('Данные content отсутствуют в ответе');
        }

        console.log('📝 Начинаем заполнение формы...');
        console.log(`🎯 Всего сгенерировано вариантов: ${data.variants_count || content.generated_variants || 1}`);
        console.log(`📊 Уникальных названий: ${content.unique_titles || 1}`);
        console.log(`📝 Уникальных описаний: ${content.unique_descriptions || 1}`);
        console.log(`🏷️ Уникальных тегов: ${content.unique_tags || 1}`);

        // Основные поля
        const titleVariants = document.querySelectorAll('[name="title_variants[]"]');
        if (titleVariants.length > 0 && content.title_template) {
            titleVariants[0].value = content.title_template;
            console.log('✅ Заполнено название:', content.title_template);
        }

        const descTemplateInput = document.querySelector('[name="description_template"]');
        if (descTemplateInput && content.description_template) {
            descTemplateInput.value = content.description_template;
            console.log('✅ Заполнено описание:', content.description_template);
        }

        const tagsTemplateInput = document.querySelector('[name="tags_template"]');
        if (tagsTemplateInput && content.tags_template) {
            tagsTemplateInput.value = content.tags_template;
            console.log('✅ Заполнены теги:', content.tags_template);
        }

        const emojiListInput = document.querySelector('[name="emoji_list"]');
        if (emojiListInput && content.emoji_list) {
            emojiListInput.value = content.emoji_list;
            console.log('✅ Заполнен emoji:', content.emoji_list);
        }

        const normalizeHookType = (rawType) => {
            if (!rawType) return '';
            const type = String(rawType).toLowerCase();
            if (['emotional', 'intriguing', 'atmospheric', 'visual', 'educational'].includes(type)) {
                return type;
            }
            if (type.includes('emotion') || type.includes('эмоц')) return 'emotional';
            if (type.includes('intrigue') || type.includes('интриг')) return 'intriguing';
            if (type.includes('atmosphere') || type.includes('атмосфер') || type.includes('calm')) return 'atmospheric';
            if (type.includes('visual') || type.includes('визу')) return 'visual';
            if (type.includes('educat') || type.includes('обуч')) return 'educational';
            return '';
        };

        const hookSelect = document.querySelector('[name="hook_type"]');
        if (hookSelect) {
            const derivedHookType =
                normalizeHookType(content.hook_type) ||
                normalizeHookType(content.content_type) ||
                normalizeHookType((data.intent && data.intent.content_type) || '') ||
                normalizeHookType((data.intent && data.intent.mood) || '');

            if (derivedHookType) {
                hookSelect.value = derivedHookType;
                console.log('✅ Установлен основной тип контента:', derivedHookType);
            } else {
                console.warn('⚠️ Не удалось определить основной тип контента');
            }
        }

        // Варианты названий (до 25)
        if (content.title_variants && Array.isArray(content.title_variants)) {
            let titleInputs = document.querySelectorAll('[name="title_variants[]"]');
            const maxTitles = Math.min(content.title_variants.length, 25);

            let attempts = 0;
            while (titleInputs.length < maxTitles && attempts < 30) {
                addVariant('titleVariants',
                    '<input type="text" name="title_variants[]" placeholder="Новый вариант названия" required>' +
                    '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
                    1, true);
                titleInputs = document.querySelectorAll('[name="title_variants[]"]');
                attempts++;
                if (titleInputs.length >= maxTitles) break;
            }

            const updatedTitleInputs = document.querySelectorAll('[name="title_variants[]"]');
            for (let i = 0; i < maxTitles; i++) {
                const variant = content.title_variants[i];
                if (updatedTitleInputs[i] && variant) {
                    updatedTitleInputs[i].value = variant;
                    console.log(`✅ Заполнен вариант названия ${i + 1}:`, variant);
                }
            }
        }

        const normalizeTriggerType = (rawType) => {
            if (!rawType) return '';
            const type = String(rawType).toLowerCase();
            if (type.includes('emotional') || type.includes('эмоци')) return 'emotional';
            if (type.includes('intrigue') || type.includes('интриг')) return 'intrigue';
            if (type.includes('atmosphere') || type.includes('атмосфер')) return 'atmosphere';
            if (type.includes('question') || type.includes('вопрос')) return 'question';
            if (type.includes('cta') || type.includes('призыв')) return 'cta';
            return '';
        };

        const detectTriggerTypeFromText = (text) => {
            if (!text) return '';
            const value = String(text).toLowerCase();
            if (value.includes('?') || value.includes('как ') || value.includes('почему') || value.includes('что если')) {
                return 'question';
            }
            if (value.includes('коммент') || value.includes('лайк') || value.includes('подпиш') || value.includes('расскажи') || value.includes('пиши')) {
                return 'cta';
            }
            if (value.includes('секрет') || value.includes('угада') || value.includes('интриг') || value.includes('знаешь')) {
                return 'intrigue';
            }
            if (value.includes('атмосфер') || value.includes('спокой') || value.includes('ноч') || value.includes('неон') || value.includes('настро')) {
                return 'atmosphere';
            }
            if (value.includes('мураш') || value.includes('слез') || value.includes('восторг') || value.includes('эмоци')) {
                return 'emotional';
            }
            return '';
        };

        // Варианты описаний (до 25)
        if (content.description_variants) {
            let totalVariants = 0;
            Object.entries(content.description_variants).forEach(([type, variants]) => {
                if (Array.isArray(variants)) {
                    totalVariants += variants.length;
                }
            });
            totalVariants = Math.min(totalVariants, 25);

            let descInputs = document.querySelectorAll('[name="description_texts[]"]');
            let descAttempts = 0;
            while (descInputs.length < totalVariants && descAttempts < 30) {
                addVariant('descriptionVariants',
                    '<select name="description_types[]" class="description-type" required>' +
                        '<option value="">Тип триггера</option>' +
                        '<option value="emotional">😱 Эмоция</option>' +
                        '<option value="intrigue">🤔 Интрига</option>' +
                        '<option value="atmosphere">🌙 Атмосфера</option>' +
                        '<option value="question">❓ Вопрос</option>' +
                        '<option value="cta">👇 CTA</option>' +
                    '</select>' +
                    '<textarea name="description_texts[]" rows="2" placeholder="Текст описания" required></textarea>' +
                    '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
                    1, true);
                descInputs = document.querySelectorAll('[name="description_texts[]"]');
                descAttempts++;
                if (descInputs.length >= totalVariants) break;
            }

            let descIndex = 0;
            const updatedDescTypes = document.querySelectorAll('[name="description_types[]"]');
            const updatedDescTexts = document.querySelectorAll('[name="description_texts[]"]');

            Object.entries(content.description_variants).forEach(([type, variants]) => {
                if (Array.isArray(variants)) {
                    variants.forEach(variant => {
                        if (descIndex < totalVariants && descIndex < updatedDescTypes.length && descIndex < updatedDescTexts.length) {
                            if (updatedDescTypes[descIndex]) {
                                const mappedType = normalizeTriggerType(type);
                                const inferredType = detectTriggerTypeFromText(variant);
                                const finalType = mappedType || inferredType || 'atmosphere';
                                updatedDescTypes[descIndex].value = finalType;
                                if (!mappedType && !inferredType) {
                                    console.warn('⚠️ Не удалось определить тип триггера, использован atmosphere');
                                }
                            }
                            if (updatedDescTexts[descIndex]) updatedDescTexts[descIndex].value = variant;
                            console.log(`✅ Заполнен вариант описания ${descIndex + 1} (${type}):`, variant);
                            descIndex++;
                        }
                    });
                }
            });
        }

        // Emoji группы
        if (content.emoji_groups) {
            Object.entries(content.emoji_groups).forEach(([type, emojis]) => {
                const inputName = `emoji_${type}`;
                const input = document.querySelector(`[name="${inputName}"]`);
                if (input && Array.isArray(emojis)) {
                    input.value = emojis.join(', ');
                }
            });
        }

        // Остальные поля
        const baseTagsInput = document.querySelector('[name="base_tags"]');
        if (baseTagsInput && content.base_tags) {
            baseTagsInput.value = content.base_tags;
        }

        const questionsInput = document.querySelector('[name="questions"]');
        if (questionsInput && content.questions && Array.isArray(content.questions)) {
            questionsInput.value = content.questions.join('\n');
        }

        if (content.pinned_comments && Array.isArray(content.pinned_comments)) {
            let pinnedInputs = document.querySelectorAll('[name="pinned_comments[]"]');
            const maxComments = Math.min(content.pinned_comments.length, 25);

            let pinnedAttempts = 0;
            while (pinnedInputs.length < maxComments && pinnedAttempts < 30) {
                addVariant('pinnedCommentVariants',
                    '<input type="text" name="pinned_comments[]" placeholder="Новый вариант закрепленного комментария" required>' +
                    '<button type="button" class="btn btn-sm btn-danger remove-variant" onclick="removeVariant(this)">❌</button>',
                    1, true);
                pinnedInputs = document.querySelectorAll('[name="pinned_comments[]"]');
                pinnedAttempts++;
                if (pinnedInputs.length >= maxComments) break;
            }

            const updatedPinnedInputs = document.querySelectorAll('[name="pinned_comments[]"]');
            for (let i = 0; i < maxComments; i++) {
                const comment = content.pinned_comments[i];
                if (updatedPinnedInputs[i] && comment) {
                    updatedPinnedInputs[i].value = comment;
                    console.log(`✅ Заполнен закрепленный комментарий ${i + 1}:`, comment);
                }
            }
        }

        const focusPointsInput = document.querySelector('[name="focus_points"]');
        if (focusPointsInput && content.focus_points && Array.isArray(content.focus_points)) {
            focusPointsInput.value = JSON.stringify(content.focus_points);
        }

        const nameInput = document.querySelector('[name="name"]');
        if (nameInput && data.idea) {
            nameInput.value = `Auto: ${data.idea}`;
            console.log('✅ Обновлено название шаблона');
        }

        const descriptionInput = document.querySelector('[name="description"]');
        if (descriptionInput && data.idea) {
            descriptionInput.value = `Автоматически сгенерированный шаблон для: ${data.idea}`;
            console.log('✅ Обновлено описание шаблона');
        }

        console.log('✅ Форма успешно заполнена сгенерированным контентом!');
        console.log('🔍 Проверьте поля формы - они должны быть заполнены автоматически.');
    } catch (error) {
        console.error('💥 Ошибка в fillFormWithSuggestion:', error);
        console.error('Stack trace:', error.stack);
        throw error;
    }
}

// Автоматическая валидация при изменении полей
document.addEventListener('input', function(e) {
    if (e.target.name === 'title_variants[]') {
        // Задержка валидации для производительности
        clearTimeout(window.validationTimeout);
        window.validationTimeout = setTimeout(validateTemplate, 500);
    }
});
</script>

<?php
try {
    // Проверяем, что буфер активен (должен быть начат на строке 118)
    $bufferLevel = ob_get_level();
    error_log("Templates create_v2 view: Buffer level before ob_get_clean: {$bufferLevel}");
    
    if ($bufferLevel === 0) {
        error_log("Templates create_v2 view: ERROR - No active output buffer! This should not happen.");
        // В критической ситуации создаем минимальный контент
        $content = '<div class="alert alert-error">Ошибка: буфер вывода не был инициализирован</div>';
    } else {
        $content = ob_get_clean();
        if ($content === false || $content === '') {
            error_log("Templates create_v2 view: WARNING - ob_get_clean returned false or empty (buffer level was: {$bufferLevel})");
            $content = '<div class="alert alert-error">Ошибка при загрузке содержимого</div>';
        }
    }
    
    // Убеждаемся, что переменные для layout определены
    if (!isset($title)) {
        $title = 'Создать шаблон Shorts';
    }
    
    // Убеждаемся, что сессия доступна для layout
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $layoutPath = __DIR__ . '/../../layout.php';
    if (!file_exists($layoutPath)) {
        error_log("Templates create_v2 view: Layout file not found: {$layoutPath}");
        error_log("Templates create_v2 view: Current directory: " . __DIR__);
        error_log("Templates create_v2 view: Absolute layout path: " . realpath($layoutPath));
        error_log("Templates create_v2 view: File exists check: " . (file_exists($layoutPath) ? 'yes' : 'no'));
        http_response_code(500);
        echo "Layout file not found. Please check server logs.";
        exit;
    }
    
    error_log("Templates create_v2 view: Including layout from: {$layoutPath}");
    // Включаем layout - он должен вывести $content
    include $layoutPath;
    // После включения layout завершаем выполнение
    error_log("Templates create_v2 view: Layout included successfully, exiting");
    exit;
} catch (\Throwable $e) {
    error_log("Templates create_v2 view: Fatal error: " . $e->getMessage());
    error_log("Templates create_v2 view: Error file: " . $e->getFile() . ":" . $e->getLine());
    error_log("Templates create_v2 view: Stack trace: " . $e->getTraceAsString());
    
    // Очищаем все буферы
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    http_response_code(500);
    echo "Fatal error loading template creation page. Please check server logs.";
    exit;
}
?>