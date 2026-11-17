## MODIFIED Requirements

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
