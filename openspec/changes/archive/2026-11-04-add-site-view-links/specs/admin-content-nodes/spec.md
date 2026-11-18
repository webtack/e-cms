## ADDED Requirements

### Requirement: Кнопка «Перегляд» у формі редагування запису
The form of editing a record of a page (`page`) or of a type `content` SHALL contain in the bottom action panel the button «Перегляд» ("Preview") — a link to the public address of this record, which opens in a new browser tab: for a page `/{slug}`, for a record `content` — `/{slug of the type}/{slug of the record}`. The address SHALL match the saved slug (not the unsaved changes in the form). For a record of the type `block` there SHALL be NO button, because a block has no address of its own. In the form of creating there SHALL be NO button. The button is a simple link: for a record that the site does not show (disabled, scheduled, expired) the site responds with 404 according to the rules of the public site.

#### Scenario: Preview of a page
- **WHEN** the user opens the form of editing a page with the slug `about` and clicks «Перегляд» ("Preview")
- **THEN** `/about` opens in a new tab

#### Scenario: Preview of a record content
- **WHEN** the user opens the form of editing a record `hello` of a type with the slug `news`
- **THEN** the button «Перегляд» ("Preview") leads to `/news/hello` and opens in a new tab

#### Scenario: Block without preview
- **WHEN** the user opens the form of editing a record of a block
- **THEN** there is no button «Перегляд» ("Preview")

#### Scenario: Form of creating
- **WHEN** the user opens the form of creating a record
- **THEN** there is no button «Перегляд» ("Preview")

#### Scenario: Invisible record
- **WHEN** the user clicks «Перегляд» ("Preview") on a disabled record
- **THEN** the 404 of the site opens in a new tab, and the form in the admin remains unchanged
