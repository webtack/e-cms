# default-theme-styling Specification

## Purpose

The default visual appearance of the public site: the theme `default` serves styled pages that read well on a phone and on a desktop, and keeps the hook classes by which other themes override the markup.

## Requirements

### Requirement: Default theme styles are connected through the resource build
The theme `default` SHALL have a style sheet in `assets/app.css`, which is connected by the `html` template through the theme's resource build. The style sheet of `default` SHALL remain plain CSS (without a preprocessor), because it is built by Tailwind. The public site pages with the theme `default` SHALL be served with this style sheet, and the admin panel page SHALL NOT receive the styles of the public theme.

#### Scenario: A public page has the theme styles
- **WHEN** a guest opens the home page with `APP_THEME=default` and the theme resources are built
- **THEN** the built style sheet `app` of the theme `default` is connected in `<head>`, and the header, the main zone, and the footer have their own margins and font, not the browser default values

#### Scenario: The admin panel does not change its look
- **WHEN** an administrator opens any `/admin` page
- **THEN** the default theme styles are not applied, and the look of the admin panel is the same as before this change

### Requirement: Page skeleton
The site header SHALL show the logo (if set), the site name as a link to the home page, and the menu `main` (if it exists). The main zone SHALL limit the content width and have inner margins. The footer SHALL show the contacts from the site settings and be separated from the main zone.

#### Scenario: Header with a logo and a menu
- **WHEN** a logo, a site name, and the menu `main` with visible items are set
- **THEN** the header shows the logo, the name-link to `/`, and the menu items in one row on a wide screen

#### Scenario: Without the menu `main`
- **WHEN** the menu `main` does not exist or has no visible items
- **THEN** the header shows the logo and the name, there is no navigation, and the page has no error

#### Scenario: Content is limited in width
- **WHEN** a page is opened on a screen 1440px wide
- **THEN** the text of the main zone does not stretch across the full width of the screen and is centered

### Requirement: Adaptation to a phone
The pages of the theme `default` SHALL be readable on a screen 375px wide: the text wraps, images and videos do not go beyond the screen, and the page SHALL NOT have horizontal scrolling. The side margins of the content on a phone SHALL be not less than 16px.

#### Scenario: A narrow screen without horizontal scrolling
- **WHEN** any public page is opened on a screen 375px wide
- **THEN** there is no horizontal page scrolling, and a long line or a wide table wraps or scrolls inside its own block

#### Scenario: The menu on a phone
- **WHEN** a page with the menu `main` is opened on a 375px screen
- **THEN** the menu items wrap onto several lines or collapse so that each item is accessible without horizontal page scrolling

### Requirement: Menu with nested items
The menu SHALL show nested items with an indent or as a nested list, so that it is visible which items belong to the parent. An item with a `css_class` SHALL have these classes on its element.

#### Scenario: Nested items
- **WHEN** the menu has an item with child items
- **THEN** the child items are visible as nested under the parent, and the parent is marked as having nested items

### Requirement: Look of fields and nodes
The values of fields SHALL have consistent labels and margins: the field label is separated from the value, and multiple values are listed separately. Images in fields SHALL fit the width of the content. The `richtext` field SHALL have typography for headings, lists, links, and quotes. A YouTube video SHALL take the width of the content with an aspect ratio of 16:9. A node card in a list SHALL show the title and the card fields in one block with margins.

#### Scenario: Richtext with a heading and a list
- **WHEN** a `richtext` field contains a second-level heading and a bulleted list
- **THEN** the heading and the list have visible margins and sizes different from ordinary text

#### Scenario: YouTube in a field
- **WHEN** a page has a `youtube` field
- **THEN** the embedded player takes the width of the content and has an aspect ratio of 16:9

#### Scenario: A large image in a field
- **WHEN** an `image` field contains an image wider than the content
- **THEN** the image is reduced to the width of the content, keeping its proportions

#### Scenario: List of nodes
- **WHEN** a list of a `content` type with several nodes is opened
- **THEN** the node cards are placed one under another with margins, and the paginator, if enabled, is separated from the list

### Requirement: Styled 404 error page
The page `errors/404` SHALL have a heading and an error text in the center of the main zone with margins, and the link to the home page SHALL be visible.

#### Scenario: A non-existent address
- **WHEN** a guest opens a non-existent address
- **THEN** the response has status 404, the page shows the heading "404" and the error text in a styled form, and the link to the home page is visible

### Requirement: Hook classes remain
The styling SHALL NOT remove the classes that the templates of the theme `default` already output as a contract for overriding by other themes: `site-header`, `site-main`, `site-footer`, `menu`, `menu--{key}`, `menu__item`, `page`, `record-list`, `field`, `field-{type}`, `field--{key}`, `field__label`, `field__item`. Utility classes SHALL be added next to them.

#### Scenario: Another theme sees its hooks
- **WHEN** the theme `custom` overrides `layout` and the templates of the theme `default` are rendered without changes
- **THEN** the page has `site-header`, `site-main`, `site-footer`, `menu--main`, and `field--{key}` with the same names as before this change

### Requirement: Isolation of the admin style build from the theme
The build of the admin styles SHALL NOT contain utilities that exist only in the theme's templates, and these templates SHALL NOT affect the look of the admin panel. A change of the theme's templates or styles SHALL NOT change the admin styles. The theme SHALL have full freedom in styling: its templates and style sheet do not depend on the admin styles.

#### Scenario: A utility used only by the theme does not get into the admin panel
- **WHEN** a theme template uses a utility that is absent from the admin code, for example `aspect-video`, and the resource build is run again
- **THEN** the compiled admin style sheet does NOT contain the rule for this utility

#### Scenario: A change of the theme does not change the admin panel
- **WHEN** the template or style of the theme `default` is changed and the resource build is run again
- **THEN** the styles used by the admin panel look the same as before the theme change

#### Scenario: The theme uses its own utilities
- **WHEN** a theme template uses a utility that is absent from the admin code
- **THEN** this utility is applied on the public page, and the theme's style sheet contains its rule

### Requirement: Look of a table in rich text
A table in a `richtext` value SHALL be shown on the public site with visible cell borders, padding inside the cells, and header cells that differ from ordinary cells. A table wider than the content SHALL scroll horizontally inside its own area instead of widening the page. The text of every cell SHALL be left-aligned unless a paragraph inside the cell sets its own alignment.

#### Scenario: A table with a header row
- **WHEN** a `richtext` field holds a table with a header row and two rows of data
- **THEN** the cells have borders and padding, and the header cells differ from the data cells

#### Scenario: A wide table on a phone
- **WHEN** a `richtext` field holds a table with many columns and the page is opened at the width of a phone
- **THEN** the table scrolls horizontally inside its own area and the page itself does not scroll sideways

#### Scenario: A paragraph alignment inside a cell
- **WHEN** a cell holds a paragraph saved with `text-align: center`
- **THEN** the text of that cell is centered and the other cells stay left-aligned

### Requirement: Breadcrumbs in the default theme
The theme `default` SHALL show the trail (`$breadcrumbs`) as a navigation landmark with an ordered list, above the page content, with the current page as the last item marked as the current page for assistive technology and every other item a link. The navigation SHALL have an accessible name. The markup SHALL live in its own template that another theme can replace without touching the layout, and SHALL carry classes a theme can style (`breadcrumbs`, `breadcrumbs__item`). When the trail is empty (the home page, a 404), nothing SHALL be output.

#### Scenario: A record page
- **WHEN** a record page of a nested type is shown
- **THEN** the page has a navigation with a list of the trail items, where the last one is not a link and is marked as the current page

#### Scenario: The home page
- **WHEN** the home page is shown
- **THEN** no breadcrumbs navigation is output

### Requirement: Breadcrumb structured data in the document head
The base document template of the theme `default` SHALL print the structured data (`$breadcrumbsJsonLd`) in the `<head>` as a `<script type="application/ld+json">` element when it is not `null`, and print nothing otherwise. A theme that replaces only its breadcrumbs template SHALL keep the structured data.

#### Scenario: A record page
- **WHEN** a record page is shown
- **THEN** the `<head>` contains one `application/ld+json` script with a `BreadcrumbList`

#### Scenario: The home page
- **WHEN** the home page is shown
- **THEN** the `<head>` has no `application/ld+json` script

### Requirement: Active menu states in the default theme
The menu templates of the theme `default` SHALL mark the active item (`is_active`) with the class `is-active` and `aria-current="page"` on its link, and each item in the trail (`is_trail`) with the class `is-trail`, and SHALL style both states so that they are visibly different from an ordinary item and from each other. The classes SHALL be added to the existing hook classes, not in place of them.

#### Scenario: The active item
- **WHEN** the page of a menu item is shown
- **THEN** its link has `aria-current="page"` and the class `is-active`, and its parent items have the class `is-trail`

### Requirement: Section title as the list heading
The list template of the theme `default` SHALL use the public title of the type (`$sectionTitle`) as the page heading, not the administrative name of the type.

#### Scenario: A type with a section title
- **WHEN** the list of a type whose administrative name differs from its section title is shown
- **THEN** the heading is the section title
