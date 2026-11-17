# Proposal

## Why

Редактор поля `richtext` (див. архівну зміну `add-richtext-field-type`) має мінімум: заголовок, жирний, курсив, списки, посилання. Редакторам контенту бракує звичних можливостей текстового редактора: підкреслення, закреслення, вирівнювання, кольорів, очищення форматування, вставки відео й фреймів, смайликів і режиму на весь екран.

## What Changes

- Тулбар `RichTextEditor` отримує дії: підкреслений, закреслений, очищення форматування (залишає жирний, курсив, підкреслення, посилання, заголовки; прибирає решту), вирівнювання (ліворуч, по центру, праворуч, за шириною), колір тексту, колір фону, вставка YouTube-відео, вставка фрейму (`iframe`), смайлики, розгортання на весь екран.
- Серверний `HtmlSanitizer` розширюється allow-list: `u`, `s` (уже дозволені), `span` з безпечним `style` (лише `color` і `background-color`), блокові теги з `style="text-align: …"` і `iframe` лише з дозволених хостів. Усе інше, як і раніше, прибирається.
- Хости, з яких дозволено фрейми, задаються в конфігурації (`config/content.php`), за замовчуванням — відео (YouTube, Vimeo), Google Drive і Google Docs/Sheets/Slides/Forms (`drive.google.com`, `docs.google.com`), Google Maps (`google.com/maps/embed`) і соцмережі (Facebook-плагіни, Instagram, X/Twitter, TikTok, Telegram). Запис списку — хост або хост із префіксом шляху (наприклад `www.facebook.com/plugins/`), щоб не відкривати весь сайт.
- Нові залежності фронтенду: розширення Tiptap для вирівнювання, кольору, підсвітки, YouTube; нових пакетів PHP немає.

**Припущення (рішення, яких немає в запиті; фіксуються тут і в `design.md`):**
- «Вставка фрейму» — довільний `https`-фрейм за адресою або з готового `<iframe>`-коду, але лише з хостів (і префіксів шляху) дозволеного списку, куди входять Google Drive та соцмережі; фрейм завжди отримує `sandbox`. Список хостів можна змінити в конфігурації.
- «Смайлики» — вибір Unicode-емодзі з панелі, вставляється символ (без зображень і нових залежностей).
- Колір обирається з палітри та полем `#rrggbb`; зберігається як `#rrggbb`.

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `content-values`: allow-list очищення `richtext` розширюється (вирівнювання, колір, фон, фрейми з дозволених хостів).
- `admin-content-nodes`: тулбар редактора `richtext` отримує нові дії, повноекранний режим.

## Impact

- Код: `resources/js/Components/Admin/ContentNodes/RichTextEditor.tsx` (і, за потреби, нові файли поруч), `resources/css/app.css`, `app/Content/HtmlSanitizer.php` (+ нові санітайзери атрибутів `style` і `src` у `app/Content/`), `config/content.php`, `lang/en.json`, `lang/uk.json`, `package.json`.
- Тести: `tests/Feature/Content/HtmlSanitizerTest.php`, `tests/Feature/Content/RichTextFieldTypeTest.php`, `e2e/admin-content-nodes.spec.js`.
- Документація: оновити `docs/content-values.md` (allow-list, хости фреймів, `style`), `docs/admin-content-nodes.md` (тулбар), `docs/e2e-testing.md` (нові сценарії в розділі `admin-content-nodes`; таблиці спеків без нових рядків).
