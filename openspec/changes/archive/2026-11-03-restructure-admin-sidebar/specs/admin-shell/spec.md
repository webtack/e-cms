# Spec Delta

## MODIFIED Requirements

### Requirement: Бічна навігація
The shell SHALL contain a side panel with the item Dashboard, the block «Обране» (the personal types of the user), the block «Navigation» (the admin menu: links to types and groups configured by the administrator), Media Library and two sections in the root: «Структура» ("Structure") (Content Types, Page Types, Block Types, Taxonomy, Menus) and «Доступ» ("Access") (Users, Roles, Site), and SHALL highlight the item that matches the current address. There SHALL NOT be an item Pages nor a collapsible Settings. Each item of the sections SHALL be shown by the same right as before (the types — by the right `types` or the level `own`/`any` on a type of their kind; Taxonomy, Menus, Users, Roles, Site — by the corresponding system rights); a section without visible items SHALL be hidden. At the bottom of the sidebar there SHALL be a link to the settings of the admin menu (`/admin/settings/navigation`), visible to the users with the right `settings`. The indents between the groups SHALL be compact. The names of the items SHALL be displayed in the language of the interface of the current user. Content Types SHALL open the page of the list of content types, Page Types — of page types, Block Types — of block types, Taxonomy — the list of vocabularies, Menus — the list of menus, Users — users, Roles — roles, Site — the page of the site settings `/admin/settings`, and Media Library — the media library. The block «Обране» and the admin menu are described in the requirements `admin-favorites` and `admin-navigation-menu`.

#### Scenario: Highlighting of the active item
- **WHEN** the user is on `/admin/dashboard`
- **THEN** the item Dashboard is marked as active, and the other items are not

#### Scenario: Item of a section is active
- **WHEN** the user opens `/admin/taxonomy`
- **THEN** the item Taxonomy of the section «Структура» is highlighted, and the breadcrumbs show its name

#### Scenario: Working item Content Types
- **WHEN** the user navigates by the subitem Content Types
- **THEN** `/admin/content-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Page Types
- **WHEN** the user navigates by the subitem Page Types
- **THEN** `/admin/page-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Block Types
- **WHEN** the user navigates by the subitem Block Types
- **THEN** `/admin/block-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Taxonomy
- **WHEN** the user navigates by the subitem Taxonomy
- **THEN** `/admin/taxonomy` opens, the item is marked as active (also on the pages of terms and the forms of the section), and the breadcrumbs show its name

#### Scenario: Working item Menus
- **WHEN** the user navigates by the subitem Menus
- **THEN** `/admin/menus` opens, the item is marked as active (also on the pages of the items of a menu and the forms of the section), and the breadcrumbs show its name

#### Scenario: Working subitems Users and Roles
- **WHEN** a user with the corresponding right navigates by the subitem Users or Roles
- **THEN** `/admin/users` or `/admin/roles` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Media Library
- **WHEN** the user navigates by the item Media Library
- **THEN** `/admin/media` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Settings
- **WHEN** a user with the right to the site settings navigates by the item Site of the section «Доступ»
- **THEN** `/admin/settings` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Items by rights
- **WHEN** the user has only the right `taxonomy`
- **THEN** the sidebar contains the section «Структура» with a single item Taxonomy, and there is no section «Доступ»

#### Scenario: Sections without available items
- **WHEN** the user has no right that opens the items of the sections
- **THEN** neither the section «Структура» nor the section «Доступ» is displayed in the sidebar

#### Scenario: Placeholder items
- **WHEN** the user opens the admin
- **THEN** there is no item Pages in the sidebar

#### Scenario: Link to the settings of the menu
- **WHEN** a user with the right `settings` looks at the bottom of the sidebar
- **THEN** there is a link to `/admin/settings/navigation`; without the right `settings` it is absent

#### Scenario: Item names in Ukrainian
- **WHEN** a user with the language `uk` opens `/admin/dashboard`
- **THEN** the names of the sidebar items are displayed in Ukrainian translations, and the highlighting of the active item does not change
