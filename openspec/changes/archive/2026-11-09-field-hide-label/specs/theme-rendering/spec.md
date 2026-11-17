## MODIFIED Requirements

### Requirement: Rendering a field
The system SHALL render a field with a template chosen from a list of eight candidates from specific to general: `{t}/field-{ftype}--{key}`, `{t}/field-{ftype}`, `{t}/field--{key}`, `{t}/field`, `fields/field-{ftype}--{key}`, `fields/field-{ftype}`, `fields/field--{key}`, `fields/field`, where `{t}` is the key of the node's type, `{ftype}` is the field type, and `{key}` is the field key. The template SHALL always receive `$items` — an array of ready values, also for a single-value field, and `$field`, whose `hide_label` tells whether the label is hidden. A field without a value SHALL output nothing. Before rendering, the hooks `preprocess('field')` and `preprocess('field.{ftype}')` SHALL run, and `suggest('field')` SHALL be able to change the list of candidates. The theme `default` SHALL have a template for each of the 15 field types and the common `fields/field`; each of them SHALL output the field label (its name) unless the field has `hide_label` on, and the file template — the file name as a link and its description. A field with the label hidden SHALL still output its values.

#### Scenario: Cascade from the field key
- **WHEN** the theme has `fields/field--cover` and `fields/field-image`, and the field `cover` of type `image` is rendered
- **THEN** `fields/field-image--cover` is used if it exists, otherwise `fields/field-image`, and `fields/field--cover` only when `fields/field-image` does not exist

#### Scenario: The node type template outranks the global one
- **WHEN** the node type `news` has `news/field-string`, and `default` has `fields/field-string`
- **THEN** a field of type `string` of a node of `news` is rendered by the template `news/field-string`

#### Scenario: A single-value field as a list
- **WHEN** a single-value field with a value is rendered
- **THEN** the template receives `$items` with one element

#### Scenario: An empty field
- **WHEN** a field has no value (or a multiple field has an empty list)
- **THEN** the HTML of the field is empty

#### Scenario: Label and file description in the default theme
- **WHEN** a non-empty field of any type is rendered, and for `file` — with the file name and description
- **THEN** the HTML contains the field name as a label; for `file` — the file name as the link text and the description under it

#### Scenario: Hidden label in the default theme
- **WHEN** a non-empty field of any type with `hide_label` on is rendered
- **THEN** the HTML contains the values of the field and does not contain the field name as a label

#### Scenario: The preprocess hook of a field
- **WHEN** the theme registers `preprocess('field.youtube')` that changes the variables
- **THEN** the template of the `youtube` field receives the changed variables, and the hook `field` was executed earlier
