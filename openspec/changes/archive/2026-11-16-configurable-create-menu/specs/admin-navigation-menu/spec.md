## ADDED Requirements

### Requirement: Create menu settings
The menu settings page `/admin/settings/navigation` SHALL contain a "Create menu" section where the administrator chooses the types shown in the dashboard "Create" menu and their order. The administrator SHALL be able to add a type that is not yet in the list, remove a type, and change the order by dragging with the mouse or the keyboard. A type SHALL be in the list at most once; adding an unknown or a repeated type SHALL be rejected with a validation error. Saving an empty list SHALL return the dashboard menu to its default behavior (all accessible types, grouped by kind). The section and its requests SHALL require the `settings` right; without it the response is 403. The list is independent of the admin menu tree: a type MAY be in both, in either or in none.

#### Scenario: Adding a type
- **WHEN** an administrator adds the type `news` in the "Create menu" section
- **THEN** `news` appears in the list at the end and the dashboard "Create" menu shows it

#### Scenario: Changing the order
- **WHEN** an administrator moves `page` above `news` in the list
- **THEN** the new order is saved and the dashboard "Create" menu shows `page` before `news`

#### Scenario: Removing the last type
- **WHEN** an administrator removes the last type from the list
- **THEN** the configuration is cleared and the dashboard "Create" menu lists all types accessible to the user, grouped by kind

#### Scenario: Repeated or unknown type
- **WHEN** a request adds a type that is already in the list or does not exist
- **THEN** the server returns a validation error and the list does not change

#### Scenario: Without the settings right
- **WHEN** a user without the `settings` right sends a request to change the "Create menu" list
- **THEN** the response is 403
