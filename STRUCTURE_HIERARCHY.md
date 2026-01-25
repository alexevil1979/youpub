структура иерархия проекта

ядро системы находится в core :
Auth, Controller, Router, Repository, Database, Service.

основная бизнес логика находится в app :
Controllers, Services, Repositories, Middlewares.

модуль контент групп находится в app/Modules/ContentGroups :
Controllers, Services, Repositories, Views связаны с группами, шаблонами и умными расписаниями.

представления находятся в views :
layout, auth, dashboard, videos, schedules, content_groups, admin.

маршруты находятся в routes :
web.php, api.php, admin.php.

статические ресурсы находятся в assets :
css, js.

cron задачи находятся в cron :
publish.sh, stats.sh.

воркеры находятся в workers :
publish_worker, smart_publish_worker, stats_worker, thumbnail_worker, emergency_stop.

конфигурация находится в config :
env.example.php и env.php.

миграции и схема базы находятся в database :
migrations, schema.sql, migrate.php.

служебные скрипты находятся в scripts :
reset_admin_password.php.

файлы загрузок и логов находятся в storage :
uploads, logs.

тестовые скрипты находятся в корне :
test_*.php

функционал и зависимости файлов групп расписаний шаблонов

группы контента (content groups)
- контроллеры:
  app/Modules/ContentGroups/Controllers/GroupController.php
  app/Modules/ContentGroups/Controllers/AutoShortsController.php
- сервисы:
  app/Modules/ContentGroups/Services/GroupService.php
  app/Modules/ContentGroups/Services/TemplateService.php
  app/Modules/ContentGroups/Services/AutoShortsGenerator.php
  app/Modules/ContentGroups/Services/SmartQueueService.php
  app/Modules/ContentGroups/Services/ScheduleEngineService.php
- репозитории:
  app/Modules/ContentGroups/Repositories/ContentGroupRepository.php
  app/Modules/ContentGroups/Repositories/ContentGroupFileRepository.php
  app/Modules/ContentGroups/Repositories/PublicationTemplateRepository.php
- представления:
  views/content_groups/index.php
  views/content_groups/show.php
  views/content_groups/create.php
  views/content_groups/edit.php
- функции:
  создание и управление группой, добавление/удаление файлов,
  показ статистики группы, управление статусом,
  автогенерация контента для шаблонов.
- зависимости:
  использует VideoRepository, ScheduleRepository, PublicationRepository,
  TemplateService, Auth, Router, layout.

расписания (schedules)
- контроллеры:
  app/Controllers/ScheduleController.php
  app/Modules/ContentGroups/Controllers/SmartScheduleController.php
- сервисы:
  app/Services/ScheduleService.php
  app/Modules/ContentGroups/Services/ScheduleEngineService.php
- репозитории:
  app/Repositories/ScheduleRepository.php
- воркеры:
  workers/publish_worker.php
  workers/smart_publish_worker.php
- cron:
  cron/publish.sh
- представления:
  views/schedules/index.php
  views/schedules/show.php
  views/schedules/create.php
  views/schedules/edit.php
  views/content_groups/schedules/index.php
  views/content_groups/schedules/create.php
  views/content_groups/schedules/edit.php
  views/content_groups/schedules/show.php
- функции:
  создание/редактирование расписаний, планирование публикаций,
  управление статусами (pending, processing, published, failed, paused),
  расчёт следующих публикаций, запуск публикаций через воркеры.
- зависимости:
  VideoRepository, ContentGroupRepository, TemplateService,
  YoutubeService, TelegramService, SmartQueueService.

шаблоны (templates)
- контроллеры:
  app/Modules/ContentGroups/Controllers/TemplateController.php
  app/Modules/ContentGroups/Controllers/AutoShortsController.php
- сервисы:
  app/Modules/ContentGroups/Services/TemplateService.php
  app/Modules/ContentGroups/Services/AutoShortsGenerator.php
- репозитории:
  app/Modules/ContentGroups/Repositories/PublicationTemplateRepository.php
- представления:
  views/content_groups/templates/index.php
  views/content_groups/templates/create.php
  views/content_groups/templates/create_v2.php
  views/content_groups/templates/edit.php
- функции:
  создание и редактирование шаблонов, предпросмотр,
  автогенерация вариантов по идее, хранение наборов полей.
- зависимости:
  GroupService, ScheduleService, Auth, Router, layout.
