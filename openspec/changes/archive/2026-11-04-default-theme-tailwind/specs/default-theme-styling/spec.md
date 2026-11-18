# Spec Delta

## Purpose

The visual look of the public site by default: the theme `default` serves styled pages that are readable on a phone and on a desktop, and keeps the hook classes by which other themes override the markup.

## ADDED Requirements

### Requirement: Стилі теми за замовчуванням підключаються через збірку ресурсів
The theme `default` SHALL have a stylesheet in `assets/css/app.css`, which is connected by the template `html` through the build of the resources of the theme. The pages of the public site with the theme `default` SHALL be served with this stylesheet, and the page of the admin SHALL NOT receive the styles of the public theme.

#### Scenario: The public page has the styles of the theme
- **WHEN** a guest opens the home page with `APP_THEME=default` and the built resources
- **THEN** `themes/default/assets/css/app.css` is connected in `<head>`, and the header, the main zone and the footer have their own indents and font, not the browser default values

#### Scenario: The admin does not change its look
- **WHEN** the administrator opens any page of `/admin`
- **THEN** the styles of the default theme are not applied, and the look of the admin is the same as before this change

### Requirement: Каркас сторінки
The header of the site SHALL show the logo (if it is set), the name of the site as a link to the home page and the menu `main` (if it exists). The main zone SHALL limit the width of the content and have internal indents. The footer SHALL show the contacts from the settings of the site and be separated from the main zone.

#### Scenario: Header with a logo and a menu
- **WHEN** the logo, the name of the site and the menu `main` with visible items are set
- **THEN** the header shows the logo, the name-link to `/` and the items of the menu in one row on a wide screen

#### Scenario: Without the menu `main`
- **WHEN** the menu `main` does not exist or has no visible items
- **THEN** the header shows the logo and the name, there is no navigation, and the page has no error

#### Scenario: Content is limited in width
- **WHEN** a page is opened on a screen 1440px wide
- **THEN** the text of the main zone does not stretch to the full width of the screen and is centred

### Requirement: Адаптивність до телефона
The pages of the theme `default` SHALL be readable on a screen 375px wide: the text wraps, the images and videos do not go beyond the screen, and the page SHALL NOT have a horizontal scroll. The side indents of the content on a phone SHALL be not less than 16px.

#### Scenario: Narrow screen without horizontal scroll
- **WHEN** any public page is opened on a screen 375px wide
- **THEN** there is no horizontal scroll of the page, and a long line or a wide table wraps or scrolls inside its own block

#### Scenario: Menu on a phone
- **WHEN** a page with the menu `main` is opened on a 375px screen
- **THEN** the items of the menu wrap onto several lines or collapse so that each item is available without a horizontal scroll of the page

### Requirement: Меню з вкладеними пунктами
The menu SHALL show nested items with an indent or as a nested list, so that it is visible which items belong to the parent. An item with a `css_class` SHALL have these classes on its element.

#### Scenario: Nested items
- **WHEN** the menu has an item with child items
- **THEN** the child items are seen as nested under the parent, and the parent is marked as having nested items

### Requirement: Вигляд полів і записів
The values of the fields SHALL have agreed captions and indents: the caption of a field is separated from the value, the multiple values are listed separately. The images in the fields SHALL fit in the width of the content. The field `richtext` SHALL have typography for the headings, lists, links and quotes. A YouTube video SHALL take the width of the content with the aspect ratio 16:9. The card of a record in the list SHALL show the heading and the fields of the card in one block with indents.

#### Scenario: Richtext with a heading and a list
- **WHEN** the field `richtext` contains a second-level heading and a bulleted list
- **THEN** the heading and the list have visible indents and sizes, different from the ordinary text

#### Scenario: YouTube in a field
- **WHEN** the page has a field `youtube`
- **THEN** the embedded player takes the width of the content and has the aspect ratio 16:9

#### Scenario: Large image in a field
- **WHEN** the field `image` contains an image wider than the content
- **THEN** the image is reduced to the width of the content, keeping the proportions

#### Scenario: List of records
- **WHEN** the list of the type `content` with several records is opened
- **THEN** the cards of the records are placed one under another with indents, and the paginator, if enabled, is separated from the list

### Requirement: Сторінка помилки 404 стилізована
The page `errors/404` SHALL have a heading and the error text in the centre of the main zone with indents, and the link to the home page SHALL be visible.

#### Scenario: Non-existent address
- **WHEN** a guest opens a non-existent address
- **THEN** the response has the status 404, the page shows the heading «404» and the error text in the styled look, and the link to the home page is visible

### Requirement: Класи-хуки лишаються
The styling SHALL NOT remove the classes which the templates of the theme `default` already output as the contract for overriding by other themes: `site-header`, `site-main`, `site-footer`, `menu`, `menu--{key}`, `menu__item`, `page`, `record-list`, `field`, `field-{type}`, `field--{key}`, `field__label`, `field__item`. The classes of the utilities SHALL be added next to them.

#### Scenario: Another theme sees its hooks
- **WHEN** the theme `custom` overrides `layout` and the templates of the theme `default` are rendered without changes
- **THEN** the page has `site-header`, `site-main`, `site-footer`, `menu--main` and `field--{key}` with the same names as before this change
