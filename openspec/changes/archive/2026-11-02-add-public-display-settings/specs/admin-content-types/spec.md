## ADDED Requirements

### Requirement: Секція «Публічне відображення» у формі типу
The settings page of a type SHALL contain the section «Публічне відображення» for all kinds. For `page` and `block` it contains only the choice of the fields of the page; for `content` — also the fields of the card, the sorting (field + direction), the number of records per page and the switch of the paginator. For the choice only the added fields (`is_system = false`) are offered; `title`, the meta fields and the service attributes are not offered, because they are always available to the template. The section SHALL show the saved state with the default values and SHALL be saved together with the form, updating only the key `public` of the settings.

#### Scenario: Default values
- **WHEN** the form of a type `content` without public settings is opened
- **THEN** the switch «Усі додані поля» for the page is enabled, the card has no added fields, the sorting is «Дата публікації» descending, the number is «0» (all)

#### Scenario: Explicit choice of the page fields
- **WHEN** the administrator switches off «Усі додані поля», marks `price` and saves
- **THEN** after reopening only `price` is marked, and a new field of the type does not appear among the marked ones

#### Scenario: Return to «all»
- **WHEN** the administrator switches on «Усі додані поля» and saves
- **THEN** the key `page_fields` is not stored, and a new field automatically enters the page

#### Scenario: Other keys are not erased
- **WHEN** the form is saved with changed public settings
- **THEN** `settings.list` and the other keys of `settings` remain unchanged

### Requirement: Кількість записів і пагінатор у формі
The field of the number of records SHALL accept an integer ≥ 0 (by default `0` — all); with `0` the switch of the paginator SHALL be disabled and not affect the result. The section SHALL show validation errors next to the corresponding fields.

#### Scenario: Paginator with «all»
- **WHEN** the field of the number of records is `0`
- **THEN** the switch «Пагінатор» is disabled

#### Scenario: Incorrect number
- **WHEN** a negative number or a number above the allowed limit is entered and «Зберегти» is pressed
- **THEN** an error is shown next to the field, the settings are not saved
