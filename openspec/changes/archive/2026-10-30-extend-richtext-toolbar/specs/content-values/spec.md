## MODIFIED Requirements

### Requirement: Санітизація HTML форматованого тексту
Перед збереженням система SHALL очищати значення `richtext` за списком дозволеного: дозволені лише теги заголовків, абзаців, переносу рядка, виділення (жирний, курсив, підкреслення, закреслення), цитат, марковані й нумеровані списки, посилання, `span` і фрейми з дозволених хостів. Усі інші теги, атрибути й обробники подій SHALL бути прибрані; у посилання SHALL бути дозволені лише схеми `http`, `https`, `mailto`, `tel` (і відносні адреси), а посилання SHALL мати безпечні значення `rel`. Атрибут `style` SHALL бути дозволений лише на абзацах, заголовках, цитатах, елементах списку й `span` і SHALL залишати лише властивості `text-align` (`left`, `center`, `right`, `justify`), `color` і `background-color` зі значенням у форматі `#rgb`, `#rrggbb` або `rgb(r, g, b)`; будь-яка інша властивість чи значення (зокрема `url(...)`, `expression(...)`, `position`) SHALL бути прибрані. Фрейм `iframe` SHALL лишатися лише з адресою `https` на хост (за потреби з префіксом шляху) зі списку дозволених у конфігурації, до якого за замовчуванням входять відеосервіси, Google Drive і Google Docs, соцмережі, і лише з атрибутами `src`, `width`, `height`, `title`, `allowfullscreen`, `allow`; система SHALL примусово виставляти йому `sandbox` і `loading="lazy"`, інакше весь тег SHALL бути прибраний. Очищення SHALL відбуватися на сервері незалежно від віджета, а при читанні SHALL повертатися вже очищене значення. Текстовий вміст, що лишається після видалення заборонених тегів, SHALL зберігатися.

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
- **WHEN** у поле `richtext` записують HTML із тегом `img`
- **THEN** тег прибрано з збереженого значення

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
