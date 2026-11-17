# Spec Delta

## ADDED Requirements

### Requirement: Reading a term with fallback
The system SHALL be able to read the name and the description of a term in a language with an explicit fallback mode used by the public site: each of the two fields missing in the requested language SHALL be replaced by its `default` value, independently of the other field. Reading without the mode SHALL stay strict (a missing translation reads as missing).

#### Scenario: Name falls back
- **WHEN** the name of a term is read in English with the fallback mode and there is no English name
- **THEN** the `default` name is returned

#### Scenario: Partial translation
- **WHEN** a term has an English description only and is read in English with the fallback mode
- **THEN** the name is the `default` name and the description is the English one

#### Scenario: Strict reading
- **WHEN** the name is read in English without the mode and there is no English name
- **THEN** it reads as missing
