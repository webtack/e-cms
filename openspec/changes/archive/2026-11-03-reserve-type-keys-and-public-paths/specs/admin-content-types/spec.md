# Spec Delta

## ADDED Requirements

### Requirement: Помилки зарезервованого ключа й колізій slug у формах типу
The form of creating a type SHALL reject a key from the list of reserved ones and show an error next to the field of the key. The forms of creating and editing a type SHALL reject a slug that matches the slug of a record of the kind `page` or a reserved path of the site, and show an error next to the field slug; the rest of the entered values SHALL remain in the form. For the sections of pages and blocks the key SHALL be checked in the same way as for content. The messages SHALL be displayed in the language of the interface.

#### Scenario: Reserved key
- **WHEN** the user enters the key `pages` (or `content`, `blocks`, `fields`, `menus`, `errors`) in the form of creating a type of any section and submits the form
- **THEN** the type is not created, an error «Цей ключ зарезервований» ("This key is reserved") is shown next to the field of the key, the entered value remains in the form

#### Scenario: Slug matches a page
- **WHEN** the user enters the slug `about`, and a record of the kind `page` already has the slug `about`
- **THEN** the type is not created, an error is shown next to the field slug

#### Scenario: Slug matches a reserved path
- **WHEN** the user enters the slug `admin` in the form of editing a type
- **THEN** the change is not saved, an error is shown next to the field slug

#### Scenario: Saving without changing the slug
- **WHEN** an existing type with a slug that once matched the slug of a page is saved with the unchanged slug and a new name
- **THEN** the change is saved
