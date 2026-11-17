# Spec Delta

## ADDED Requirements

### Requirement: Public title of a type
The public title of a `content` type SHALL be its `section_title` value (the "Section title" entered on the type's "Display" tab) in the reading language; when the type has no value in that language, the value in the base language; when it has neither, the type's administrative name. An empty value SHALL count as absent. The title SHALL be a plain string, escaped by the template that prints it.

#### Scenario: A translated title
- **WHEN** the type has `section_title` in the reading language
- **THEN** the public title is that value

#### Scenario: A missing translation
- **WHEN** the type has no `section_title` in the reading language but has one in the base language
- **THEN** the public title is the base-language value

#### Scenario: No section title
- **WHEN** the type has no `section_title` in any language
- **THEN** the public title is the type's administrative name

### Requirement: Section title on the list page
The template of a type list page SHALL receive the public title of the type as the variable `$sectionTitle`, in addition to the existing variables.

#### Scenario: The list of a type with a section title
- **WHEN** `/news` shows the list of the type `news` that has the `section_title` `Новини`
- **THEN** the list template receives `$sectionTitle` equal to `Новини`
