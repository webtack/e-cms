# Spec Delta

## MODIFIED Requirements

### Requirement: Edit form of a type
The admin panel SHALL have the page `/admin/content-types/{key}/edit`, available only to authenticated users with a verified email, with a form pre-filled with the current values of a type of kind `content`: name, slug, parent type, description, and the settings of the node list (the "Node list" section). The key SHALL be shown read-only with an explanation that it is immutable. The kind is absent from the form. For a non-existent key, and for a type of kind `page` or `block`, the page SHALL respond "not found". The form SHALL have a save button and a "Cancel" link.

The "Node list" section SHALL allow: (1) selecting the columns of the list among the fields of the type that have values in the nodes, and the service columns "Slug", "Status", "Active", "Creation date", "Update date", "Publication date", "Unpublishing date", "Views", and setting their order by dragging (the column `title` is always present and is not turned off, but its position can be changed; the order is changed also from the keyboard, and there are no "up/down" buttons); (2) selecting the search fields among the text fields of the type; (3) selecting the default sorting — the field and the direction. The options of the sorting field SHALL be: the service "Status", "Active", "Creation date", "Update date", "Publication date", "Unpublishing date", "Views" and the fields of the type that can be sorted (without file fields, `youtube`, and `term`); the slug is not sortable. The initial choice for a type without settings SHALL be "Creation date" in ascending order, and for the columns — `title` first, then the slug, the status, and the publication date, as in the list without settings. The section SHALL be the same for types of all kinds (`content`, `page`, `block`).

#### Scenario: Opening the form
- **WHEN** the user opens `/admin/content-types/news/edit` for an existing type `content`
- **THEN** they see the form with the current name, slug, parent type, and description, the "Node list" section with the current settings, and the key `news` read-only

#### Scenario: Type without node list settings
- **WHEN** the user opens the settings of a type that has no `settings.list`
- **THEN** the section has the columns slug, status, and publication date selected, search by all text fields, and the sorting "Creation date" in ascending order

#### Scenario: Dragging columns
- **WHEN** the user drags the column `title` to the third position or moves it by the keyboard
- **THEN** the order in the form changes, and the checkbox `title` remains checked and disabled for changes

#### Scenario: Sorting options
- **WHEN** the type has the fields `price` (number), `image` (file), and `tags` (term)
- **THEN** the sorting options include "Status", "Active", "Creation date", "Update date", "Publication date", "Unpublishing date", "Views", and `price`, and `image`, `tags`, and the slug are absent

#### Scenario: Non-existent type
- **WHEN** the user opens the edit form for a non-existent key
- **THEN** the response is "not found"

#### Scenario: Type of another kind
- **WHEN** the user opens the edit form for the key of a type of kind `page` or `block`
- **THEN** the response is "not found"

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens the edit form or submits it
- **THEN** they are redirected to the login page, and the type is not changed

### Requirement: Saving changes of a type
Submitting the edit form SHALL update the name, the slug, the parent type, the description, and the node list settings of the type by the same rules as creation; the uniqueness of the slug SHALL be checked excluding the type itself. The key and the kind SHALL NOT change, even if they are passed in the request. An empty slug clears the value. The type itself or its descendant SHALL NOT be selectable as the parent. The node list settings SHALL be stored in `settings.list` (`visible`, `search`, `sort`) and update only this key, without changing the other keys of `settings`. The keys in `visible` and `search` SHALL belong to the fields of the type or (only in `visible`) to the key `title` or the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`; the sorting field — to a field of the type or a service key from this list, except `slug`; the direction — `asc` or `desc`; unknown keys SHALL be rejected with an error next to the section. The choice of sorting "Creation date" in ascending order SHALL not store `sort`, and an empty choice of columns and search SHALL not store the corresponding keys. The edit form SHALL NOT change `settings.public`: this key is saved only by the "Display" tab, and an edit request with `settings.public` SHALL be rejected with a validation error. After success, the user SHALL remain on the edit form of the type with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the slug of an existing type to free admissible values and saves
- **THEN** the changes are saved, the key and the kind have not changed, and the user remains on the edit form of the type with a success message

#### Scenario: Saving one's own slug
- **WHEN** the user saves the form without changing the slug of the type
- **THEN** there is no uniqueness error

#### Scenario: Slug of another type
- **WHEN** the user enters a slug that another type already has
- **THEN** the changes are not saved, and an error is shown next to the slug field

#### Scenario: Clearing the slug
- **WHEN** the user erases the slug and saves
- **THEN** the slug of the type is empty

#### Scenario: Key in the request is ignored
- **WHEN** a different `key` or `kind` is passed in the edit request
- **THEN** the key and the kind of the type remain unchanged

#### Scenario: Cycle in the hierarchy
- **WHEN** the type itself or its descendant is selected as the parent
- **THEN** the changes are not saved, and an error is shown next to the parent type field

#### Scenario: Saving columns, search, and sorting
- **WHEN** the user leaves `title` first, selects the columns `price`, status, `is_active`, `created_at`, `unpublished_at` in this order, searches by `title`, selects the sorting "Update date" in descending order, and saves
- **THEN** the `settings.list` of the type is `visible: ["title", "price", "status", "is_active", "created_at", "unpublished_at"]`, `search: ["title"]`, `sort: {field: "updated_at", direction: "desc"}`, and the node list shows these columns and this order

#### Scenario: Views as a column and as the sorting
- **WHEN** the user selects the column "Views" and the sorting "Views" in descending order, and saves
- **THEN** the `settings.list` of the type has `views` in `visible` and `sort: {field: "views", direction: "desc"}`

#### Scenario: Position of title is kept
- **WHEN** the user places `title` in the second position after `price` and saves
- **THEN** `settings.list.visible` begins with `["price", "title"]`

#### Scenario: Default sorting is not stored
- **WHEN** the user leaves the sorting "Creation date" in ascending order and saves
- **THEN** there is no `sort` key in `settings.list`

#### Scenario: Unknown field in the list settings
- **WHEN** the request contains in `visible` a key that is absent among the fields of the type and the service keys, or `slug` in `sort.field`
- **THEN** the changes are not saved, and an error is shown next to the "Node list" section

#### Scenario: Other settings keys are kept
- **WHEN** the type has another key in `settings` (in particular `public`), and the user saves the form with a changed list
- **THEN** the other key of `settings` remains unchanged

#### Scenario: Public settings in the edit form
- **WHEN** an edit request of a type contains `settings.public`
- **THEN** the changes are not saved, the response contains a validation error, and `settings.public` of the type is not changed

#### Scenario: Types of pages and blocks
- **WHEN** the user saves the node list settings in a type of kind `page` or `block`
- **THEN** they are saved and apply in the node list of the same section by the same rules
