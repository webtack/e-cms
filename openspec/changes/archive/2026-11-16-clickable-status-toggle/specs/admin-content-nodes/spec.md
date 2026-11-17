## ADDED Requirements

### Requirement: One-click state toggle in the node list
In the node list, the state badge (the `status` column) of a row the user may edit SHALL be a clickable control that toggles only `is_active` of the node, without a confirmation. A click on a node in the state "Published", "Scheduled" or "Expired" SHALL set `is_active = false`; a click on a "Disabled" node SHALL set `is_active = true`. `published_at`, `unpublished_at` and all values SHALL stay unchanged, so after enabling, the badge shows the state that follows from the dates (a node with a future `published_at` becomes "Scheduled", one with a reached `unpublished_at` becomes "Expired"). The change SHALL go through `PATCH {section}/{contentType}/nodes/{node}/active` with a boolean `is_active`, SHALL be authorized by the same policy as editing the node, SHALL answer with a redirect back and a success message, and SHALL move `updated_at`. For a row the user may not edit, the badge SHALL be shown as before, not clickable. The control SHALL have an accessible name that says what the click does. The edit form SHALL be unchanged.

#### Scenario: Unpublishing a published node
- **WHEN** the user clicks the "Published" badge of a node in the list
- **THEN** `is_active` becomes false, the badge shows "Disabled", and the dates are unchanged

#### Scenario: Enabling a disabled node with a future date
- **WHEN** the user clicks the "Disabled" badge of a node whose `published_at` is in the future
- **THEN** `is_active` becomes true and the badge shows "Scheduled"

#### Scenario: Clicking a scheduled or expired node
- **WHEN** the user clicks the "Scheduled" or "Expired" badge
- **THEN** `is_active` becomes false and the badge shows "Disabled"

#### Scenario: A user who may not edit the node
- **WHEN** a user without the right to edit a node views it in the list, or sends `PATCH .../active` for it
- **THEN** the badge is not clickable, and the request is refused with 403 without changing the node

#### Scenario: Invalid value
- **WHEN** the request has no boolean `is_active`
- **THEN** the response is a validation error and the node is unchanged
