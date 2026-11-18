## ADDED Requirements

### Requirement: Налаштування публічного відображення типу
The system SHALL store in `content_types.settings.public` the public display settings of a type: `list_fields` (the added fields of the card in the list), `page_fields` (the added fields of the page of the record), `sort` (`field` and `direction` — `asc` or `desc`), `per_page` (an integer ≥ 0) and `pagination` (a boolean). «Added» fields are the fields with `is_system = false` that hold the values of records and have a non-reserved key. The system SHALL reject, through `ValidationException`, references to other fields, to fields of another type, a non-sortable field in `sort.field`, and `per_page` outside the admissible limits. Saving `public` SHALL NOT change the other keys of `settings`.

#### Scenario: Saving admissible settings
- **WHEN** a type is saved with `settings.public` = `{ "list_fields": ["price"], "sort": { "field": "published_at", "direction": "desc" }, "per_page": 10, "pagination": true }` and the field `price` is an added field of the type
- **THEN** the settings are saved, and the key `settings.list` remains unchanged

#### Scenario: System or foreign field is rejected
- **WHEN** `list_fields` or `page_fields` contains `meta_title`, `title`, or a key that is not among the fields of the type
- **THEN** the saving ends with a `ValidationException` with the key `settings`

#### Scenario: Incorrect direction or number
- **WHEN** `sort.direction` is not `asc`/`desc`, or `per_page` is negative or greater than `content.max_per_page`
- **THEN** the saving ends with a `ValidationException`

### Requirement: Значення за замовчуванням публічних налаштувань
Without settings (`settings.public` absent or empty) the system SHALL apply: in the card — only `title` (an empty `list_fields`), on the page — all added fields of the type, the sorting `published_at` descending, `per_page = 0` (all records). The absent key `page_fields` SHALL mean «all added fields» at the moment of reading, so a new added field immediately enters the page; an explicitly saved `page_fields` (even an empty one) SHALL mean exactly the list.

#### Scenario: New field appears on the page
- **WHEN** a type has no key `page_fields` and a new field is added
- **THEN** the effective set of fields of the page includes the new field without a change of the settings

#### Scenario: Explicit list does not grow by itself
- **WHEN** `page_fields` is saved as `["price"]` and a new field is added
- **THEN** the effective set of fields of the page remains `["price"]`

### Requirement: Публічні налаштування залежать від виду типу
The system SHALL accept `list_fields`, `sort`, `per_page` and `pagination` only for types of the kind `content`; for `page` and `block` an attempt to specify them SHALL end with a `ValidationException`, while `page_fields` is accepted for all kinds. The meta fields, the service attributes of the record and the values of the section of the type SHALL remain available to the template regardless of the settings.

#### Scenario: List for a page is rejected
- **WHEN** a type of the kind `page` is saved with `settings.public.per_page`
- **THEN** the saving ends with a `ValidationException`

#### Scenario: Reference to a deleted field
- **WHEN** a field mentioned in `list_fields` has been deleted
- **THEN** reading the settings discards this key and does not fail
