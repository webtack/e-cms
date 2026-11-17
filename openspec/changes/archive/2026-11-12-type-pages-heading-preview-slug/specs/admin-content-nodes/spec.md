## ADDED Requirements

### Requirement: Limiting the width of the slug column
The service column "Slug" of the node table SHALL have a limited width: a long slug SHALL be cut into one line with an ellipsis, without stretching the table or pushing the other columns. The full slug SHALL be available in a hint.

#### Scenario: Long slug
- **WHEN** a node has a slug longer than the width of the column
- **THEN** the slug takes one line and ends with an ellipsis, and the other columns remain in place

#### Scenario: Full slug in the hint
- **WHEN** the user hovers over a cut slug
- **THEN** they see the full slug

## MODIFIED Requirements

### Requirement: Node list page
The admin panel SHALL have the node list pages `/admin/content-types/{key}/nodes`, `/admin/page-types/{key}/nodes`, and `/admin/block-types/{key}/nodes`, available only to authenticated users with a verified email. Each shows the nodes of the type with this key in a data table in server mode: search, sorting, pagination, and page size are performed by the server, and the state of the table is kept in the address. The section SHALL accept only types of its own kind (`content`, `page`, `block` respectively). The table SHALL NOT have filters. The page SHALL have no title of its own: the heading is the name of the type shown by the horizontal section menu of the type above the tabs (see `admin-content-types`), with the current item "Nodes", and the favorites star of the type SHALL stand next to this heading. For a key that is not among the types of the section's kind (in particular a key of a type of another kind), the page SHALL respond with 404. The page SHALL open also for a type without nodes.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/nodes`
- **THEN** they see the table of nodes of the type `news`, a heading with the name of the type above the section menu, where "Nodes" is current, and no title "Nodes of “…”"; there is no filter button in the table toolbar

#### Scenario: Favorites star
- **WHEN** the user opens the node list of a type
- **THEN** the favorites star is next to the heading with the name of the type, and clicking it adds the type to the favorites or removes it, as before

#### Scenario: Nodes of pages and blocks
- **WHEN** an authenticated user opens `/admin/page-types/home/nodes` for a type of kind `page` or `/admin/block-types/hero/nodes` for a type of kind `block`
- **THEN** they see the table of nodes of this type, a heading with the name of the type, and the section menu, where "Nodes" is current

#### Scenario: Nodes of this type only
- **WHEN** there are nodes of the types `news` and `product`, and the user opens the list of the type `news`
- **THEN** the table contains only the nodes of the type `news`

#### Scenario: Empty state
- **WHEN** a type has no nodes
- **THEN** the page opens with the standard message of the table about the absence of data, and the "Create" button is visible

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/content-types/home/nodes` for the key of a type of kind `page`, or `/admin/page-types/hero/nodes` for the key of a type of kind `block`
- **THEN** the server responds with 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/nodes` or `/admin/page-types/home/nodes`
- **THEN** they are redirected to the login page
