# Spec Delta

## ADDED Requirements

### Requirement: Menu tree with fallback
The tree of visible items for the site SHALL support an explicit fallback mode: the title and the description of an item missing in the requested language SHALL be replaced by the `default` values, each independently. The address, order, nesting and visibility SHALL NOT depend on the language. Reading without the mode SHALL stay strict.

#### Scenario: Title falls back
- **WHEN** an item has no English title and the tree is requested in English with the fallback mode
- **THEN** the item shows its `default` title and keeps its place and address

#### Scenario: Partial translation
- **WHEN** an item has an English description only
- **THEN** its title is the `default` title and its description is the English one

#### Scenario: Strict tree
- **WHEN** the tree is requested in English without the mode and an item has no English title
- **THEN** the title reads as missing
