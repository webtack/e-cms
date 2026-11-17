# Proposal

## Why

Проєкт працює в Docker (nginx на `http://localhost:8080`), але в ньому немає браузерного тестування: PHPUnit у `tests/` не перевіряє UI. Потрібен e2e-інструмент, який працює з уже запущеним застосунком, не ламаючи правило «npm тільки на хості».

## What Changes

- Додати `@playwright/test` як dev-залежність на хості та встановити лише браузер Chromium.
- Додати `playwright.config.js` з `baseURL` `http://localhost:8080`, без власного `webServer` (застосунок уже запущено в Docker).
- Тести e2e лежать в `e2e/`, окремо від PHPUnit у `tests/`; додати скрипт `test:e2e` у `package.json` і мінімальний smoke-тест.
- Додати розділ про Playwright у `CLAUDE.md` (запуск з хоста, база `http://localhost:8080`, тести в `e2e/`).
- Додати `context` у `openspec/config.yaml` (Laravel в Docker, URL, npm на хості, Playwright) і правило для `tasks`: PHP-команди йдуть через `docker compose exec app`.
- Змінити `APP_URL` у `.env.example` на `http://localhost:8080`.
- Додати документацію українською: `docs/e2e-testing.md` та індекс `docs/README.md`.

## Capabilities

### New Capabilities
- `e2e-testing`: запуск браузерних e2e-тестів Chromium з хоста проти застосунку, що працює в Docker на `http://localhost:8080`.

### Modified Capabilities

## Impact

- Файли: `package.json`, `package-lock.json`, `playwright.config.js`, `e2e/`, `CLAUDE.md`, `openspec/config.yaml`, `.env.example`, `.gitignore` (артефакти Playwright).
- Документація: створюються `docs/e2e-testing.md` і `docs/README.md`.
- Залежності: `@playwright/test` (dev) і завантажений на хост Chromium.
- Docker-конфігурація не змінюється.
