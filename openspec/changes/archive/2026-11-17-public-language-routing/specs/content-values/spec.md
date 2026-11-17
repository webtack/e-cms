# Spec Delta

## ADDED Requirements

### Requirement: Reading with fallback to the base language
The reading of node values and of a type's own values SHALL support an explicit fallback mode, used by the public site and off by default. In this mode, for each requested field the system SHALL return the value stored in the requested language and, when the field has none (the value is blank according to its type), the value stored in the base language. The fallback SHALL be applied per field: other fields of the same node keep their own language. A multiple field SHALL fall back as a whole list, never mixing items of two languages, and a composed value (a video, a link) SHALL fall back as a whole. The language rules of reading and the default behavior of reading without the mode (a missing translation reads as missing) SHALL NOT change.

#### Scenario: Missing translation falls back
- **WHEN** a node has the base title "Привіт" and no English title, and it is read in English with the fallback mode
- **THEN** the title is "Привіт"

#### Scenario: Translation wins
- **WHEN** the node has the English title "Hello" and it is read in English with the fallback mode
- **THEN** the title is "Hello"

#### Scenario: Fields fall back independently
- **WHEN** a node has an English `title` and only a base `body`, and it is read in English with the fallback mode
- **THEN** the title is English and the body is the base body

#### Scenario: Multiple field
- **WHEN** a multiple field has the base list `a, b` and the English list `c`
- **THEN** the English reading returns `c`; with no English list it returns `a, b`; the lists are never merged

#### Scenario: Strict reading is unchanged
- **WHEN** the same node is read in English without the fallback mode
- **THEN** a field without an English value reads as missing

#### Scenario: Type values
- **WHEN** a type's `section_title` exists only in the base language and is read in English with the fallback mode
- **THEN** the base value is returned
