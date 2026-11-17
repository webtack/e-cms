## ADDED Requirements

### Requirement: One-click status toggle of a menu item
In the items list of a menu, the status badge of an item SHALL be a clickable control that toggles only `is_active` of that item, without a confirmation. A click on an item in the status "Published", "Scheduled" or "Expired" SHALL set `is_active = false`; a click on a "Disabled" item SHALL set `is_active = true`. `published_at`, `unpublished_at`, the title, the address, the parent and the weight SHALL stay unchanged, so after enabling, the badge shows the status that follows from the item's own dates. The change SHALL go through `PATCH /admin/menus/{menu}/items/{item}/active` with a boolean `is_active`, SHALL need the `menus` permission like the rest of the section, SHALL answer with a redirect back and a success message, and the item SHALL be addressed within its menu (an item of another menu gives 404). The control SHALL have an accessible name that says what the click does. The item form SHALL be unchanged.

#### Scenario: Disabling a published item
- **WHEN** the user clicks the "Published" badge of an item
- **THEN** `is_active` becomes false, the badge shows "Disabled", and the dates remain

#### Scenario: Enabling a disabled item
- **WHEN** the user clicks the "Disabled" badge of an item whose `unpublished_at` is in the past
- **THEN** `is_active` becomes true and the badge shows "Expired"

#### Scenario: User without the `menus` permission
- **WHEN** a user without `menus` sends `PATCH /admin/menus/{menu}/items/{item}/active`
- **THEN** the request is refused with 403 and the item is unchanged

#### Scenario: Item of another menu
- **WHEN** the request addresses an item that belongs to another menu
- **THEN** the response is 404
