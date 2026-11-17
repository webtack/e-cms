# Design

## Context

Редактор — `Components/Admin/ContentNodes/RichTextEditor.tsx` (Tiptap 3, `StarterKit` без `strike`, `code`, `codeBlock`, `horizontalRule`; заголовки h2–h4; тулбар із `ToolButton` і `LinkButton` на `Popover`). Сервер очищає значення в `RichTextType::prepare()` через `App\Content\HtmlSanitizer` (`symfony/html-sanitizer`): уже дозволені `u`, `s`, `strong`, `em`, `p`, `br`, `h2`–`h4`, `blockquote`, `ul`, `ol`, `li`, `a[href]`; `span` зараз розгортається (тег прибирається, текст лишається). Атрибути `style` і тег `iframe` забороняються. `symfony/html-sanitizer` має `AttributeSanitizerInterface` (`withAttributeSanitizer`), тож власні перевірки значень атрибутів додаються без нових пакетів. Мотивація — у `proposal.md`, вимоги — у `specs/`.

## Goals / Non-Goals

**Goals:**
- Усі дії з запиту доступні в тулбарі, збережений HTML відновлюється редактором без втрат.
- Серверний allow-list лишається єдиним авторитетним шаром: безпечні `style` і `iframe` проходять, решта ні.
- Мінімум нових залежностей, жодних змін у моделі даних і міграцій.

**Non-Goals:**
- Скрипти вбудови соцмереж (`<script async src=…>`, `blockquote` Twitter/Instagram): підтримуються лише варіанти з `iframe`.
- Таблиці, зображення, код-блоки, довільні шрифти й розміри шрифту.
- Рендеринг на сайті (тип віддає санітизований HTML).
- Налаштування складу тулбара через `settings` поля.
- Завантаження відео з медіатеки.

## Decisions

**1. Розширення Tiptap.** `@tiptap/extension-text-align` (типи `heading`, `paragraph`; значення `left|center|right|justify`), `@tiptap/extension-text-style` (містить `Color` і `BackgroundColor` у Tiptap 3; якщо в установленій версії їх немає — `@tiptap/extension-color` і `@tiptap/extension-highlight` з `multicolor`), `@tiptap/extension-youtube`. Underline і Strike беруться зі `StarterKit` (у v3 Underline вже входить); у конфігурації прибирається `strike: false`. Версії підтверджує `npm install --legacy-peer-deps` і `npm run build` на першому завданні (як в архівній зміні). Документацію розширень звіряти через context7.

**2. Колір і фон як `span[style]`.** Tiptap серіалізує колір у `<span style="color: #rrggbb">`, фон — `background-color`. Колір обирається з вбудованої палітри (кнопки) і поля `#rrggbb`; значення нормалізується до `#rrggbb` на клієнті. Альтернатива — класи замість `style` — не годиться для довільного кольору. Серверу `span` більше не розгортається, а дозволяється з `style`.

**3. Очищення `style` на сервері: `StyleAttributeSanitizer`.** Реалізує `AttributeSanitizerInterface`, підключається до `HtmlSanitizerConfig::withAttributeSanitizer()`, оголошує `getSupportedElements()` = `span`, `p`, `h2`–`h4`, `blockquote`, `li` і `getSupportedAttributes()` = `style`. Значення розбирається на декларації; лишаються `text-align` (`left|center|right|justify`), `color`, `background-color` зі значенням за регулярним виразом `#rgb`, `#rrggbb`, `rgb(r, g, b)`; інше відкидається; порожній результат → `null` (атрибут прибирається). Список (`ul`/`ol`) і `strong`/`em` `style` не отримують. Чому не `allowAttribute('style')` без перевірки: це відкрило б `url()`, `position` тощо. Альтернатива — ключове слово `rgb`/ім'я кольору: імена кольорів відкинуто, щоб не підтримувати словник.

**4. Фрейми: `IframeSrcSanitizer` і конфіг хостів.** `allowElement('iframe', ['src','width','height','title','allowfullscreen','allow'])`; санітайзер `src` лишає лише `https` і хост зі списку `config('content.richtext.iframe_hosts')` (за замовчуванням: `www.youtube-nocookie.com/`, `www.youtube.com/embed/`, `player.vimeo.com/`, `drive.google.com/`, `docs.google.com/`, `www.google.com/maps/embed`, `www.facebook.com/plugins/`, `www.instagram.com/`, `platform.twitter.com/`, `www.tiktok.com/embed/`, `t.me/`). Запис — хост або хост із префіксом шляху; порівняння хоста точне (без суфіксних збігів, щоб `youtube.com.evil.example` не пройшов), шлях має починатися з префікса. Префікси потрібні для великих сайтів (наприклад `facebook.com`), де фреймуються лише плагіни. Один розбір (`parseIframeInput` на клієнті, `IframeSrcSanitizer` на сервері) однаково трактує записи списку. Google Forms вбудовується адресою `docs.google.com/forms/d/e/ID/viewform?embedded=true`, яку вже покриває запис `docs.google.com/`; окремого запису не треба (звузити до `docs.google.com/forms/` можна в конфігурації, якщо Docs/Sheets не потрібні). Google Drive і соцмережі потребують скриптів і cookies своєї origin, тому `sandbox` лишається `allow-scripts allow-same-origin allow-presentation allow-popups`; чи достатньо цього для кожної мережі, перевіряється вручну на завданні 5.1 (автотести не ходять у мережу). Тег `iframe` без прийнятного `src` має зникати цілком, а не лишатися порожнім: `HtmlSanitizer::clean()` після очищення прибирає `iframe` без `src` (прохід `DOMDocument` по результату); точний спосіб підтверджується тестами сценаріїв «чужий хост / не https» на завданні 2.4. `sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"` і `loading="lazy"` виставляються через `forceAttribute('iframe', ...)`. Альтернатива — дозволяти будь-який `https` — відкинуто як вектор для фішингових фреймів у адмінці й на сайті.

`allow` (Permissions Policy) очищується окремим `IframeAllowSanitizer`: значення ділиться за `;`, лишаються лише голі назви з білого списку `accelerometer`, `autoplay`, `clipboard-write`, `encrypted-media`, `fullscreen`, `gyroscope`, `picture-in-picture`, `web-share` (без вказання origin), решта (зокрема `camera`, `microphone`, `geolocation`, `payment`) відкидається; порожній результат → атрибут прибрано. Список зберігається в `config/content.php` (`richtext.iframe_allow`).

**5. Вставка відео YouTube.** Діалог (`Popover`) з полем адреси; `youtu.be/ID`, `youtube.com/watch?v=ID`, `youtube.com/embed/ID`, `shorts/ID` розбираються функцією `parseYoutubeId()`; вставляється розширення Youtube з `nocookie: true`, що дає `youtube-nocookie.com/embed/ID`. Некоректна адреса — повідомлення біля поля без вставки.

**6. Вставка фрейму.** Діалог з одним полем, що приймає і посилання `https://…`, і код `<iframe …></iframe>` цілком (також із обгорткою тексту навколо, береться перший `iframe`). З коду переносяться `src`, `width`, `height`, `title`, `allowfullscreen`, `allow` (числові `width`/`height` і значення `100%`); решта атрибутів (`style`, `frameborder`, `onload` тощо) відкидається, `sandbox` і `loading` виставляє сервер. Для голого посилання `width`/`height` не задаються, редактор показує фрейм на всю ширину зі співвідношенням 16:9. Розбір коду робить `DOMParser` на клієнті; сервер усе одно очищає значення самостійно. Клієнт перевіряє хост за тим самим списком: список віддається в Inertia-спільні пропси (`richtext.iframeHosts`) з конфігурації, щоб клієнт і сервер не розходилися; недозволений хост — помилка біля поля. Для Tiptap потрібен власний вузол `Iframe` (`Node.create`, `atom`, `group: 'block'`, атрибути `src`, `width`, `height`, `title`); YouTube-розширення вставляє власний вузол, тож розбір `iframe` з YouTube при завантаженні збереженого HTML має відновлюватися саме Youtube-розширенням, а інших хостів — вузлом `Iframe` (пріоритет розбору: Youtube перевіряє хост).

**7. Очищення форматування.** Команда `clearFormatting()`: `unsetAllMarks()` з подальшим поверненням дозволених позначок не підходить (втрачає B/I/U/посилання). Тому: для кожної позначки, крім `bold`, `italic`, `underline`, `link`, — `unsetMark`/`unsetColor`/`unsetBackgroundColor`/`unsetStrike`; `unsetTextAlign()` для блоків у виділенні; вузли заголовків не змінюються (не `clearNodes()`). Реалізується одним ланцюгом у `RichTextEditor`.

**8. Повноекранний режим — CSS + стан.** Стан `fullscreen` додає до обгортки `fixed inset-0 z-50 flex flex-col bg-background`, область редагування займає вільну висоту (`flex-1 overflow-auto`); `Escape` вимикає (слухач на документі лише в режимі). Редактор лишається в тому самому місці дерева (без порталу), тож стан і курсор не губляться; на час режиму блокується прокручування `body`. Попапи (`Popover`) мають z-index вищий за обгортку. Альтернатива — Fullscreen API браузера: неоднакова поведінка, не розгортає попапи.

**9. Смайлики.** Власна панель (`Popover` із сіткою ~60–80 популярних емодзі в групах) і `editor.chain().focus().insertContent(emoji).run()`. Пакет емодзі не додається: серверний allow-list нічого не змінює, бо це звичайний текст.

**10. Структура коду.** `RichTextEditor.tsx` розростається, тому нові діалоги й допоміжні функції виносяться поруч: `RichTextToolbar.tsx` (тулбар), `richTextExtensions.ts` (склад розширень і вузол `Iframe`), `richTextUtils.ts` (`parseYoutubeId`, `parseIframeInput`, `normalizeHexColor`), `EmojiPicker.tsx`. Публічні функції з JSDoc англійською та `@example`.

## Risks / Trade-offs

- [Соцмережі й Google можуть не відображати вбудову через власні `X-Frame-Options`/CSP, або потребувати інших адрес вбудови, ніж скопійована з браузера] → це поза нашим контролем; діалог підказує формат адреси вбудови (`/preview` для Drive, код «Вбудувати» для соцмереж), а клієнт приймає код `<iframe>`.

- [XSS через `style` і `iframe`] → єдиний шар на сервері, суворі регулярні вирази значень, точне порівняння хоста, `sandbox`; тести: `url()`, `expression()`, `javascript:`, `data:`, `http`, `youtube.com.evil.example`, `//host`.
- [`allow-same-origin` разом з `allow-scripts` у `sandbox` послаблює ізоляцію, але потрібен плеєрам YouTube/Vimeo] → дозволені лише довірені хости з конфігурації.
- [Розбір збереженого `iframe` не тим розширенням] → два вузли з чіткими правилами `parseHTML` і e2e-перевірка відновлення після перезавантаження.
- [Вирівнювання «за шириною» і `style` на `li`] → `text-align` дозволено лише там, де Tiptap його пише; сервер приймає ширший набір безпечно.
- [Клієнтська й серверна перевірки хостів розходяться] → єдине джерело — `config/content.php`, на клієнт передається через спільні пропси.
- [Колір чужого HTML при вставці з буфера] → `rgb(...)` дозволено на сервері; клієнт нормалізує до `#rrggbb` лише власні вставки.
- [Зростання бандла від розширень] → розширення невеликі; за потреби динамічний імпорт не плануємо.

## Migration Plan

Міграцій немає: збережені значення лишаються чинними (нові правила лише дозволяють більше). Залежності: `npm install --legacy-peer-deps @tiptap/extension-text-align @tiptap/extension-text-style @tiptap/extension-youtube` на хості, потім `npm run build`. Відкат: прибрати нові кнопки й залежності; збережений HTML з `style`/`iframe` після відкату серверного коду очиститься при наступному збереженні.

## Документація

`docs/content-values.md` (allow-list, `style`, хости фреймів), `docs/admin-content-nodes.md` (тулбар, повноекранний режим), `docs/e2e-testing.md` (сценарії спеку `admin-content-nodes`).
