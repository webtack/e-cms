# Spec Delta

## MODIFIED Requirements

### Requirement: Бічна навігація
The shell SHALL contain a side panel with the items Dashboard, Content Types, Page Types, Block Types, Taxonomy, Pages, Media Library and Settings, and SHALL highlight the item that corresponds to the current address. The names of the items SHALL be shown in the interface language of the current user. The item Content Types SHALL open the page of the list of content types, the item Page Types — the page of the list of page types, the item Block Types — the page of the list of block types, the item Taxonomy — the page of the list of vocabularies, and the item Media Library — the media library page.

#### Scenario: Highlighting the active item
- **WHEN** the user is on `/admin/dashboard`
- **THEN** the item Dashboard is marked as active, and the other items are not

#### Scenario: Working item Content Types
- **WHEN** the user follows the item Content Types
- **THEN** `/admin/content-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Page Types
- **WHEN** the user follows the item Page Types
- **THEN** `/admin/page-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Block Types
- **WHEN** the user follows the item Block Types
- **THEN** `/admin/block-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Taxonomy
- **WHEN** the user follows the item Taxonomy
- **THEN** `/admin/taxonomy` opens, the item is marked as active (also on the pages of terms and the forms of the section), and the breadcrumbs show its name

#### Scenario: Working item Media Library
- **WHEN** the user follows the item Media Library
- **THEN** `/admin/media` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Layout items
- **WHEN** the user sees the items Pages and Settings
- **THEN** they are displayed as a layout and do not open working functions of this stage

#### Scenario: Names of the items in Ukrainian
- **WHEN** the user with the language `uk` opens `/admin/dashboard`
- **THEN** the names of the items of the sidebar are displayed in Ukrainian translations, and the highlighting of the active item does not change
