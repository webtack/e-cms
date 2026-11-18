## ADDED Requirements

### Requirement: Елементи введення полів-посилань
The form of a record SHALL show for `type_ref` a choice of a type, and for `node_ref` a choice of a record; both as a drop-down autocomplete with a search on the server, which requests the options while typing (with a delay) and shows a limited number of results. For `type_ref` the search goes by the `name` of the type of the kind chosen in the settings of the field; an option shows the `name` (and the `key` muted). For `node_ref` the search goes by the `title` (the language `default`) of the records of the type chosen in the settings. The selected value SHALL show its label (`name` / `title`) immediately when the form is opened, without typing; the value of the form is `key` / `uuid`. The choice SHALL be clearable, and then the value is cleared. For a multiple field each element SHALL have its own autocomplete by the general rules of the form. The errors of the server SHALL be shown next to the field, and the choice SHALL be kept.

#### Scenario: Search of a type
- **WHEN** the user types a part of the `name` of a type in the field `type_ref`
- **THEN** the list shows the types of the required kind that match the request, and the choice records `key`

#### Scenario: Search of a record
- **WHEN** the user types a part of the `title` in the field `node_ref`
- **THEN** the list shows the records of the required type that match the request, and the choice records `uuid`

#### Scenario: Saved value
- **WHEN** the user opens a record with a saved value
- **THEN** the field shows the label of the selected target

#### Scenario: Clearing the choice
- **WHEN** the user clears the choice and saves
- **THEN** the value of the field is removed

### Requirement: Ендпоінти пошуку для полів-посилань
The admin SHALL provide to authorized administrators a JSON search of types by `name` within the kind and of records by `title` (the language `default`) within the type. The search SHALL return no more than 20 results in a stable order, and an empty request — the first 20. An unknown kind or type SHALL give a validation error, and an unauthorized request — a refusal.

#### Scenario: Search of types
- **WHEN** the admin requests the types of the kind `block` for `q = hero`
- **THEN** the response contains up to 20 types of this kind with `key` and `name` that contain `hero`

#### Scenario: Search of records
- **WHEN** the admin requests the records of the type `article` for `q = news`
- **THEN** the response contains up to 20 records with `uuid` and `title` that contain `news`

#### Scenario: Unknown type
- **WHEN** the request specifies a type that does not exist
- **THEN** the response is a validation error

#### Scenario: Without authorization
- **WHEN** an unauthenticated user calls the search
- **THEN** the request is rejected

### Requirement: Значення полів-посилань у таблиці записів
If the field `type_ref` / `node_ref` is chosen as a column of the list of records, the cell SHALL show the `name` of the type / the `title` of the record (the language `default`) as a link to the edit page of this entity; the link SHALL open in the same window (without `target="_blank"`). For a multiple field — several links in the saved order; if the label is not available — the value itself without a link. The column SHALL be sorted by the label (`name` / `title` of the language `default`), for a multiple field — by the first value; records without a value SHALL go last in both directions. The column SHALL be available as the default sorting in the settings of the list.

#### Scenario: Cell
- **WHEN** a record has a `node_ref` to a record with `title = Новини`
- **THEN** the cell shows the link `Новини`, which leads to editing this record and opens in the same window

#### Scenario: Link to a type
- **WHEN** a record has a `type_ref` to the type `Герой` of the kind `block`
- **THEN** the cell shows the link `Герой` to editing this type

#### Scenario: Sorting by the label
- **WHEN** the list is sorted by this field in ascending order
- **THEN** the records go in the alphabetical order of the labels of the targets, and the records without a value — last
