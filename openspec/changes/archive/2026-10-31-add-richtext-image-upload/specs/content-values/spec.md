# Spec Delta

## MODIFIED Requirements

### Requirement: Санітизація HTML форматованого тексту
Перед збереженням система SHALL очищати значення `richtext` за списком дозволеного: дозволені лише теги заголовків, абзаців, переносу рядка, виділення (жирний, курсив, підкреслення, закреслення), цитат, марковані й нумеровані списки, посилання, `span`, зображення зі сховища (з підписом) і фрейми з дозволених хостів. Усі інші теги, атрибути й обробники подій SHALL бути прибрані; у посилання SHALL бути дозволені лише схеми `http`, `https`, `mailto`, `tel` (і відносні адреси), а посилання SHALL мати безпечні значення `rel`. Атрибут `style` SHALL бути дозволений лише на абзацах, заголовках, цитатах, елементах списку, `span` і `figure` (на `figure` лише `text-align`, `width`, `max-width` і `margin-left`/`margin-right`, див. нижче) і SHALL залишати лише властивості `text-align` (`left`, `center`, `right`, `justify`), `color` і `background-color` зі значенням у форматі `#rgb`, `#rrggbb` або `rgb(r, g, b)`; будь-яка інша властивість чи значення (зокрема `url(...)`, `expression(...)`, `position`) SHALL бути прибрані. Тег `img` SHALL лишатися лише з атрибутами `src`, `alt` і `width`, і лише коли `src` є відносною адресою файлу сховища вигляду `/storage/{uuid}.{розширення}` (без протоколу й домену); зображення з будь-якою іншою адресою (зовнішньою, протокол-відносною `//host/...`, `data:`, з іншим шляхом) SHALL бути прибрані повністю. Атрибут `width` SHALL лишатися лише як ціле число від 1 до 4000 (інакше атрибут прибирається, а зображення лишається); `height` SHALL бути прибраний. Підпис SHALL зберігатися як `figure`, що містить `img` і `figcaption` з текстом; `figcaption` SHALL не мати атрибутів, `figure` SHALL мати лише `style` зі шириною, вирівнюванням і `max-width: 100%`: `width` — лише `{N}px` (ціле 1–4000), `fit-content` або `min-content` (збережене раніше), `margin-left`/`margin-right` — лише `auto` або `0`, `max-width` — лише `100%`, `text-align` — `left|center|right`. Фрейм `iframe` SHALL лишатися лише з адресою `https` на хост (за потреби з префіксом шляху) зі списку дозволених у конфігурації, до якого за замовчуванням входять відеосервіси, Google Drive і Google Docs, соцмережі, і лише з атрибутами `src`, `width`, `height`, `title`, `allowfullscreen`, `allow`; система SHALL примусово виставляти йому `sandbox` і `loading="lazy"`, інакше весь тег SHALL бути прибраний. Очищення SHALL відбуватися на сервері незалежно від віджета, а при читанні SHALL повертатися вже очищене значення. Текстовий вміст, що лишається після видалення заборонених тегів, SHALL зберігатися.

#### Scenario: Скрипт
- **WHEN** у поле `richtext` записують `<p>Hi</p><script>alert(1)</script>`
- **THEN** збережено `<p>Hi</p>` без тегу `script` і його вмісту

#### Scenario: Обробник події
- **WHEN** у поле `richtext` записують `<p onclick="x()">Hi</p>`
- **THEN** збережено `<p>Hi</p>` без атрибута `onclick`

#### Scenario: Небезпечна схема посилання
- **WHEN** у поле `richtext` записують `<a href="javascript:alert(1)">x</a>`
- **THEN** збережений HTML не містить посилання з схемою `javascript:`

#### Scenario: Безпечне посилання
- **WHEN** у поле `richtext` записують `<a href="https://example.com">x</a>`
- **THEN** посилання збережено з тією самою адресою

#### Scenario: Зображення
- **WHEN** у поле `richtext` записують `<img src="https://evil.example/a.png">`
- **THEN** тег прибрано з збереженого значення

#### Scenario: Зображення зі сховища
- **WHEN** у поле `richtext` записують `<img src="/storage/{uuid}.png" alt="Лого" onerror="x()">`
- **THEN** збережено `img` з тією самою адресою й `alt`, без `onerror`

#### Scenario: Ширина зображення
- **WHEN** у поле `richtext` записують `<img src="/storage/{uuid}.png" width="200" height="100">`
- **THEN** збережено `img` з `width="200"` без `height`

#### Scenario: Некоректна ширина
- **WHEN** у поле `richtext` записують `<img src="/storage/{uuid}.png" width="5000">` або `width="10px"`
- **THEN** `img` збережено без `width`

#### Scenario: Підпис зображення
- **WHEN** у поле `richtext` записують `<figure class="x" onclick="y()"><img src="/storage/{uuid}.png"><figcaption>Офіс</figcaption></figure>`
- **THEN** збережено `figure` з `img` і `figcaption` «Офіс» без `class` і `onclick`

#### Scenario: Вирівняне зображення
- **WHEN** у поле `richtext` записують `<figure style="text-align: center"><img src="/storage/{uuid}.png"></figure>`
- **THEN** збережено `figure` зі `style="text-align: center"` і `img`

#### Scenario: Вирівняне зображення з підписом
- **WHEN** у поле `richtext` записують `<figure style="text-align: right"><img src="/storage/{uuid}.png"><figcaption>Офіс</figcaption></figure>`
- **THEN** збережено `figure` зі `style="text-align: right"`, `img` і `figcaption` «Офіс»

#### Scenario: Вирівнювання через поля
- **WHEN** у поле `richtext` записують `<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center; position: absolute"><img src="/storage/{uuid}.png"><figcaption>Офіс</figcaption></figure>`
- **THEN** збережено `figure` лише з `width: 500px`, `max-width: 100%`, `margin-left: auto`, `margin-right: auto`, `text-align: center`, без `position`

#### Scenario: Допустимі ширини figure
- **WHEN** на `figure` записують `width: 200px`, `width: fit-content` або (збережене раніше) `width: min-content`
- **THEN** кожне значення збережено

#### Scenario: Недопустимі значення на figure
- **WHEN** на `figure` записують `width: 9999px`, `width: 50%` або `margin-left: 50px`
- **THEN** ці властивості прибрано

#### Scenario: Інші властивості на figure
- **WHEN** у поле `richtext` записують `<figure style="position: absolute; text-align: center"><img src="/storage/{uuid}.png"></figure>`
- **THEN** збережено `figure` лише зі `style="text-align: center"`

#### Scenario: Адреса зображення не відносна
- **WHEN** у поле `richtext` записують `<img src="//host/storage/{uuid}.png">`, `<img src="http://localhost/storage/{uuid}.png">` або `<img src="data:image/png;base64,AAAA">`
- **THEN** у збереженому значенні немає тегу `img`

#### Scenario: Вирівнювання зберігається
- **WHEN** у поле `richtext` записують `<p style="text-align: center">Hi</p>`
- **THEN** збережено абзац зі `style="text-align: center"`

#### Scenario: Колір тексту й фону зберігаються
- **WHEN** у поле `richtext` записують `<span style="color: #ff0000; background-color: #ffff00">x</span>`
- **THEN** збережено `span` з обома властивостями

#### Scenario: Небезпечний style очищається
- **WHEN** у поле `richtext` записують `<span style="color: red; position: fixed; background-color: url(javascript:x)">x</span>`
- **THEN** у збереженому `style` немає `position` і `url(`, а текст `x` збережено

#### Scenario: style на недозволеному тегу прибирається
- **WHEN** у поле `richtext` записують `<strong style="color: #f00">x</strong>`
- **THEN** збережено `<strong>x</strong>` без `style`

#### Scenario: Фрейм з дозволеного хоста зберігається
- **WHEN** у поле `richtext` записують `<iframe src="https://www.youtube-nocookie.com/embed/abc" onload="x()"></iframe>`
- **THEN** збережено `iframe` з тією ж адресою, `sandbox` і `loading="lazy"`, без `onload`

#### Scenario: Атрибут allow очищається
- **WHEN** у поле `richtext` записують `<iframe src="https://www.youtube-nocookie.com/embed/abc" allow="autoplay; camera; picture-in-picture"></iframe>`
- **THEN** збережений `allow` дорівнює `autoplay; picture-in-picture`, а `camera` прибрано

#### Scenario: Фрейм Google Drive або соцмережі зберігається
- **WHEN** у поле `richtext` записують `<iframe src="https://drive.google.com/file/d/abc/preview"></iframe>` або `<iframe src="https://www.facebook.com/plugins/post.php?href=x"></iframe>`
- **THEN** обидва `iframe` збережено з `sandbox` і `loading="lazy"`

#### Scenario: Фрейм Google Forms зберігається
- **WHEN** у поле `richtext` записують `<iframe src="https://docs.google.com/forms/d/e/abc/viewform?embedded=true"></iframe>`
- **THEN** `iframe` збережено з тією самою адресою, `sandbox` і `loading="lazy"`

#### Scenario: Фрейм поза префіксом шляху прибирається
- **WHEN** у поле `richtext` записують `<iframe src="https://www.facebook.com/some.page"></iframe>`
- **THEN** у збереженому значенні немає тегу `iframe`

#### Scenario: Фрейм з чужого хоста або не https прибирається
- **WHEN** у поле `richtext` записують `<iframe src="https://evil.example/x"></iframe>` або `<iframe src="http://www.youtube.com/embed/a"></iframe>`
- **THEN** у збереженому значенні немає тегу `iframe`

## ADDED Requirements

### Requirement: Зображення в значенні форматованого тексту
Кожне зображення у значенні `richtext` SHALL посилатися на наявний файл профілю `image`; значення з посиланням на неіснуючий файл чи файл іншого профілю SHALL бути відхилене з помилкою для цього поля, а нічого не записується. Значення, що містить зображення, SHALL вважатися непорожнім навіть без видимого тексту. Довжина за видимим текстом саме зображення не враховує, а текст підпису (`figcaption`) враховує, як і пошук за текстом.

#### Scenario: Наявний файл
- **WHEN** у поле `richtext` записують `<p><img src="/storage/{uuid}.png"></p>`, де файл `{uuid}` профілю `image` існує
- **THEN** значення збережено

#### Scenario: Неіснуючий файл
- **WHEN** у поле `richtext` записують `<img src="/storage/{uuid}.png">`, а файлу `{uuid}` немає
- **THEN** запис відхилено з помилкою для цього поля

#### Scenario: Файл іншого профілю
- **WHEN** у поле `richtext` записують `<img>` з адресою файлу профілю `document`
- **THEN** запис відхилено з помилкою для цього поля

#### Scenario: Підпис у пошуку й довжині
- **WHEN** підпис зображення «Наш офіс» і запис шукають за словом «офіс»
- **THEN** запис знайдено, а довжина за видимим текстом включає символи підпису

#### Scenario: Лише зображення
- **WHEN** у обов'язкове поле `richtext` записують `<p><img src="/storage/{uuid}.png"></p>` без тексту
- **THEN** значення вважається непорожнім і збережено
