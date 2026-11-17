# Spec Delta

## ADDED Requirements

### Requirement: Error of a node slug that hides a type list in the node form
The create and edit forms of a node of kind `content` SHALL reject a slug that makes the address of the node (the slug of its type, `/`, and the slug of the node) equal to the slug of a type of kind `content`, and show an error next to the slug field; the rest of the entered data SHALL remain in the form. The check applies on creation and on a change of the slug, and does not apply to a type without a slug or to nodes of kinds `page` and `block`.

#### Scenario: Slug that hides a type list
- **WHEN** the user enters the slug `institutions` in the form of a node of the type with the slug `common`, and another type has the slug `common/institutions`
- **THEN** the node is not saved, and an error is shown next to the slug field

#### Scenario: Slug of a node of a type without a slug
- **WHEN** the user enters any admissible slug in the form of a node of a type without a slug
- **THEN** the node is saved

#### Scenario: Saving without changing the slug
- **WHEN** an existing node whose address equals a type slug (created earlier) is saved with the unchanged slug and a new title
- **THEN** the change is saved
