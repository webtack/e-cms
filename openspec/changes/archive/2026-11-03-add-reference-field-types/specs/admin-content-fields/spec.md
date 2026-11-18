## ADDED Requirements

### Requirement: Налаштування полів-посилань
The form of the field SHALL show, for the type `type_ref`, a required choice of the kind of types (`content`, `page`, `block`), and for the type `node_ref` — a required choice of the type of records (a search among all types by the name and the key). After the values appear in the field, the choice SHALL be blocked with an explanation. The errors of the server SHALL be shown next to the corresponding choice. These types SHALL NOT have other settings.

#### Scenario: Creating a field type_ref
- **WHEN** the admin chooses the type `type_ref`, the kind `block` and saves
- **THEN** the field is created with `settings.kind = block`

#### Scenario: Creating a field node_ref
- **WHEN** the admin chooses the type `node_ref`, the type of records `article` and saves
- **THEN** the field is created with `settings.content_type = article`

#### Scenario: Required choice
- **WHEN** the admin saves a field `type_ref` / `node_ref` without a choice
- **THEN** an error is shown next to the choice

#### Scenario: Field with values
- **WHEN** the admin opens a field that already has values
- **THEN** the choice of the kind / the type is blocked with an explanation
