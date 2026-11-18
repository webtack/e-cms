# Spec Delta

## MODIFIED Requirements

### Requirement: Сортування за типом поля
The system SHALL sort records by the value of a field in the specified language in ascending or descending direction according to the value type (numbers — numerically, dates — chronologically, text — lexicographically, boolean — false before true), SHALL place records without a value at the end regardless of the direction, and SHALL apply a deterministic secondary order: by the value of the field `title` in the specified language in ascending order regardless of the direction of the main sorting, records without `title` in this language — after those that have it, and then by the identifier of the record. The secondary order by `title` SHALL apply to an explicit sorting (by a field and by the date of publication); without an explicit sorting the order does not change. The fallback of `title` to the language `default` SHALL NOT apply. It SHALL be possible to sort by any field of the content type, including a multiple one and a field of any type; the sort key of a record SHALL be its first value of the field (with the smallest weight) in the specified language, regardless of the direction and of the filters of the request.

#### Scenario: Numeric sorting
- **WHEN** sorting by `views` in descending order, values `9`, `10`, `100`
- **THEN** the order is `100`, `10`, `9`

#### Scenario: Records without a value
- **WHEN** some records have no value in the sort field (in the specified language)
- **THEN** they are placed after the records with a value in any direction

#### Scenario: Sorting by a multiple field
- **WHEN** sorting in ascending order by the multiple field `dates`, record A has the values `2026-12-20` (first) and `2026-10-01`, record B — `2026-11-05`
- **THEN** the order is B (key `2026-11-05`), A (key `2026-12-20`)

#### Scenario: Multiple field in descending order
- **WHEN** sorting in descending order by the multiple field `dates`, record A has the values `2026-12-20` (first) and `2026-10-01`, record B — `2026-11-05`
- **THEN** the order is A (key `2026-12-20`), B (key `2026-11-05`)

#### Scenario: Filters do not change the key
- **WHEN** the filter `dates >= 2026-11-01`, sorting in ascending order by `dates`, record A has the values `2026-10-01` (first) and `2026-12-20`
- **THEN** record A is included in the selection, and its key remains `2026-10-01`

#### Scenario: Record without values of a multiple field
- **WHEN** a record has no value at all of the multiple sort field in the specified language
- **THEN** it is placed after the records with a key in any direction

#### Scenario: Equal keys are ordered by title
- **WHEN** sorting by `views` in descending order, records with `title` «Beta» (id 1) and «Alpha» (id 2) have the same value of `views`
- **THEN** «Alpha» goes before «Beta», because of the secondary order by `title` in ascending order

#### Scenario: Secondary order does not depend on the direction
- **WHEN** sorting by `views` in ascending order and in descending order, records with an equal `views` have `title` «Alpha» and «Beta»
- **THEN** in both directions «Alpha» goes before «Beta»

#### Scenario: Record without a title in the specified language
- **WHEN** sorting by `views`, and a record with an equal key has no `title` in the specified language, although it has one in the language `default`
- **THEN** it goes after the records with `title` in the specified language, then by the identifier; the `title` of the language `default` is not substituted

#### Scenario: Identical key and title
- **WHEN** two records have identical values of the main key and of `title`
- **THEN** their order is defined by the identifier of the record

#### Scenario: Record is not duplicated
- **WHEN** sorting by a multiple field, and the record has five values
- **THEN** the record appears in the selection and in the page counter once

#### Scenario: Sorting by a field of any type
- **WHEN** sorting by a field of the type `image` or `youtube`
- **THEN** the selection is not rejected with an error, and the records are ordered by the stored string of the first value

### Requirement: Фільтрація за значенням поля
The system SHALL filter records by a field, an operator and a value in the specified language, and the set of permitted operators SHALL be defined by the type of the field: equality for all types; comparison (greater/less/range) for numeric, date and date-time fields; substring inclusion for text ones; the values `true`/`false` for boolean; membership in a list (`in`) for string, text, numeric, date and date-time ones. The filter SHALL work in the same way for a multiple and a non-multiple field: a record matches the filter if at least one value of the field matches it. The operator `in` SHALL accept a non-empty array of values, each of which is checked by the rules of the type of the field, and SHALL consider a record matching if any of its values equals any of the passed ones. Several filters SHALL be combined through AND, and each filter SHALL be checked independently of the others, including for several filters on one field.

#### Scenario: Numeric range
- **WHEN** the filter `price` between `10` and `20`
- **THEN** only records whose `price` is in this range inclusively are returned

#### Scenario: Multiple field
- **WHEN** the filter `tags` equals `news`, and a record has the values `["a", "news"]`
- **THEN** this record is included in the result

#### Scenario: Membership in a list
- **WHEN** the filter `city` with the operator `in` and the value `["Kyiv", "Lviv"]`
- **THEN** records whose `city` equals `Kyiv` or `Lviv` are returned

#### Scenario: Membership in a list for a multiple field
- **WHEN** the filter `tags` with the operator `in` and the value `["news", "sale"]`, and a record has the values `["a", "sale"]`
- **THEN** this record is included in the result

#### Scenario: Invalid value for `in`
- **WHEN** the operator `in` with an empty array or not an array
- **THEN** the request is rejected with a validation error `filters.N.value`

#### Scenario: Several filters on one multiple field
- **WHEN** the filters `dates >= 2026-11-10` and `dates <= 2026-11-15`, and a record has the values `2026-11-01` and `2026-12-20`
- **THEN** the record is included in the result, because each filter is executed separately by different values

#### Scenario: Filter by language
- **WHEN** the filter `title` contains «Hello» with `lang = en`
- **THEN** only records whose English `title` contains «Hello» are returned, regardless of the value of `default`

#### Scenario: Unsupported operator
- **WHEN** the operator «contains» or `in` is applied to a boolean field
- **THEN** the request is rejected with a validation error

#### Scenario: Unknown field
- **WHEN** the filter refers to a key that is not in the content type
- **THEN** the request is rejected with a validation error
