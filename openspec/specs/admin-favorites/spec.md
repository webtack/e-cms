# admin-favorites Specification

## Purpose
A personal "Favorites" menu: the user marks the types they work with most often and sees them in the sidebar as a separate block. The list belongs only to this user and always matches their current access.

## Requirements

### Requirement: "Favorites" star on the page of type records
The heading of a type's records page (of any kind) and a row in the list of types SHALL contain a star that adds the type to the current user's "Favorites" or removes it. The state of the star (selected or not) SHALL match the user's saved list. The change SHALL be applied without losing the page state (the table filters and sorting).

#### Scenario: Adding to favorites
- **WHEN** the user clicks the star on the records page of a type that is not in their favorites
- **THEN** the star becomes filled, and the type appears in the "Favorites" block in the sidebar

#### Scenario: Adding from the list of types
- **WHEN** the user clicks the star in the row of a type in the list of types
- **THEN** the type appears in the "Favorites" block, and the row's star becomes filled

#### Scenario: Removing from favorites
- **WHEN** the user clicks the filled star
- **THEN** the star becomes empty, and the type disappears from the "Favorites" block

#### Scenario: The list belongs to the user
- **WHEN** one user adds a type to favorites
- **THEN** another user does not see this type in their "Favorites"

### Requirement: "Favorites" block in the sidebar
The sidebar SHALL show the "Favorites" block with the types chosen by the current user, in the order of addition. The item SHALL lead to the list of records of the type, have an icon by the kind of the type (`content`, `page`, `block`), and show the name of the type. The highlighting of the active item SHALL work by the address of the type's records. Without any visible item the block SHALL NOT be displayed.

#### Scenario: Navigation from favorites
- **WHEN** the user clicks a type in the "Favorites" block
- **THEN** the list of records of this type opens, and the item is marked as active

#### Scenario: Order
- **WHEN** the user successively adds types A, then B
- **THEN** in the "Favorites" block A stands before B

#### Scenario: Empty favorites
- **WHEN** the user has not chosen any type
- **THEN** the "Favorites" block is not displayed in the sidebar

### Requirement: Favorites match access rights
A type SHALL be shown in "Favorites" only when the user's access level to the records of this type is not `none`. Saved types whose access has been revoked, or which have been deleted, SHALL disappear from the displayed list immediately after the change, without any action by the user. Adding to favorites a type without access SHALL be impossible (response 403). Deleting a type SHALL remove it from the "Favorites" of all users.

#### Scenario: Revoking access
- **WHEN** an administrator changes a user's role so that the level on a favorite type becomes `none`
- **THEN** on the next opening of a page this type disappears from this user's "Favorites" block

#### Scenario: Restoring access
- **WHEN** access to a type that was previously in the user's favorites is granted again
- **THEN** the type is shown again in the "Favorites" block

#### Scenario: Type deleted
- **WHEN** the type is deleted
- **THEN** it disappears from the "Favorites" of all users

#### Scenario: Adding without access
- **WHEN** a user without access to the records of a type sends a request to add this type to favorites
- **THEN** the server responds with 403, and the list does not change
