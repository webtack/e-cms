# Spec Delta

## ADDED Requirements

### Requirement: Поле «термін» у формі поля
The create and edit forms of a field of the type `content` SHALL offer the value type «термін» among the registered types. The forms of page and block types SHALL not offer «термін», and the server SHALL reject `type = term` for fields of such types with an error next to the type choice. For this type the form SHALL show a mandatory choice of a vocabulary (among the existing ones; if there are no vocabularies, the form SHALL explain that a vocabulary must be created first, with a link to the section «Таксономія»). Saving a field of the type «термін» without a vocabulary or with a non-existent vocabulary SHALL be rejected with an error next to the choice of the vocabulary. The choice of the vocabulary SHALL be blocked in the edit form if the field already has values; the server SHALL reject a change of the vocabulary of such a field. For other types the choice of the vocabulary SHALL not be shown, and the server SHALL reject the setting `vocabulary` for them.

#### Scenario: Creating a field «термін»
- **WHEN** the user creates a field `category` of the type «термін» and chooses the vocabulary «Категорії»
- **THEN** the field is created, in the list of fields it has the type «term»

#### Scenario: Type without a vocabulary
- **WHEN** the user chooses the type «термін» and saves without choosing a vocabulary
- **THEN** a validation error appears next to the choice of the vocabulary, the field is not created

#### Scenario: No vocabularies
- **WHEN** the user chooses the type «термін», and there is no vocabulary at all
- **THEN** the form shows an explanation and a link to the section «Таксономія»

#### Scenario: Blocked vocabulary
- **WHEN** the user opens the editing of a field «термін» that has values
- **THEN** the choice of the vocabulary is blocked

#### Scenario: Page or block type
- **WHEN** the user opens the form of a field of a page or block type
- **THEN** «термін» is absent among the types, and a POST with `type=term` is rejected by validation

#### Scenario: Another type
- **WHEN** the user chooses the type «string»
- **THEN** the choice of the vocabulary is not shown
