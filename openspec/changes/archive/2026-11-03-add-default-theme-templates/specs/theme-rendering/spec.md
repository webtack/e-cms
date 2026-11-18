# Spec Delta

## Purpose

The conversion of field values into data ready for output, and the rendering of fields, blocks and menus by the templates of the theme with a cascade of candidates and hooks.

## ADDED Requirements

### Requirement: Готові значення полів
Each field type SHALL provide a public representation of the value (`present`): data ready for output without additional requests in the template. Files and images SHALL be presented by a public URL together with the file name and its description (the description is empty if it is not set); the value of the type `term` — by the name of the term in the reading language (without substitution by another language); `node_ref` — by the heading and the address of the target record (a record that is invisible or deleted SHALL give the absence of a value); `type_ref` — by the key, the name and the address of the type (for the kind `content`); `youtube` — by the identifier, the viewing address and the embed address; `url` — by the address and the heading; `richtext` — by the cleaned HTML. The other types SHALL be presented by the value of their PHP type.

#### Scenario: Image as a URL
- **WHEN** a field of the type `image` has the value `{uuid}.png`
- **THEN** the template of the field receives the public URL of this file, and not `{uuid}.png`

#### Scenario: Name and description of a file
- **WHEN** a field of the type `file` refers to a file with the name `price.pdf` and the description `Price list`
- **THEN** the template of the field receives the URL, the name `price.pdf` and the description `Price list`, and for a file without a description — an empty description

#### Scenario: Term as a name
- **WHEN** a field of the type `term` refers to a term that has a translation in the reading language
- **THEN** the template of the field receives the name of the term in this language

#### Scenario: Invisible target record
- **WHEN** a field `node_ref` refers to a record that is inactive or unpublished
- **THEN** the template does not receive this value and outputs nothing about it

#### Scenario: YouTube
- **WHEN** a field `youtube` has a value with the identifier of the video
- **THEN** the template receives the identifier and the embed address `youtube-nocookie.com`

### Requirement: Рендер поля
The system SHALL render a field by a template chosen from the list of eight candidates from the specific to the general: `{t}/field-{ftype}--{key}`, `{t}/field-{ftype}`, `{t}/field--{key}`, `{t}/field`, `fields/field-{ftype}--{key}`, `fields/field-{ftype}`, `fields/field--{key}`, `fields/field`, where `{t}` is the key of the type of the record, `{ftype}` is the type of the field, `{key}` is the key of the field. The template SHALL always receive `$items` — an array of ready values, also for a non-multiple field. A field without a value SHALL output nothing. Before rendering the hooks `preprocess('field')` and `preprocess('field.{ftype}')` SHALL be run, and `suggest('field')` SHALL be able to change the list of candidates. The theme `default` SHALL have a template for each of the 15 field types and the general `fields/field`; each of them SHALL output the label of the field (its name), and the template of a file — the file name as a link text and its description under it.

#### Scenario: Cascade from the key of the field
- **WHEN** the theme has `fields/field--cover` and `fields/field-image`, and the field `cover` of the type `image` is rendered
- **THEN** `fields/field-image--cover` is used if it exists, otherwise `fields/field-image`, and `fields/field--cover` only when there is no `fields/field-image`

#### Scenario: The template of the type of the record beats the global one
- **WHEN** the type of the record `news` has `news/field-string`, and `default` has `fields/field-string`
- **THEN** the field of the type `string` of the record `news` is rendered by the template `news/field-string`

#### Scenario: Non-multiple field as a list
- **WHEN** a non-multiple field with a value is rendered
- **THEN** the template receives `$items` with one element

#### Scenario: Empty field
- **WHEN** the field has no value (or a multiple field has an empty list)
- **THEN** the HTML of the field is empty

#### Scenario: Label and description of a file in the theme default
- **WHEN** a non-empty field of any type is rendered, and for `file` — with the name and the description of the file
- **THEN** the HTML contains the name of the field as a label; for `file` — the name of the file as the text of the link and the description under it

#### Scenario: Preprocess hook of the field
- **WHEN** the theme registers `preprocess('field.youtube')`, which changes the variables
- **THEN** the template of the field `youtube` receives the changed variables, and the hook `field` has been run before

### Requirement: Блоки в шаблонах
A template SHALL be able to place a block by its slug with an explicit call. A block SHALL choose a template from the list `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`, where `{t}` is the key of the type of the block, and `{slug}` is the slug of the record without the prefix `block-`. The template of a block SHALL receive the record, the type and the values, and its fields SHALL be output by the same mechanism as the fields of a page. A block that does not exist, is inactive or unpublished SHALL NOT output anything and SHALL NOT break the page.

#### Scenario: Placing a block
- **WHEN** a visible block with the slug `block-footer` exists, and the template calls its placement
- **THEN** the HTML contains the content of the block, assembled by the template `blocks/block-{t}--footer`, and in its absence `blocks/block-{t}` or `blocks/block`

#### Scenario: Invisible block
- **WHEN** the block is inactive, not scheduled or does not exist
- **THEN** the placement outputs nothing, the response of the page is successful

#### Scenario: Block is not routed
- **WHEN** the block is placed in a template
- **THEN** this does not create a public address of this block

### Requirement: Меню в шаблонах
A template SHALL be able to place a menu by its key with an explicit call. The menu SHALL choose a template from the list `menus/menu--{key}`, `menus/menu` and receive the tree of visible items in the reading language (external links are marked, nested items keep the hierarchy). A menu that does not exist SHALL NOT output anything and SHALL NOT break the page.

#### Scenario: Nested menu
- **WHEN** the menu `main` has an item with a child item
- **THEN** the HTML contains both items, the child nested in the parent

#### Scenario: Hidden item
- **WHEN** a menu item is inactive or outside the publication period
- **THEN** it and its branch are not output

#### Scenario: Menu without a record
- **WHEN** a template calls a menu with a key that does not exist
- **THEN** the placement outputs nothing, the response of the page is successful

#### Scenario: Separate template of the menu
- **WHEN** the theme has `menus/menu--footer`
- **THEN** the menu `footer` is rendered by it, and the other menus — by the template `menus/menu`
