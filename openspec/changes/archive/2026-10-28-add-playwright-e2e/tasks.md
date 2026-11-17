# Tasks

## 1. Playwright на хості

- [x] 1.1 Виконати на хості `npm i -D @playwright/test` та `npx playwright install chromium`; перевірка: `npx playwright --version` працює, залежність є в `package.json`, `package-lock.json` оновлено
- [x] 1.2 Створити `playwright.config.js` з `testDir: './e2e'`, `baseURL: 'http://localhost:8080'`, одним проєктом Chromium і без `webServer`, з JSDoc англійською; перевірка: `npx playwright test --list` не повертає помилок конфігу
- [x] 1.3 Додати скрипт `test:e2e` у `package.json` та артефакти Playwright (`test-results/`, `playwright-report/`) у `.gitignore`; перевірка: `npm run test:e2e` запускає Playwright

## 2. Smoke-тест і документація тестів

- [x] 2.1 Додати `e2e/smoke.spec.js`, що відкриває `/` і перевіряє статус нижче 400, з коментарями англійською; перевірка: `npm run test:e2e` проходить при запущених контейнерах
- [x] 2.2 Перевірити розділення з PHPUnit; перевірка: `docker compose exec app php artisan test` не запускає файли з `e2e/`
- [x] 2.3 Створити `docs/e2e-testing.md` українською (передумови, команди, структура `e2e/`, помилка при зупинених контейнерах) і `docs/README.md` з індексом; перевірка: усі відносні посилання працюють, команди з документа виконуються як написано

## 3. Правила проєкту

- [x] 3.1 Додати в `CLAUDE.md` розділ про Playwright (запуск з хоста, база `http://localhost:8080`, тести в `e2e/`, лише Chromium); перевірка: розділ не дублює Docker-правила і посилається на `docs/e2e-testing.md`
- [x] 3.2 Додати в `openspec/config.yaml` поле `context` (Laravel в Docker, URL `http://localhost:8080`, npm тільки на хості, Playwright) і правило для `tasks` про PHP-команди через `docker compose exec app`; перевірка: `openspec context --json` не повертає помилок, YAML парситься
- [x] 3.3 Змінити `APP_URL` у `.env.example` на `http://localhost:8080`; перевірка: `grep APP_URL .env.example` показує нове значення

## 4. Інтеграційна перевірка

- [x] 4.1 Повний прогін: `docker compose ps` показує запущені контейнери, `npm run test:e2e` проходить, `openspec validate add-playwright-e2e` без помилок
