// Основной JavaScript файл

document.addEventListener('DOMContentLoaded', function() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (csrfToken) {
        // Добавляем CSRF токен во все POST формы
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method === 'POST' && !form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = csrfToken;
                form.appendChild(input);
            }
        });

        // Добавляем CSRF токен во все fetch запросы
        const originalFetch = window.fetch.bind(window);
        window.fetch = function(resource, options = {}) {
            const init = options || {};
            const headers = new Headers(init.headers || {});
            const method = (init.method || 'GET').toUpperCase();
            const url = typeof resource === 'string' ? resource : (resource && resource.url ? resource.url : '');
            const isSameOrigin = url.startsWith('/') || url.startsWith(window.location.origin);

            if (isSameOrigin && !['GET', 'HEAD', 'OPTIONS'].includes(method) && !headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', csrfToken);
            }

            return originalFetch(resource, Object.assign({}, init, { headers }));
        };
    }

    // Автоматическое скрытие алертов через 5 секунд
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Валидация форм (базовая)
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('field-error');
                } else {
                    field.classList.remove('field-error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                showToast('Пожалуйста, заполните все обязательные поля', 'error');
            }
        });
    });

    // Переключение сайдбара на мобильных
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.style.display = 'inline-flex';
        sidebarToggle.addEventListener('click', function() {
            const isOpen = document.body.classList.toggle('sidebar-open');
            sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }
});

/**
 * Глобальная функция для toast-уведомлений, чтобы переиспользовать её на страницах.
 */
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
