# Spec Delta

## MODIFIED Requirements

### Requirement: Structure of the admin menu
The menu SHALL consist of items of three kinds: a link to a type, a link to a node, and a group. A group SHALL have a translatable name and contain links to types and links to nodes; groups within a group SHALL NOT exist. A link to a type or to a node SHALL be either at the root or in a group. One type SHALL be contained in the menu at most once, and one node SHALL be contained in the menu at most once (a type and one of its nodes MAY both be in the menu). The order of items at the root and in a group SHALL be set by the administrator.

#### Scenario: Root and group
- **WHEN** the administrator adds a link to type A at the root and a group with links to types B and C
- **THEN** the menu saves this structure and order

#### Scenario: Link to a node
- **WHEN** the administrator adds a link to node N of type A at the root, and a link to node M into a group
- **THEN** the menu saves both links in the chosen places and order

#### Scenario: Nested group is forbidden
- **WHEN** the administrator attempts to place a group inside another group
- **THEN** the action is unavailable or is rejected with an error, and the menu does not change

#### Scenario: Repeated addition of a type
- **WHEN** the administrator adds a type that is already in the menu
- **THEN** the server rejects the action with a validation error, and the menu does not change

#### Scenario: Repeated addition of a node
- **WHEN** the administrator adds a node that is already in the menu
- **THEN** the server rejects the action with a validation error, and the menu does not change

#### Scenario: Type and its node together
- **WHEN** type A is already in the menu and the administrator adds a node of type A
- **THEN** the node link is added, and the type link stays

### Requirement: Menu settings page
The system SHALL provide the page `/admin/settings/navigation` for configuring the menu: adding, deleting, and renaming groups, adding and deleting links to types and to nodes, changing the order, and moving a type or node link between the root and a group. A node SHALL be added by choosing its type and then finding the node by its title; an unknown node SHALL be rejected with a validation error. The page and all its requests SHALL require the `settings` right; without it the response is 403. The name of a group SHALL be translatable (values per language, the base language is edited); an empty name SHALL be rejected. The page SHALL be reachable through the link at the bottom of the sidebar and from the Site page.

#### Scenario: Creating a group
- **WHEN** an administrator with the `settings` right creates a group named "Catalog"
- **THEN** the group is saved and appears in the menu

#### Scenario: Empty group name
- **WHEN** an administrator saves a group with an empty name
- **THEN** the server returns an error next to the field, and the group is not saved

#### Scenario: Adding a node
- **WHEN** an administrator chooses type A, finds the node "About us" by its title, and adds it
- **THEN** a link to the node appears in the menu on the settings page, labeled with the node title

#### Scenario: Unknown node
- **WHEN** a request adds a node that does not exist
- **THEN** the server returns a validation error, and the menu does not change

#### Scenario: Without the settings right
- **WHEN** a user without the `settings` right opens `/admin/settings/navigation` or sends a request to change the menu
- **THEN** the response is 403

#### Scenario: Deleting a group
- **WHEN** an administrator deletes a group with links
- **THEN** the group and its links are deleted, and the types and nodes do not change

### Requirement: Admin menu in the sidebar
The sidebar SHALL show the admin menu to all users under the "Favorites" block as a separate block with the heading "Navigation" (in the interface language; uk: «Навігація»), in the same style as the "Favorites" heading; without visible items neither the block nor its heading SHALL be displayed. A group SHALL be collapsible, a link to a type SHALL lead to the list of records of the type, and a link to a node SHALL lead to the edit form of the node. The icon of a type link SHALL be determined by the kind of the type (`content`, `page`, `block`), the icon of a node link SHALL be the same for all node links and distinct from type icons, and the icon of a group is the same for all groups. The label of a node link SHALL be the title of the node in the language of the current locale, falling back to the base language, and SHALL change when the node title changes. The name of a group SHALL be displayed in the language of the current locale, falling back to the base language if there is no translation. A group with the active item SHALL be expanded. On the edit page of a node that has a link in the menu, exactly that node link SHALL be active; the link to its type and the section item SHALL NOT be active.

#### Scenario: Block heading
- **WHEN** the menu has at least one visible item
- **THEN** the heading "Navigation" is shown above it

#### Scenario: Empty menu without a heading
- **WHEN** no menu item is visible to the user
- **THEN** the block and the heading are not displayed

#### Scenario: Showing the menu
- **WHEN** a user with access to types A and B opens the admin panel, and the menu has a link to A and a group with B
- **THEN** the sidebar shows the link to A and the collapsible group with B

#### Scenario: Navigation through a group item
- **WHEN** the user clicks a type inside a group
- **THEN** the list of records of the type opens, the item is active, and its group is expanded

#### Scenario: Opening a node link
- **WHEN** the user clicks the link to node N in the sidebar
- **THEN** the edit form of node N opens and this link is the only active item, even if the menu also holds the link to the type of N

#### Scenario: Node title in the label
- **WHEN** the title of a linked node is changed and saved
- **THEN** the next page shows the new title as the label of the link

#### Scenario: Node link in a group
- **WHEN** the user opens the edit form of a node whose link is inside a group
- **THEN** the group is expanded and the node link is active

### Requirement: The admin menu matches access rights
A type SHALL be shown in the admin menu only when the user's access level to the records of this type is not `none`. A node link SHALL be shown only when the user may edit that node: the level on its type is `any`, or the level is `own` and the user is the author of the node. A group without visible links SHALL be hidden. A change of access rights, of the node author, or the deletion of a type or node SHALL be reflected in the menu without any action by the user. The saved structure of the menu SHALL NOT change because a type or node is temporarily unavailable to a particular user. Deleting a type SHALL remove its link and the links to its nodes from the menu; deleting a node SHALL remove its link from the menu.

#### Scenario: Type without access
- **WHEN** the user has no access to a type that is in the menu
- **THEN** this item is absent from their sidebar, while it is present for other users

#### Scenario: Node of another author at level own
- **WHEN** a user has level `own` on type A, and the menu has a link to a node of type A written by another user
- **THEN** that node link is absent from this user's sidebar and present for users who may edit the node

#### Scenario: Node of a type without access
- **WHEN** the user has level `none` on type A, and the menu has a link to a node of type A
- **THEN** the node link is absent from their sidebar

#### Scenario: Empty group is hidden
- **WHEN** the user has no access to any link of a group
- **THEN** the group is not displayed

#### Scenario: Type deleted
- **WHEN** a type to which the menu refers is deleted
- **THEN** its item and the links to its nodes disappear from the menu, and the rest of the structure remains

#### Scenario: Node deleted
- **WHEN** a node to which the menu refers is deleted
- **THEN** its link disappears from the menu, and the rest of the structure remains
