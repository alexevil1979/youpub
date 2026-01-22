/**
 * Глобальный поиск по админке
 */
(function() {
    const searchInput = document.getElementById('global-search-input');
    const searchResults = document.getElementById('search-results');
    let searchTimeout = null;
    let currentHighlight = -1;
    let currentResults = [];

    if (!searchInput || !searchResults) {
        return;
    }

    // Обработка ввода
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            hideResults();
            return;
        }

        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Обработка фокуса
    searchInput.addEventListener('focus', function() {
        if (currentResults.length > 0) {
            showResults();
        }
    });

    // Обработка клика вне поиска
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            hideResults();
        }
    });

    // Обработка клавиатуры
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightNext();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightPrevious();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            selectHighlighted();
        } else if (e.key === 'Escape') {
            hideResults();
            searchInput.blur();
        }
    });

    /**
     * Выполнить поиск
     */
    function performSearch(query) {
        fetch('/search?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.results) {
                    currentResults = data.data.results;
                    renderResults(currentResults);
                } else {
                    currentResults = [];
                    renderResults([]);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                currentResults = [];
                renderResults([]);
            });
    }

    /**
     * Отобразить результаты
     */
    function renderResults(results) {
        currentHighlight = -1;
        searchResults.innerHTML = '';

        if (results.length === 0) {
            searchResults.classList.add('empty');
            showResults();
            return;
        }

        searchResults.classList.remove('empty');

        results.forEach((result, index) => {
            const item = document.createElement('a');
            item.href = result.url;
            item.className = 'search-result-item';
            item.dataset.index = index;

            item.innerHTML = `
                <span class="search-result-icon">${result.icon}</span>
                <div class="search-result-content">
                    <div class="search-result-title">${escapeHtml(result.title)}</div>
                    ${result.description ? `<div class="search-result-description">${escapeHtml(result.description)}</div>` : ''}
                    <div class="search-result-type">${getTypeLabel(result.type)}</div>
                </div>
            `;

            item.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = result.url;
            });

            item.addEventListener('mouseenter', function() {
                currentHighlight = index;
                updateHighlight();
            });

            searchResults.appendChild(item);
        });

        showResults();
    }

    /**
     * Показать результаты
     */
    function showResults() {
        searchResults.classList.add('active');
    }

    /**
     * Скрыть результаты
     */
    function hideResults() {
        searchResults.classList.remove('active');
    }

    /**
     * Выделить следующий элемент
     */
    function highlightNext() {
        if (currentResults.length === 0) return;
        currentHighlight = (currentHighlight + 1) % currentResults.length;
        updateHighlight();
        scrollToHighlighted();
    }

    /**
     * Выделить предыдущий элемент
     */
    function highlightPrevious() {
        if (currentResults.length === 0) return;
        currentHighlight = currentHighlight <= 0 ? currentResults.length - 1 : currentHighlight - 1;
        updateHighlight();
        scrollToHighlighted();
    }

    /**
     * Обновить выделение
     */
    function updateHighlight() {
        const items = searchResults.querySelectorAll('.search-result-item');
        items.forEach((item, index) => {
            if (index === currentHighlight) {
                item.classList.add('highlighted');
            } else {
                item.classList.remove('highlighted');
            }
        });
    }

    /**
     * Прокрутить к выделенному элементу
     */
    function scrollToHighlighted() {
        const items = searchResults.querySelectorAll('.search-result-item');
        if (items[currentHighlight]) {
            items[currentHighlight].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    /**
     * Выбрать выделенный элемент
     */
    function selectHighlighted() {
        if (currentHighlight >= 0 && currentResults[currentHighlight]) {
            window.location.href = currentResults[currentHighlight].url;
        }
    }

    /**
     * Получить метку типа
     */
    function getTypeLabel(type) {
        const labels = {
            'video': 'Видео',
            'group': 'Группа',
            'schedule': 'Расписание',
            'template': 'Шаблон',
            'publication': 'Публикация',
        };
        return labels[type] || type;
    }

    /**
     * Экранирование HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
