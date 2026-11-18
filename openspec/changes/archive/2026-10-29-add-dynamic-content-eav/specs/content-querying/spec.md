# Spec Delta

## Purpose

Describes the selection of content nodes of a content type with configurable visible fields, search, filters and sorting by dynamic fields, taking into account their type and language.

## ADDED Requirements

### Requirement: Selection of nodes with visible fields
The system SHALL return a page of nodes of the given content type with the values of only the requested visible fields (by default — from the list settings in the `settings` of the type) in the specified language (by default `default`; other languages are allowed under the same conditions as for reading values) and with the pagination data.

#### Scenario: Visible fields
- **WHEN** a list of nodes with the visible fields `title` and `price` is requested
- **THEN** each node contains values of only these fields

#### Scenario: Default settings
- **WHEN** no visible fields are specified, and list settings are set in the `settings` of the type
- **THEN** the visible fields and the sorting from these settings are used

#### Scenario: Pagination
- **WHEN** the second page with the size 10 is requested
- **THEN** nodes 11–20 are returned in the given order, together with the total count

#### Scenario: Unsupported language
- **WHEN** a list with `lang = en` is requested, and multilingualism is disabled or the language `en` is not in the list
- **THEN** the request is rejected with a validation error

#### Scenario: Selection language
- **WHEN** a list with `lang = en` is requested
- **THEN** the visible values are taken from the language `en`; nodes without this translation are returned with `null` in the corresponding fields

### Requirement: Filtering by field value
The system SHALL filter nodes by a field, an operator and a value in the specified language, and the set of allowed operators SHALL be determined by the field type: equality for all types; comparison (greater/less/range) for numeric, date and date-time; substring containment for text; the values `true`/`false` for boolean. For a multiple field a node SHALL match the filter if at least one of its values matches. Several filters SHALL be combined with AND.

#### Scenario: Numeric range
- **WHEN** the filter `price` is between `10` and `20`
- **THEN** only the nodes whose `price` is in this range inclusive are returned

#### Scenario: Multiple field
- **WHEN** the filter `tags` equals `news`, and a node has the values `["a", "news"]`
- **THEN** this node is included in the result

#### Scenario: Filter by language
- **WHEN** the filter `title` contains "Hello" with `lang = en`
- **THEN** only the nodes whose English `title` contains "Hello" are returned, regardless of the `default` value

#### Scenario: Unsupported operator
- **WHEN** the operator "contains" is applied to a boolean field
- **THEN** the request is rejected with a validation error

#### Scenario: Unknown field
- **WHEN** a filter refers to a key that does not exist in the content type
- **THEN** the request is rejected with a validation error

### Requirement: Search
The system SHALL support text search over a list of fields (from the request or from the list settings) in the specified language, returning nodes in which at least one of these fields contains the search string; the search SHALL allow only text fields.

#### Scenario: Search over several fields
- **WHEN** the search `lamp` is applied over the fields `title` and `description`
- **THEN** the nodes where `lamp` is contained in `title` or in `description` are returned

#### Scenario: Non-text field in the search
- **WHEN** an integer field is among the search fields
- **THEN** the request is rejected with a validation error

### Requirement: Sorting by field type
The system SHALL sort nodes by the value of a field in the specified language in ascending or descending direction according to the value type (numbers — numerically, dates — chronologically, text — lexicographically, boolean — false before true), SHALL place nodes without a value at the end regardless of the direction, and SHALL apply a deterministic secondary order by the node identifier. For a multiple field, sorting by it is forbidden.

#### Scenario: Numeric sorting
- **WHEN** sorting by `views` in descending order, with the values `9`, `10`, `100`
- **THEN** the order is `100`, `10`, `9`

#### Scenario: Nodes without a value
- **WHEN** some nodes have no value in the sorting field (in the specified language)
- **THEN** they are placed after the nodes with a value in any direction

#### Scenario: Sorting by a multiple field
- **WHEN** sorting by a multiple field is requested
- **THEN** the request is rejected with a validation error

### Requirement: Finding a node by slug
The system SHALL find a node by its slug regardless of its type and kind and return it with its type and kind, or report its absence.

#### Scenario: Found node
- **WHEN** a node with the slug `pro-nas` is requested
- **THEN** this node is returned together with its content type and its kind

#### Scenario: Missing node
- **WHEN** a slug that does not exist is requested
- **THEN** it is reported that the node is not found

### Requirement: List of types by kind
The system SHALL allow obtaining the content types filtered by kind `kind` (and by parent type), in order to work separately with ordinary content, pages and blocks.

#### Scenario: Filter of types by kind
- **WHEN** types with the kind `page` are requested
- **THEN** only the types of this kind are returned

### Requirement: Isolation by content type
The selection SHALL return only the nodes of the given content type and SHALL allow only the fields of this type.

#### Scenario: Field of another type
- **WHEN** a request to the type `article` specifies a field key that exists only in the type `product`
- **THEN** the request is rejected with a validation error
