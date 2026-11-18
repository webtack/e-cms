# Spec Delta

## ADDED Requirements

### Requirement: Фільтр за полем «термін» охоплює нащадків
The system SHALL support for a field of the type «термін» the operators `eq` and `in`, and SHALL treat each given term as this term together with all its descendants at any depth. A record matches the filter if its value of the field (for a multiple field — at least one) equals any term from the expanded set. The given term SHALL be the `uuid` of an existing term; anything else is a validation error `filters.N.value`. Other operators for this type SHALL be rejected as unsupported.

#### Scenario: Record in a subcategory
- **WHEN** the filter `category` equals «Одяг», and the record has `category` = «Зимові», a descendant of «Одяг» through «Куртки»
- **THEN** this record is included in the result

#### Scenario: Record directly in the term
- **WHEN** the filter `category` equals «Одяг», and the record has `category` = «Одяг»
- **THEN** this record is included in the result

#### Scenario: Record in another branch
- **WHEN** the filter `category` equals «Одяг», and the record has `category` = «Електроніка»
- **THEN** this record is not included in the result

#### Scenario: List of terms
- **WHEN** the filter `category` with the operator `in` and the values «Одяг» and «Електроніка»
- **THEN** records with any of these terms or their descendants are returned, each record once

#### Scenario: Multiple field
- **WHEN** the filter `tags` equals «Новини», and the record has `tags` with the values «Спорт» and «Футбол», where «Футбол» is a descendant of «Новини»
- **THEN** the record is included in the result

#### Scenario: Non-existent term in the filter
- **WHEN** the filter contains a `uuid` that does not exist
- **THEN** the request is rejected with a validation error `filters.N.value`

#### Scenario: Unsupported operator
- **WHEN** the operator `gt` is specified for a field of the type «термін»
- **THEN** the request is rejected with a validation error `filters.N.operator`
