# Spec Delta

## Purpose

The admin menu, which the administrator configures and which all users see: a tree of two levels with links to types and to groups. It makes it possible to bring the needed types into the sidebar without going into the sections of management.

## ADDED Requirements

### Requirement: Структура меню адміна
The menu SHALL consist of items of two kinds: a link to a type and a group. A group SHALL have a translatable name and contain links to types; groups within a group SHALL NOT exist. A link to a type SHALL stand either in the root or in a group. A single type SHALL be in the menu at most once. The order of the items in the root and in a group SHALL be set by the administrator.

#### Scenario: Root and group
- **WHEN** the administrator adds to the root a link to the type A and a group with links to the types B and C
- **THEN** the menu keeps this structure and order

#### Scenario: Nested group is forbidden
- **WHEN** the administrator tries to place a group inside another group
- **THEN** the action is unavailable or is rejected with an error, the menu does not change

#### Scenario: Repeated adding of a type
- **WHEN** the administrator adds a type that is already in the menu
- **THEN** the server rejects the action with a validation error, the menu does not change

### Requirement: Сторінка налаштування меню
The system SHALL provide the page `/admin/settings/navigation` for configuring the menu: adding, deleting, renaming groups, adding and deleting links to types, changing the order and moving a type between the root and a group. The page and all its requests SHALL require the right `settings`; without it the response is 403. The name of a group SHALL be translatable (the value in the language, the base language is edited); an empty name SHALL be rejected. The page SHALL be reachable by the link at the bottom of the sidebar and from the page Site.

#### Scenario: Creating a group
- **WHEN** the administrator with the right `settings` creates a group with the name «Каталог» ("Catalogue")
- **THEN** the group is saved and appears in the menu

#### Scenario: Empty name of a group
- **WHEN** the administrator saves a group with an empty name
- **THEN** the server returns an error next to the field, the group is not saved

#### Scenario: Without the right settings
- **WHEN** a user without the right `settings` opens `/admin/settings/navigation` or sends a request to change the menu
- **THEN** the response is 403

#### Scenario: Deleting a group
- **WHEN** the administrator deletes a group with links
- **THEN** the group and its links are deleted, the types do not change

### Requirement: Меню адміна в sidebar
The sidebar SHALL show the admin menu to all users below the block «Обране» as a separate block with the heading «Navigation» (in the language of the interface; uk: «Навігація») in the same style as the heading «Обране»; without visible items the block and its heading SHALL NOT be displayed. A group SHALL be collapsible, a link to a type SHALL lead to the list of records of the type, and the icon SHALL be defined by the kind of the type (`content`, `page`, `block`); the icon of a group is the same for all groups. The name of a group SHALL be displayed in the language of the current locale, with a fallback to the base language if there is no translation. A group with the active item SHALL be expanded.

#### Scenario: Heading of the block
- **WHEN** the menu has at least one visible item
- **THEN** the heading «Navigation» is shown above it

#### Scenario: Empty menu without a heading
- **WHEN** no item of the menu is visible to the user
- **THEN** the block and the heading are not displayed

#### Scenario: Display of the menu
- **WHEN** a user with access to the types A and B opens the admin, and the menu has a link to A and a group with B
- **THEN** the sidebar shows the link to A and a collapsible group with B

#### Scenario: Navigation by an item of a group
- **WHEN** the user clicks a type inside a group
- **THEN** the list of records of the type opens, the item is active, and its group is expanded

### Requirement: Меню адміна відповідає доступам
A type SHALL be shown in the admin menu only when the user's level of access to the records of this type is not `none`. A group without visible types SHALL be hidden. A change of the access or the deletion of a type SHALL be displayed in the menu without any action of the user. The saved structure of the menu SHALL NOT change because a type is temporarily unavailable to a particular user. Deleting a type SHALL remove its link from the menu.

#### Scenario: Type without access
- **WHEN** a user has no access to a type that is in the menu
- **THEN** this item is absent from his sidebar, while other users have it

#### Scenario: Empty group is hidden
- **WHEN** a user has no access to any type of a group
- **THEN** the group is not displayed

#### Scenario: Type deleted
- **WHEN** a type referenced by the menu is deleted
- **THEN** its item disappears from the menu, the rest of the structure remains
