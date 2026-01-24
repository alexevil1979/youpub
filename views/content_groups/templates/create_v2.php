<?php
$title = 'Создать шаблон Shorts (улучшенный)';
ob_start();
?>

<h1>🎯 Создать шаблон для YouTube Shorts</h1>

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

<form method="POST" action="/content-groups/templates/create-shorts" class="template-form-shorts" id="templateForm">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <!-- ОСНОВНАЯ ИНФОРМАЦИЯ -->
    <div class="form-section">
        <h3>📋 Основная информация</h3>

        <div class="form-group">
            <label for="name">Название шаблона *</label>
            <input type="text" id="name" name="name" required placeholder="Например: Неон + Голос (Эмоциональный)">
            <small>Уникальное название для идентификации шаблона</small>
        </div>

        <div class="form-group">
            <label for="description">Описание шаблона</label>
            <textarea id="description" name="description" rows="2" placeholder="Для чего используется этот шаблон"></textarea>
        </div>

        <!-- Переключатель автогенерации -->
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" id="use_auto_generation" name="use_auto_generation">
                🚀 Использовать автогенерацию контента
            </label>
            <small>Автоматически сгенерировать контент из одной идеи вместо ручного заполнения</small>
        </div>

        <!-- Поле для идеи (скрыто по умолчанию) -->
        <div class="form-group auto-gen-field" id="idea_field" style="display: none;">
            <label for="video_idea">💡 Базовая идея видео *</label>
            <input type="text" id="video_idea" name="video_idea" placeholder="Например: Девушка поёт под неоном" maxlength="100">
            <small>Опишите суть видео в 3-7 словах</small>
            <button type="button" class="btn btn-secondary" onclick="generateFromIdea()" style="margin-top: 0.5rem;">
                🎯 Сгенерировать контент
            </button>
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
                <option value="emotional">😱 Эмоциональный (мурашки, слезы, восторг)</option>
                <option value="intriguing">🤔 Интригующий (секрет, загадка, интрига)</option>
                <option value="atmospheric">🌙 Атмосферный (настроение, атмосфера, чувство)</option>
                <option value="visual">🎨 Визуальный (красиво, эстетика, цвета)</option>
                <option value="educational">📚 Образовательный (узнаешь, откроешь, поймешь)</option>
            </select>
            <small>Определяет стиль подачи контента</small>
        </div>

        <div class="form-group">
            <label>Фокус видео (можно выбрать несколько)</label>
            <div class="checkbox-grid">
                <label><input type="checkbox" name="focus_points[]" value="voice"> 🎤 Голос/вокал</label>
                <label><input type="checkbox" name="focus_points[]" value="neon"> 💡 Неоновые огни/цвета</label>
                <label><input type="checkbox" name="focus_points[]" value="atmosphere"> 🌫️ Атмосфера/настроение</label>
                <label><input type="checkbox" name="focus_points[]" value="effects"> ✨ Визуальные эффекты</label>
                <label><input type="checkbox" name="focus_points[]" value="combination"> 🔄 Комбинация всего</label>
            </div>
        </div>
    </div>

    <!-- ШАБЛОН НАЗВАНИЯ -->
    <div class="form-section">
        <h3>📝 Варианты названий (A/B тестирование)</h3>

        <div class="form-group">
            <div id="titleVariants">
                <div class="variant-item">
                    <input type="text" name="title_variants[]" placeholder="Вариант 1: Неон + голос = мурашки по коже" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <div class="variant-item">
                    <input type="text" name="title_variants[]" placeholder="Вариант 2: Этот вокал заставляет светиться ярче" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <div class="variant-item">
                    <input type="text" name="title_variants[]" placeholder="Вариант 3: Когда голос встречает неоновый свет" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
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
                <div class="variant-item description-variant">
                    <select name="description_types[]" class="description-type" required>
                        <option value="">Тип триггера</option>
                        <option value="emotional">😱 Эмоция</option>
                        <option value="intrigue">🤔 Интрига</option>
                        <option value="atmosphere">🌙 Атмосфера</option>
                        <option value="question">❓ Вопрос</option>
                        <option value="cta">👇 CTA</option>
                    </select>
                    <textarea name="description_texts[]" rows="2" placeholder="Текст описания (1-2 строки)" required></textarea>
                    <button type="button" class="btn btn-sm btn-danger remove-variant">❌</button>
                </div>
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
                <input type="text" name="emoji_emotional" value="😱,😲,❤️,💙,💜,🔥,✨,🌟" placeholder="😱,😲,❤️,💙,💜,🔥,✨,🌟">
            </div>
            <div class="emoji-group">
                <label>Интригующие (🤔❓🎭)</label>
                <input type="text" name="emoji_intrigue" value="🤔,❓,🔍,🎭,🎪,🎨,🌈,⭐" placeholder="🤔,❓,🔍,🎭,🎪,🎨,🌈,⭐">
            </div>
            <div class="emoji-group">
                <label>Атмосферные (🌙🌃💫)</label>
                <input type="text" name="emoji_atmosphere" value="🌙,🌃,🌌,💫,🌠,🎵,🎶,🎼" placeholder="🌙,🌃,🌌,💫,🌠,🎵,🎶,🎼">
            </div>
            <div class="emoji-group">
                <label>Вопросительные (❓💭💡)</label>
                <input type="text" name="emoji_question" value="❓,🤔,💭,💡,🔮,🎯,🎪,🎨" placeholder="❓,🤔,💭,💡,🔮,🎯,🎪,🎨">
            </div>
            <div class="emoji-group">
                <label>CTA (👇💬📝)</label>
                <input type="text" name="emoji_cta" value="👇,💬,📝,✍️,💭,🔥,👍,❤️" placeholder="👇,💬,📝,✍️,💭,🔥,👍,❤️">
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
            <input type="text" name="base_tags" value="неон, голос, вокал, атмосфера, музыка" placeholder="неон, голос, вокал, атмосфера, музыка" required>
            <small>Эти теги будут в каждом видео</small>
        </div>

        <div class="form-group">
            <label>Вариативные теги (ротация)</label>
            <div id="tagVariants">
                <div class="variant-item">
                    <input type="text" name="tag_variants[]" value="неоновые огни, женский вокал, эмоции" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <div class="variant-item">
                    <input type="text" name="tag_variants[]" value="красный неон, спокойная музыка, чувства" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <div class="variant-item">
                    <input type="text" name="tag_variants[]" value="синий неон, уникальный голос, настроение" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
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
                <div class="variant-item">
                    <input type="text" name="questions[]" value="Какое сочетание цветов тебе больше всего понравилось?" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <div class="variant-item">
                    <input type="text" name="questions[]" value="Чувствовал ли ты мурашки от голоса?" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <div class="variant-item">
                    <input type="text" name="questions[]" value="Какая часть видео тебя зацепила больше всего?" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
            </div>
            <button type="button" id="addQuestionVariant" class="btn btn-sm btn-secondary">➕ Добавить вопрос</button>
        </div>

        <div class="form-group">
            <label>Закреплённый комментарий (варианты)</label>
            <div id="pinnedCommentVariants">
                <div class="variant-item">
                    <input type="text" name="pinned_comments[]" value="🎵 Слушай плейлист в моём профиле" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
                <div class="variant-item">
                    <input type="text" name="pinned_comments[]" value="🔥 Все видео этой серии здесь 👇" required>
                    <button type="button" class="btn btn-sm btn-danger remove-variant" style="display: none;">❌</button>
                </div>
            </div>
            <button type="button" id="addPinnedCommentVariant" class="btn btn-sm btn-secondary">➕ Добавить вариант</button>
        </div>

        <div class="form-group">
            <label>Типы CTA (Call to Action)</label>
            <div class="checkbox-grid">
                <label><input type="checkbox" name="cta_types[]" value="subscribe" checked> 📺 Подписка на канал</label>
                <label><input type="checkbox" name="cta_types[]" value="playlist"> 🎵 Просмотр плейлиста</label>
                <label><input type="checkbox" name="cta_types[]" value="like_comment"> 👍 Лайк и комментарий</label>
                <label><input type="checkbox" name="cta_types[]" value="link_bio"> 🔗 Ссылка в описании</label>
                <label><input type="checkbox" name="cta_types[]" value="next_video"> ⏭️ Следующее видео</label>
            </div>
        </div>
    </div>

    <!-- НАСТРОЙКИ -->
    <div class="form-section">
        <h3>⚙️ Настройки</h3>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" checked> Активен
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="enable_ab_testing" value="1" checked> Включить A/B тестирование названий
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
        <button type="submit" class="btn btn-primary">🎯 Создать шаблон</button>
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

// Функция для переключения режима автогенерации
function toggleAutoGeneration() {
    try {
        console.log('🔄 toggleAutoGeneration called');
        const useAutoGen = document.getElementById('use_auto_generation');
        const manualFields = document.getElementById('manual_fields');
        const ideaField = document.getElementById('idea_field');

        if (!useAutoGen) {
            console.error('❌ Element use_auto_generation not found');
            return;
        }
        if (!manualFields) {
            console.error('❌ Element manual_fields not found');
            return;
        }
        if (!ideaField) {
            console.error('❌ Element idea_field not found');
            return;
        }

        const checked = useAutoGen.checked;
        console.log('📋 Auto generation checked:', checked);
        console.log('📋 Current displays before change:', {
            manualFields: manualFields.style.display,
            ideaField: ideaField.style.display
        });

        if (checked) {
            manualFields.style.display = 'none';
            ideaField.style.display = 'block';
            ideaField.style.opacity = '1';
            ideaField.style.visibility = 'visible';
            console.log('✅ Switched to auto-generation mode');
            console.log('📋 New displays:', {
                manualFields: manualFields.style.display,
                ideaField: ideaField.style.display,
                ideaFieldOpacity: ideaField.style.opacity,
                ideaFieldVisibility: ideaField.style.visibility
            });
            // Добавим визуальную индикацию
            ideaField.style.border = '2px solid #007bff';
            setTimeout(() => { ideaField.style.border = ''; }, 1000);
        } else {
            manualFields.style.display = 'block';
            manualFields.style.opacity = '1';
            manualFields.style.visibility = 'visible';
            ideaField.style.display = 'none';
            console.log('✅ Switched to manual mode');
            console.log('📋 New displays:', {
                manualFields: manualFields.style.display,
                ideaField: ideaField.style.display,
                manualFieldsOpacity: manualFields.style.opacity,
                manualFieldsVisibility: manualFields.style.visibility
            });
            // Добавим визуальную индикацию
            manualFields.style.border = '2px solid #28a745';
            setTimeout(() => { manualFields.style.border = ''; }, 1000);
        }
    } catch (error) {
        console.error('💥 Error in toggleAutoGeneration:', error);
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Page loaded, initializing toggleAutoGeneration');
    console.log('📋 Current URL:', window.location.href);

    const checkbox = document.getElementById('use_auto_generation');
    const manualFields = document.getElementById('manual_fields');
    const ideaField = document.getElementById('idea_field');

    console.log('🔍 Elements found:', {
        checkbox: !!checkbox,
        manualFields: !!manualFields,
        ideaField: !!ideaField
    });

    if (checkbox) {
        // Добавляем обработчик события
        checkbox.addEventListener('change', function() {
            console.log('📝 Checkbox changed, checked:', checkbox.checked, 'calling toggleAutoGeneration');
            toggleAutoGeneration();
        });

        // Также добавим обработчик клика для надежности
        checkbox.addEventListener('click', function() {
            console.log('🖱️ Checkbox clicked, checked:', checkbox.checked);
            // Небольшая задержка чтобы дать браузеру обновить состояние
            setTimeout(() => {
                console.log('🖱️ After timeout, checked:', checkbox.checked);
                toggleAutoGeneration();
            }, 10);
        });
        // Вызываем функцию один раз при загрузке для корректного начального состояния
        toggleAutoGeneration();
        console.log('✅ toggleAutoGeneration initialized');
    } else {
        console.error('❌ use_auto_generation checkbox not found');
    }
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

        if (content.hook_type) {
            const hookSelect = document.querySelector('[name="hook_type"]');
            if (hookSelect) {
                hookSelect.value = content.hook_type;
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
                                updatedDescTypes[descIndex].value = mappedType || '';
                                if (!mappedType) {
                                    console.warn('⚠️ Неизвестный тип триггера:', type);
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
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>