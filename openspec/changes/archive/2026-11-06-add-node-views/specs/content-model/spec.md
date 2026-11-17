# Spec Delta

## MODIFIED Requirements

### Requirement: Service attributes of a node
Each content node SHALL have service attributes: `published_at` (the date and time of publication, required), `unpublished_at` (the date and time of unpublishing, optional), `is_active` (boolean, default `true`), `user_id` (the author, required, a reference to a user), and `views` (the view counter, a non-negative integer, default `0`). `published_at`, `unpublished_at`, and `is_active` SHALL be editable. The author SHALL be set by the system from the user who creates the node, SHALL NOT accept a value from the request, and SHALL NOT change after creation. A user who is the author of a node SHALL NOT be deleted. If `published_at` is not specified on creation, the system SHALL substitute the current moment; clearing `published_at` of an existing node SHALL be impossible. Past and future values of `published_at` SHALL be admissible. An empty `unpublished_at` SHALL mean "no unpublishing date", and clearing it for an existing node SHALL be possible. When `unpublished_at` is set, it SHALL be later than `published_at` (equality is not admissible); this SHALL apply also when `published_at` is substituted by the system. A past date of `unpublished_at` SHALL be admissible. Disabling a node (`is_active = false`) SHALL NOT change `published_at` and `unpublished_at`. For nodes that existed before these attributes appeared, `published_at` SHALL equal their creation date, `unpublished_at` SHALL be empty, and the author SHALL be the administrator from `ADMIN_EMAIL`.

The `views` counter SHALL belong to nodes of every kind (`content`, `page`, `block`), SHALL NOT depend on the language, and SHALL NOT be a field of the type. It SHALL NOT be accepted from a request (the node forms of the admin panel ignore it), but the code that creates a node SHALL be able to set the initial value (for example, when importing nodes from another site). A negative value SHALL be rejected with a validation error of the field `views`. The counter SHALL be increased atomically by a given amount (one by default): concurrent increases are not lost, and increasing the counter SHALL NOT change `updated_at`, `published_at` or any other attribute of the node. Nodes that existed before the counter appeared SHALL have `views = 0`.

#### Scenario: Default values
- **WHEN** a node is created without `published_at`, `unpublished_at`, and `is_active`
- **THEN** `is_active` equals `true`, `published_at` is the moment of creation, and `unpublished_at` is empty

#### Scenario: Author from the current user
- **WHEN** an authenticated user creates a node
- **THEN** this user becomes the author of the node

#### Scenario: Author does not change
- **WHEN** an update request of a node contains a different `user_id`
- **THEN** the author of the node remains the previous one, and there is no error

#### Scenario: Author is not accepted on creation
- **WHEN** a creation request contains the `user_id` of another user
- **THEN** the author becomes the current user

#### Scenario: A node without an author is impossible
- **WHEN** a node is created without a user (neither an explicit author nor the current user)
- **THEN** the creation is rejected, and the node is not saved

#### Scenario: Deleting an author
- **WHEN** an attempt is made to delete a user who is the author of at least one node
- **THEN** the user is not deleted

#### Scenario: Disabling keeps the date
- **WHEN** a node with `published_at` in the future and a set `unpublished_at` is disabled (`is_active = false`)
- **THEN** `published_at` and `unpublished_at` do not change

#### Scenario: Unpublishing date later than the publication date
- **WHEN** a node is saved with `published_at` 2026-12-01 10:00 and `unpublished_at` 2026-12-31 10:00
- **THEN** the node is saved with both dates

#### Scenario: Unpublishing date not later than the publication date
- **WHEN** a node is saved with an `unpublished_at` that equals or precedes `published_at`
- **THEN** saving is rejected with a validation error of the field `unpublished_at`, and the node is not changed

#### Scenario: Unpublishing date against the substituted publication date
- **WHEN** a node is created without `published_at`, and `unpublished_at` is in the past relative to the moment of creation
- **THEN** the creation is rejected with a validation error of the field `unpublished_at`

#### Scenario: Clearing the unpublishing date
- **WHEN** the date of a node with `unpublished_at` is cleared and saved
- **THEN** `unpublished_at` is empty, and the node is again without an unpublishing date

#### Scenario: Migration of existing nodes
- **WHEN** a migration is performed over a table with existing nodes
- **THEN** each node has `published_at` = the creation date, `unpublished_at` empty, `is_active = true`, and the author — the administrator from `ADMIN_EMAIL`

#### Scenario: No administrator for existing nodes
- **WHEN** a migration is performed over a non-empty table of nodes, and there is no user with `ADMIN_EMAIL`
- **THEN** the request is rejected with a validation error

#### Scenario: Default view counter
- **WHEN** a node of any kind (`content`, `page` or `block`) is created without a counter value
- **THEN** its `views` equals `0`

#### Scenario: Initial counter set by the creating code
- **WHEN** code creates a node and passes the initial `views` 1250
- **THEN** the node is saved with `views` equal to 1250

#### Scenario: Counter is not accepted from a request
- **WHEN** a create or update request of a node in the admin panel contains `views`
- **THEN** the value is ignored: a new node has `views = 0`, and an existing node keeps its counter

#### Scenario: Negative counter
- **WHEN** a node is saved with a negative `views`
- **THEN** saving is rejected with a validation error of the field `views`, and the node is not changed

#### Scenario: Increasing the counter
- **WHEN** the counter of a node with `views = 5` is increased by one, and then by 10
- **THEN** `views` equals 16

#### Scenario: Increasing does not touch other attributes
- **WHEN** the counter of a node is increased
- **THEN** `updated_at`, `published_at`, `is_active` and the values of the node do not change

#### Scenario: Migration gives existing nodes a zero counter
- **WHEN** the migration that adds the counter is performed over a table with existing nodes
- **THEN** each of them has `views = 0`, and the rollback of the migration removes the counter
