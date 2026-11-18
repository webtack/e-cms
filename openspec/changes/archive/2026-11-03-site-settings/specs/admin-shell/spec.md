# Spec Delta

## MODIFIED Requirements

### Requirement: Бічна навігація
The shell SHALL contain a side panel with the items Dashboard, Content Types, Page Types, Block Types, Taxonomy, Menus, Pages, Media Library and Settings, and SHALL highlight the item that matches the current address. The names of the items SHALL be displayed in the language of the interface of the current user. The item Content Types SHALL open the page of the list of content types, the item Page Types — the page of the list of page types, the item Block Types — the page of the list of block types, the item Taxonomy — the page of the list of vocabularies, the item Menus — the page of the list of menus, the item Media Library — the page of the media library, and the item Settings — the page of the site settings.

#### Scenario: Highlighting of the active item
- **WHEN** the user is on `/admin/dashboard`
- **THEN** the item Dashboard is marked as active, and the other items are not

#### Scenario: Working item Content Types
- **WHEN** the user navigates by the item Content Types
- **THEN** `/admin/content-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Page Types
- **WHEN** the user navigates by the item Page Types
- **THEN** `/admin/page-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Block Types
- **WHEN** the user navigates by the item Block Types
- **THEN** `/admin/block-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Taxonomy
- **WHEN** the user navigates by the item Taxonomy
- **THEN** `/admin/taxonomy` opens, the item is marked as active (also on the pages of terms and the forms of the section), and the breadcrumbs show its name

#### Scenario: Working item Menus
- **WHEN** the user navigates by the item Menus
- **THEN** `/admin/menus` opens, the item is marked as active (also on the pages of the items of a menu and the forms of the section), and the breadcrumbs show its name

#### Scenario: Working item Media Library
- **WHEN** the user navigates by the item Media Library
- **THEN** `/admin/media` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Settings
- **WHEN** a user with the right to the site settings navigates by the item Settings
- **THEN** `/admin/settings` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Placeholder item
- **WHEN** the user sees the item Pages
- **THEN** it is displayed as a mock-up and does not open working functions of this stage

#### Scenario: Item names in Ukrainian
- **WHEN** a user with the language `uk` opens `/admin/dashboard`
- **THEN** the names of the sidebar items are displayed in Ukrainian translations, and the highlighting of the active item does not change
