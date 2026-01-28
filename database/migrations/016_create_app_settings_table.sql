-- Миграция 016: Глобальные настройки приложения (app_settings)
--
-- Хранит ключ-значение настройки для всего проекта:
-- - session_lifetime_seconds      (int)   время жизни сессии в секундах
-- - session_strict_ip             (bool)  строгая привязка сессии к IP
-- - site_name                     (string) имя проекта / бренда
-- - site_url                      (string) базовый URL (канонический домен)
-- - seo_title_suffix              (string) суффикс для <title> (например, " | YouPub")
-- - seo_meta_description_default  (string) дефолтное meta description

CREATE TABLE IF NOT EXISTS `app_settings` (
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

