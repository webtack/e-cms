# admin-dashboard Specification

## Purpose
Defines the content of the admin start page `/admin/dashboard`: a fixed set of work and overview widgets that every role sees, each limited to what the user's rights allow.

## Requirements

### Requirement: One dashboard for every role
The dashboard SHALL show the same set of widgets to every authenticated user. The widgets SHALL differ between users only by the data they contain, which is limited by the user's rights; a widget the user has no right to see anything in SHALL NOT be shown. The dashboard SHALL NOT contain charts or system and administrator widgets, and SHALL NOT show the placeholder welcome text.

#### Scenario: Same layout for different roles
- **WHEN** an administrator and an editor with access to some types open `/admin/dashboard`
- **THEN** both see the same widgets in the same order, and only the rows and numbers inside them differ

#### Scenario: Widget without any access is hidden
- **WHEN** a user whose role has level `none` on every type and on media opens `/admin/dashboard`
- **THEN** the page opens successfully, no node or media widget is shown, and an empty-state message is displayed

### Requirement: Rights limit every dashboard query
Every node shown or counted on the dashboard SHALL belong to a type on which the user's level is `own` or `any`. For a type with level `own` only the nodes created by the user SHALL be shown or counted; for `any` all nodes of the type; for `none` the type SHALL be absent from every widget. Media shown or counted SHALL follow the media level in the same way (`none` hides the widget, `own` limits it to the user's uploads, `any` shows all files).

#### Scenario: Level own limits nodes to the author
- **WHEN** a user with level `own` on the type `news` opens the dashboard, and `news` contains nodes of this user and of another user
- **THEN** the counters, recent changes, top by views and scheduled widgets include only the user's nodes of `news`

#### Scenario: Level none hides the type
- **WHEN** a user has level `none` on the type `events`
- **THEN** no node of `events` and no counter row for `events` appears anywhere on the dashboard

#### Scenario: Media level own
- **WHEN** a user with media level `own` opens the dashboard
- **THEN** the media widget counts and lists only the files uploaded by this user

#### Scenario: Media level none
- **WHEN** a user with media level `none` opens the dashboard
- **THEN** the media widget is not shown

### Requirement: Recent changes widget
The dashboard SHALL show the nodes most recently updated, newest first, limited to a short fixed list. Each row SHALL show the node title, its type, its author, and the time of the last update, and SHALL link to the node edit form. A row SHALL link to the edit form only when the user may edit that node; otherwise it SHALL be shown without a link.

#### Scenario: Newest first
- **WHEN** three accessible nodes were updated at different times
- **THEN** they are listed from the most recently updated to the least recently updated

#### Scenario: Link to the edit form
- **WHEN** the user clicks the title of a node they may edit
- **THEN** the edit form of this node opens

### Requirement: My drafts widget
The dashboard SHALL show the nodes created by the current user that are disabled (`is_active` is off), newest update first, limited to a short fixed list, each linking to its edit form. Nodes of other users SHALL NOT appear in this widget, whatever the user's level. The widget SHALL be hidden when the user has no drafts.

#### Scenario: Only own drafts
- **WHEN** an administrator opens the dashboard, and there is a disabled node of the administrator and a disabled node of another user
- **THEN** only the administrator's disabled node is listed in "My drafts"

#### Scenario: No drafts
- **WHEN** the user has no disabled nodes of their own
- **THEN** the "My drafts" widget is not shown

### Requirement: Quick create
The dashboard SHALL offer a "Create" menu. When the administrator has not configured the menu, it SHALL list the types on which the user's level is `own` or `any`, grouped or labeled by kind (content, page, block). When the administrator has configured the menu (see the admin menu settings page), it SHALL list only the configured types, in the configured order, as a flat list without kind headings; a configured type on which the user's level is `none`, and a configured type that no longer exists, SHALL NOT be listed. Choosing a type SHALL open the node creation form of that type. The menu SHALL be hidden when the list to show is empty, including when none of the configured types is accessible to the user.

#### Scenario: Choosing a type
- **WHEN** the user opens the "Create" menu and chooses the type `news`
- **THEN** the creation form of a `news` node opens

#### Scenario: Types without access are not listed
- **WHEN** the user has level `none` on the type `events`
- **THEN** `events` is not in the "Create" menu

#### Scenario: Not configured
- **WHEN** the administrator has not configured the "Create" menu
- **THEN** the menu lists all types accessible to the user, grouped by kind, as before

#### Scenario: Configured list and order
- **WHEN** the administrator has configured the menu with `page`, then `news`, and the user has access to `news`, `page` and `events`
- **THEN** the menu lists `page` and `news`, in this order, as a flat list, and `events` is not listed

#### Scenario: Configured type without access
- **WHEN** the menu is configured with `news` and `events`, and the user has level `none` on `events`
- **THEN** the menu lists `news` only

#### Scenario: No configured type is accessible
- **WHEN** the menu is configured with `events` only, and the user has level `none` on `events`
- **THEN** the "Create" menu is not shown, even though the user may create nodes of other types

#### Scenario: Deleted type in the configuration
- **WHEN** a configured type has been deleted
- **THEN** the menu skips it and lists the remaining configured types

### Requirement: Favorite types tiles
The dashboard SHALL show the user's favorite types as tiles, in the order they were added. A tile SHALL show the type name and the number of nodes of this type the user may see (all nodes for `any`, own nodes for `own`), and SHALL open the node list of the type. A favorite type on which the user's level is `none` SHALL NOT be shown. The widget SHALL be hidden when no favorite is shown.

#### Scenario: Tile with a count
- **WHEN** the user has favorited the type `news`, which has 7 nodes visible to them
- **THEN** a tile `news` with the number 7 is shown, and clicking it opens the node list of `news`

#### Scenario: Favorite without access
- **WHEN** a favorite type's level for the user becomes `none`
- **THEN** its tile is not shown, and it returns when the access returns

### Requirement: Counters by type
The dashboard SHALL show, for every type on which the user's level is not `none`, three numbers: the total count of nodes, the count of published nodes (active, already published and not yet unpublished), and the count of drafts (disabled nodes). Each type row SHALL link to the node list of the type. Types SHALL be ordered by kind and then by name, and types with no nodes SHALL still be listed with zeros.

#### Scenario: Numbers per type
- **WHEN** the type `news` has 10 nodes: 6 published, 2 disabled, 1 scheduled and 1 expired
- **THEN** the row shows total 10, published 6, drafts 2

#### Scenario: Empty type
- **WHEN** an accessible type has no nodes
- **THEN** its row is shown with total 0, published 0 and drafts 0

### Requirement: Top by views widget
The dashboard SHALL show the accessible nodes with the highest view counter, in descending order of views, limited to a short fixed list. Each row SHALL show the title, the type and the number of views. Nodes with zero views SHALL NOT be listed, and the widget SHALL be hidden when no accessible node has views.

#### Scenario: Descending order
- **WHEN** accessible nodes have 500, 120 and 900 views
- **THEN** they are listed in the order 900, 500, 120

#### Scenario: No views
- **WHEN** no accessible node has a view counter above zero
- **THEN** the widget is not shown

### Requirement: Scheduled and expired publications
The dashboard SHALL show two lists of accessible active nodes: scheduled nodes (the publication moment is in the future), ordered by the nearest publication first, and expired nodes (the unpublication moment has passed), ordered by the most recent expiry first. Each list SHALL be limited to a short fixed list, and each row SHALL show the title, the type and the relevant date. An empty list SHALL be hidden, and disabled nodes SHALL NOT appear in either list.

#### Scenario: Scheduled node
- **WHEN** an active accessible node has a publication moment in the future
- **THEN** it appears in the scheduled list with its publication date

#### Scenario: Expired node
- **WHEN** an active accessible node has an unpublication moment in the past
- **THEN** it appears in the expired list with its unpublication date

#### Scenario: Disabled node is ignored
- **WHEN** a disabled node has a publication moment in the future
- **THEN** it appears in neither list

### Requirement: Media widget
For a user with media level `own` or `any` the dashboard SHALL show the number of files, their total size in a human-readable unit, and the most recent uploads (name, size and upload time), each upload linking to its page in the media library. The counts SHALL follow the media level: `any` counts all files, `own` the user's uploads.

#### Scenario: Totals and latest uploads
- **WHEN** a user with media level `any` opens the dashboard, and the library has 12 files
- **THEN** the widget shows 12 files, their total size, and the latest uploads, newest first

#### Scenario: Empty library
- **WHEN** a user with media level `any` opens the dashboard and the library is empty
- **THEN** the widget shows 0 files and an empty-state message instead of the upload list

### Requirement: Responsive widget grid
The dashboard SHALL lay its widgets out in a grid that fits the width of the content area at any screen width: no widget SHALL be wider than the content area, and the page SHALL NOT scroll horizontally because of the widgets. A widget whose content is wider than the available space (for example a long node title) SHALL shorten that content inside its own card instead of widening the card. The number of columns SHALL depend on the width of the content area, not on the width of the browser window, so that it follows the state of the sidebar: one column when the content area is narrow, and two columns when it is wide enough; the grid SHALL NOT have more than two columns. Widgets SHALL keep their order (read by rows, left to right), and widgets in one row SHALL have the same height (the height of the tallest one). The "Recent changes" widget and the latest uploads of the "Media Library" widget SHALL list up to 7 rows; the other list widgets keep up to 5.

#### Scenario: Narrow screen
- **WHEN** a user opens the dashboard in a window about 400 px wide, and a recent node has a very long title
- **THEN** the widgets are one under another, each as wide as the content area, the long title is shortened, and the page has no horizontal scroll

#### Scenario: Wide screen
- **WHEN** a user opens the dashboard on a wide screen with the sidebar expanded
- **THEN** the widgets are in two columns in the usual order, and never in three

#### Scenario: Columns follow the sidebar
- **WHEN** the window width stays the same, and the user collapses the sidebar so that the content area becomes wide enough for two columns (or expands it so that it becomes too narrow)
- **THEN** the grid changes the number of columns to match the width of the content area

#### Scenario: Widgets of different height
- **WHEN** two widgets with a different number of rows are in the same row of the grid
- **THEN** both widgets have the height of the taller one

#### Scenario: Longer recent and media lists
- **WHEN** more than 7 nodes were changed and more than 7 files were uploaded
- **THEN** "Recent changes" shows the 7 newest nodes and the media widget shows the 7 latest uploads

### Requirement: Dashboard texts are localized
All dashboard labels and messages SHALL be displayed in the interface language of the current user, and dates SHALL be formatted for that language.

#### Scenario: Ukrainian interface
- **WHEN** a user with the language `uk` opens `/admin/dashboard`
- **THEN** the widget titles and empty-state messages are displayed in Ukrainian
