# Spec Delta

## ADDED Requirements

### Requirement: Поля на сторінці й у деталях запису
The page (`kind = page`) and the details of a record `content` SHALL output after the heading the fields listed in the public settings of the type (`page_fields`), in the order of the setting. If there is no setting, all added fields of the type SHALL be output. The service values (`title`, `meta_*`, `section_*`) SHALL NOT be output as ordinary fields, but `meta_title`, `meta_description` and `meta_image` SHALL get into the `<head>` of the document, when they are set. The fields SHALL be output by the templates of the fields of the theme.

#### Scenario: Configured fields of a page
- **WHEN** the page type has `page_fields = ['body']`, and the record has the fields `body` and `note`
- **THEN** the HTML has the value of `body`, and there is no `note`

#### Scenario: Without a setting — all added fields
- **WHEN** the type has no `page_fields`
- **THEN** the page outputs all added fields that have values

#### Scenario: Metadata of the document
- **WHEN** the record has `meta_title` and `meta_description`
- **THEN** `<title>` contains `meta_title`, and `<head>` — the meta description

### Requirement: Картки записів у списку
The list of records of the type `content` SHALL output each record as a card (preview) from the list of candidates `{t}/preview--{id}`, `{t}/preview`, `content/preview`, where `{id}` is the numeric `id` of the record. The card SHALL show `title` as a link to the record and the fields from `list_fields`. The list SHALL use the sorting, the number of records per page and the paginator from the public settings of the type; without the settings — the newest first. Only visible records SHALL get into the list.

#### Scenario: Fields of the card
- **WHEN** the type has `list_fields = ['title', 'price']`
- **THEN** the card of each record shows the heading-link and the price

#### Scenario: Overridden card of a record
- **WHEN** the theme has `news/preview--7`
- **THEN** the record with `id = 7` of the type `news` is output by this template, the others — by `news/preview` or `content/preview`

#### Scenario: Paginator disabled
- **WHEN** `pagination = false`
- **THEN** the list contains no links to pages

#### Scenario: Sorting
- **WHEN** the type has `sort = {field: 'title', direction: 'asc'}`
- **THEN** the records go by the heading in ascending order

### Requirement: Меню в шапці сайту
The header of the site (`layout`) SHALL output the menu `main`, if it exists, on every public page, including 404. If the menu `main` does not exist, the header SHALL be output without the menu.

#### Scenario: The menu main exists
- **WHEN** the menu `main` with visible items exists
- **THEN** every public page contains these items in the header

#### Scenario: The menu main is absent
- **WHEN** the menu `main` has not been created
- **THEN** the pages are displayed without an error and without a menu
