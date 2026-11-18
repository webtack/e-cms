# Spec Delta

## ADDED Requirements

### Requirement: Sidebar за правами
The sidebar SHALL show only the sections to which the role of the current user has access, and contain the items «Користувачі» ("Users") and «Ролі» ("Roles") for users with the corresponding rights. The items of content types are shown if there is the level `own` or `any` on at least one type of the corresponding kind.

#### Scenario: Role without access to menus
- **WHEN** a user without the right to menus opens the admin
- **THEN** there is no item «Menus» in the sidebar

#### Scenario: Administrator
- **WHEN** a user of the role `admin` opens the admin
- **THEN** the sidebar contains all items, including «Користувачі» and «Ролі»
