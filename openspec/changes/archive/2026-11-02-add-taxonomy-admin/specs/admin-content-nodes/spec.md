# Spec Delta

## ADDED Requirements

### Requirement: Поле «термін» у формі та списку записів
The form of a record SHALL show, for a field of the type «термін», the choice of terms of its vocabulary. For a non-multiple field there SHALL be a choice of one term with the possibility to clear it; for a multiple field — a choice of several terms (taking the limit of elements into account). The terms SHALL be shown as a tree with indents by depth, and their names — in the base language. The saved terms SHALL be marked, and an invalid choice (a term of another vocabulary, a non-existent one) SHALL be rejected by the server with an error next to the field. A required field SHALL require a choice. The list of records SHALL show in the column of the field the names of the chosen terms separated by commas (in the base language), and SHALL not offer sorting by this column.

#### Scenario: Choice of one term
- **WHEN** the user chooses the term «Куртки» in the non-multiple field `category` and saves the record
- **THEN** the value is saved, and when the form is reopened «Куртки» is marked

#### Scenario: Choice of several terms
- **WHEN** the user chooses the terms «Новини» and «Спорт» in the multiple field `tags`
- **THEN** both values are saved in the chosen order

#### Scenario: Tree with indents
- **WHEN** the user opens the choice of terms of a vocabulary where «Куртки» is a child of «Одяг»
- **THEN** «Куртки» is shown below «Одяг» with an indent

#### Scenario: Clearing
- **WHEN** the user clears the choice in a non-multiple optional field and saves
- **THEN** the value of the field is deleted

#### Scenario: Names in the list
- **WHEN** the column `tags` is visible in the list of records, and the record has the terms «Новини» and «Спорт»
- **THEN** the cell shows «Новини, Спорт»

#### Scenario: Column is not sorted
- **WHEN** the user opens the menu of the column `category` in the list of records
- **THEN** sorting by this column is unavailable
