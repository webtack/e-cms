# Spec Delta

## MODIFIED Requirements

### Requirement: Sidebar navigation
The shell SHALL contain a sidebar with the items Dashboard, Content Types, Pages, Media Library and Settings and SHALL highlight the item that matches the current address. The names of the items SHALL be displayed in the interface language of the current user.

#### Scenario: Highlighting the active item
- **WHEN** the user is on `/admin/dashboard`
- **THEN** the Dashboard item is marked as active, and the other items are not

#### Scenario: Layout items
- **WHEN** the user sees the items Content Types, Pages, Media Library and Settings
- **THEN** they are displayed as a layout and do not open working features of this stage

#### Scenario: Item names in Ukrainian
- **WHEN** a user with language `uk` opens `/admin/dashboard`
- **THEN** the sidebar item names are displayed with Ukrainian translations, and the highlighting of the active item does not change
