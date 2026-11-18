# Spec Delta

## ADDED Requirements

### Requirement: Збереження форми редагування лишає на сторінці
A successful saving of changes in an edit form in the admin (content type, page type, block type, field of a type, record, menu, menu item, vocabulary, term, role, user) SHALL keep the user on the same edit page and show a toast with a success message; the form SHALL show the saved values. Creation and deletion are not changed by this. The tabs of the type settings and the site settings SHALL likewise remain on their page.

#### Scenario: Saving changes
- **WHEN** the user changes values in the edit form and presses «Зберегти»
- **THEN** the page address does not change, a success toast is shown, and the form contains the saved values

#### Scenario: Validation error
- **WHEN** the saving is rejected by validation
- **THEN** the user stays on the form, the errors are shown next to the fields, nothing is saved

#### Scenario: Creation
- **WHEN** the user creates a new object
- **THEN** the behaviour after creation does not change
