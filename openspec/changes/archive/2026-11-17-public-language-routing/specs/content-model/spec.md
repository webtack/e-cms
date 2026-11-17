# Spec Delta

## ADDED Requirements

### Requirement: Language codes are reserved first segments
The codes of the content languages (the base language code and every additional language, active or not) SHALL be part of the namespace of the first segment of public addresses and SHALL NOT match, case-insensitively, the slug of a page, the first segment of the slug of a type of kind `content`, or a reserved site path. Saving a page, a type or the list of languages that would create such a match SHALL be rejected with an error on the field that causes it (`slug` of the page or the type; `code` of the language, or the base language code).

#### Scenario: Page named like a language
- **WHEN** the language `en` exists and a page with the slug `en` is saved
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Type named like a language
- **WHEN** the language `pl` exists and a type of kind `content` is saved with the slug `pl/news`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Language named like a page
- **WHEN** a page with the slug `faq` exists and a language with the code `faq` is added
- **THEN** adding is rejected with an error of the field `code`

#### Scenario: Base language code
- **WHEN** the base language is `uk` and a page with the slug `uk` is saved
- **THEN** saving is rejected with an error of the field `slug`
