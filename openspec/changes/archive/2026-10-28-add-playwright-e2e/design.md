# Design

## Context

Застосунок працює в Docker: `ecms_nginx` публікує порт 8080, `ecms_app` містить PHP. На хості PHP немає, а Node/npm працюють лише на хості (див. `CLAUDE.md`, розділ Docker). Тести PHPUnit лежать у `tests/`. Playwright відсутній. Мотивація: див. proposal.md.

## Goals / Non-Goals

**Goals:**
- Мінімальне e2e-оточення, що використовує вже запущений Docker-стек.
- Чіткі правила в `CLAUDE.md` і `openspec/config.yaml`, щоб агент не запускав PHP/npm не там.

**Non-Goals:**
- Playwright у Docker-контейнері та зміни `docker-compose.yml`.
- Firefox/WebKit, CI-конфігурація, наповнення сценаріями поза smoke-тестом.
- Автоматичне підготування БД (seed/міграції) для e2e.

## Decisions

- **Playwright на хості, не в контейнері.** Узгоджується з правилом «npm тільки на хості»; браузер бачить `localhost:8080` напряму. Альтернатива: окремий сервіс Playwright у compose, але це ускладнює стек без потреби.
- **Без `webServer` у конфігу.** Застосунок уже піднято в Docker, тож Playwright лише підключається за `baseURL`. Альтернатива: `webServer` з `docker compose up`, але це дублює керування контейнерами.
- **Один проєкт Chromium** у `playwright.config.js` та `npx playwright install chromium`. Менше місця і швидше; рішення підтверджено власником.
- **Тести в `e2e/`**, `testDir: './e2e'`. Це відокремлює їх від PHPUnit (`tests/`), якому вони не потрібні.
- **Конфіг у JS** (`playwright.config.js`), бо `package.json` має `"type": "module"` і проєкт не використовує TypeScript; JSDoc англійською згідно з правилами проєкту.
- **Правила та контекст** додаються до `CLAUDE.md` та `openspec/config.yaml` (поле `context` і правило `tasks`), щоб майбутні зміни автоматично враховували Docker і URL.
- **`.env.example`**: `APP_URL=http://localhost:8080`, узгоджено з `.env` та портом nginx.
- **Документація**: `docs/e2e-testing.md` українською та `docs/README.md` як індекс (каталогу `docs/` ще немає).

## Risks / Trade-offs

- [Тести падають, якщо контейнери не запущені] → у документації вказати передумову `docker compose up -d` і очікувану помилку підключення.
- [Chromium завантажується на хост і займає місце] → встановлювати лише Chromium; артефакти (`test-results/`, `playwright-report/`) додати в `.gitignore`.
- [Стан БД впливає на e2e] → поки обмежуємось smoke-тестом без залежності від даних.
