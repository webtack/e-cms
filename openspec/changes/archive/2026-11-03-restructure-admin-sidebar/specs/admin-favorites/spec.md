# Spec Delta

## Purpose

The personal menu «Обране» ("Favourites"): the user marks the types he works with most often and sees them in the sidebar as a separate block. The list belongs only to this user and always matches his current access.

## ADDED Requirements

### Requirement: Зірочка «Обране» на сторінці записів типу
The heading of the page of records of a type (of any kind) and the row in the list of types SHALL contain a star that adds the type to the «Обране» of the current user or removes it. The state of the star (selected or not) SHALL match the saved list of the user. The change SHALL be applied without losing the state of the page (the filters and sorting of the table).

#### Scenario: Adding to the favourites
- **WHEN** the user clicks the star on the page of records of a type that is not in his favourites
- **THEN** the star becomes filled, and the type appears in the block «Обране» in the sidebar

#### Scenario: Adding from the list of types
- **WHEN** the user clicks the star in the row of a type in the list of types
- **THEN** the type appears in the block «Обране», and the star of the row becomes filled

#### Scenario: Removing from the favourites
- **WHEN** the user clicks a filled star
- **THEN** the star becomes empty, and the type disappears from the block «Обране»

#### Scenario: The list belongs to the user
- **WHEN** one user adds a type to the favourites
- **THEN** another user does not see this type in his «Обране»

### Requirement: Блок «Обране» в sidebar
The sidebar SHALL show the block «Обране» with the types chosen by the current user, in the order of adding. The item SHALL lead to the list of records of the type, have an icon by the kind of the type (`content`, `page`, `block`) and show the name of the type. The highlighting of the active item SHALL work by the address of the records of the type. Without a single visible item the block SHALL NOT be displayed.

#### Scenario: Navigation from the favourites
- **WHEN** the user clicks a type in the block «Обране»
- **THEN** the list of records of this type opens, and the item is marked as active

#### Scenario: Order
- **WHEN** the user adds the types A and then B in turn
- **THEN** in the block «Обране» A stands before B

#### Scenario: Empty favourites
- **WHEN** the user has not chosen any type
- **THEN** the block «Обране» is not displayed in the sidebar

### Requirement: Обране відповідає доступам
A type SHALL be shown in «Обране» only when the user's level of access to the records of this type is not `none`. The saved types to which access has been revoked, or which have been deleted, SHALL disappear from the displayed list immediately after the change, without any action of the user. Adding to the favourites a type without access SHALL be impossible (the response 403). Deleting a type SHALL remove it from the «Обране» of all users.

#### Scenario: Revoking access
- **WHEN** the administrator changes the role of a user so that the level on the favourite type becomes `none`
- **THEN** at the next opening of a page this type disappears from the block «Обране» of this user

#### Scenario: Restoring access
- **WHEN** a user is again granted access to a type that was previously in his favourites
- **THEN** the type is shown again in the block «Обране»

#### Scenario: Type deleted
- **WHEN** a type is deleted
- **THEN** it disappears from the «Обране» of all users

#### Scenario: Adding without access
- **WHEN** a user without access to the records of a type sends a request to add this type to the favourites
- **THEN** the server responds 403 and the list does not change
