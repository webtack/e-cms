# Spec Delta

## MODIFIED Requirements

### Requirement: Public slug of a content type
A content type of kind `content` SHALL have an optional `slug` — the public site path that opens the list of nodes of this type. The slug SHALL consist of one or more segments separated by `/`; each segment SHALL consist of lowercase Latin letters, digits, and hyphens without leading, trailing, and repeated hyphens, and the whole slug SHALL be up to 190 characters in length, without a leading, trailing, or repeated `/`. The slug SHALL be unique among all types (case-insensitively); a type without a slug has no public list. For types of kinds `page` and `block` the slug SHALL be empty, and any attempt to specify it SHALL be rejected. The type slug SHALL allow a change after creation (unlike `key` and `kind`), with all the checks preserved, including clearing. The type slug SHALL NOT match the slug of any page, and its first segment SHALL NOT be a reserved site path (see the requirement "Top-level public paths without collisions"). The slug of a type that has a parent type is also bound by the requirement "Hierarchy of type slugs".

#### Scenario: A type with a slug
- **WHEN** a type of kind `content` is created with the slug `news`
- **THEN** the type is saved, and the slug `news` belongs to exactly this type

#### Scenario: A type with a slug of several segments
- **WHEN** a type of kind `content` is created with the slug `common/institutions`
- **THEN** the type is saved, and the slug `common/institutions` belongs to exactly this type

#### Scenario: A type without a slug
- **WHEN** a type of kind `content` is created without a slug
- **THEN** the type is saved, and its slug is empty (there is no public list)

#### Scenario: Duplicate slug
- **WHEN** a type is created with the slug `News`, and another type already has the slug `news`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Duplicate slug of several segments
- **WHEN** a type is created with the slug `Common/Institutions`, and another type already has the slug `common/institutions`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Invalid format
- **WHEN** a type is saved with the slug `About us`, `news_1`, `-news`, or `a--b`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Invalid separators
- **WHEN** a type is saved with the slug `/news`, `news/`, `a//b`, or `a/-b`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Slug for a non-content type
- **WHEN** a type of kind `page` or `block` is created or changed with a slug
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Change of slug
- **WHEN** the slug of an existing type of kind `content` is changed to a free admissible value
- **THEN** the change is saved, and the `key` and `kind` of the type remain unchanged

#### Scenario: Clearing the slug
- **WHEN** the slug of an existing type of kind `content` is cleared
- **THEN** the slug becomes empty, and the type is saved

### Requirement: Top-level public paths without collisions
The slug of a node of kind `page`, the slug of a type of kind `content`, and the reserved site paths SHALL share one namespace of the first segment of the public address, and SHALL NOT match one another (case-insensitively). A type slug of several segments SHALL be compared with the reserved paths by its first segment, and with the slugs of pages as a whole (a page slug has one segment, so only a type slug of one segment can match it). The reserved paths are the service paths of the application and the directories of the public directory: at least `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`; the list SHALL be configurable, and each first segment of a service route of the application SHALL be in it. The slug of a page SHALL NOT equal the slug of any type `content` or a reserved path; the slug of a type `content` SHALL NOT equal the slug of any page, and its first segment SHALL NOT be a reserved path. The slugs of nodes of kinds `content` and `block` do NOT pass this check. The check SHALL be performed on creation of a node or a type and on a change of the slug (and not on every save), in the forms and at the model level, so that an existing match does not block other edits. A violation SHALL reject saving with a validation error of the field `slug`.

#### Scenario: Page with the slug of a type
- **WHEN** a node of kind `page` with the slug `news` is created or changed, and a type of kind `content` has the slug `news`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Type with the slug of a page
- **WHEN** a type of kind `content` is created or gets the slug `about-us`, and a node of kind `page` already has the slug `about-us`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Comparison case-insensitively
- **WHEN** a type is saved with the slug `News`, and a page has the slug `news`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved path for a page
- **WHEN** a node of kind `page` is saved with the slug `admin`, `login`, or `storage`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved path for a type
- **WHEN** a type of kind `content` is saved with the slug `admin`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved first segment of a nested slug
- **WHEN** a type of kind `content` is saved with the slug `admin/reports`
- **THEN** saving is rejected with an error of the field `slug`

#### Scenario: Reserved word inside a nested slug
- **WHEN** a type of kind `content` is saved with the slug `news/admin`
- **THEN** the type is saved, because only the first segment is compared with the reserved paths

#### Scenario: Page with the first segment of a nested type
- **WHEN** a node of kind `page` has the slug `common`, and a type has the slug `common/institutions` and no type has the slug `common`
- **THEN** both are saved, because the page `/common` and the type list `/common/institutions` are different addresses

#### Scenario: Nodes content and block are not checked
- **WHEN** a node of kind `content` is saved with the slug `news` or `admin`, and the type has the slug `news`
- **THEN** the saving passes, because these slugs are not on the first level of the path

#### Scenario: A match in existing data does not block other edits
- **WHEN** a page and a type already have an identical slug (created before this requirement), and the `title` or another field of the page or the name of the type is changed without changing the slug
- **THEN** the change is saved

#### Scenario: Service route without protection
- **WHEN** a service route appears in the application whose first segment is absent from the list of reserved paths
- **THEN** an automatic test fails and requires adding the segment to the list

#### Scenario: Language prefixes
- **WHEN** the slug of a page `en` or `uk` is checked
- **THEN** the requirement does not take language codes into account (they will be added by a separate change about site languages)

## ADDED Requirements

### Requirement: Hierarchy of type slugs
A type of kind `content` that has a parent type and a non-empty slug SHALL have a slug that starts with the slug of the parent type, then `/`, and then a non-empty tail of one or more segments. The parent SHALL have a slug for this: a type with a slug whose parent has no slug SHALL be rejected. A type with a parent and without a slug SHALL remain admissible. A type without a parent MAY have any admissible slug. The comparison with the parent's slug SHALL be case-insensitive. The rule SHALL be checked when the type is created and when its slug or its parent type changes, and not on every save, so that data created earlier does not block other edits. A violation SHALL reject saving with a validation error of the field `slug`.

Changing or clearing the slug of a type SHALL be rejected while at least one of its direct children has a slug; the error SHALL say that the slugs of the child types must be changed first. There SHALL be no automatic change of the slugs of the children. A type whose children have no slugs MAY change its slug freely. The slug of a type MAY be changed when its children have no slug.

#### Scenario: Child slug with the prefix of the parent
- **WHEN** the type `common` has the slug `common`, and a child type is created with the slug `common/institutions`
- **THEN** the child is saved

#### Scenario: Deeper tail
- **WHEN** the child of `common` is created with the slug `common/city/awards`
- **THEN** the child is saved

#### Scenario: Slug without the prefix of the parent
- **WHEN** a child of the type with the slug `common` is created with the slug `institutions` or `other/institutions`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Slug equal to the slug of the parent
- **WHEN** a child of the type with the slug `common` is created with the slug `common`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: A prefix that is not a whole segment
- **WHEN** a child of the type with the slug `common` is created with the slug `commonplace/x`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Parent without a slug
- **WHEN** a child with the slug `common/institutions` is created, and its parent has no slug
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Child without a slug
- **WHEN** a child type is created without a slug, and its parent has a slug
- **THEN** the child is saved without a public list

#### Scenario: Changing the parent
- **WHEN** the parent type of an existing type with the slug `common/institutions` is changed to a type with the slug `collectives`
- **THEN** the change is rejected with a validation error of the field `slug`, and the parent remains the previous one

#### Scenario: Removing the parent
- **WHEN** an existing type with the slug `common/institutions` is saved without a parent type
- **THEN** the type is saved, and its slug remains

#### Scenario: Changing the slug of a parent that has children with slugs
- **WHEN** the slug of the type `common` is changed to `info`, and a child has the slug `common/institutions`
- **THEN** the change is rejected with a validation error of the field `slug` saying that the slugs of the child types must be changed first, and the slugs of both types remain

#### Scenario: Clearing the slug of a parent that has children with slugs
- **WHEN** the slug of the type `common` is cleared, and a child has a slug
- **THEN** the change is rejected with a validation error of the field `slug`

#### Scenario: Changing the slug of a parent whose children have no slugs
- **WHEN** the slug of the type `common` is changed, and its children have no slug
- **THEN** the change is saved

#### Scenario: A type of a deeper level
- **WHEN** a type `deep` has the parent `common/institutions` (itself a child of `common`), and `deep` gets the slug `common/institutions/deep`
- **THEN** the type is saved

#### Scenario: Existing data does not block other edits
- **WHEN** an existing child type has a slug without the prefix of its parent (created before this requirement), and its name is changed without changing the slug or the parent
- **THEN** the change is saved

### Requirement: A node address does not hide a type list
The public address of a node of kind `content` is the slug of its type, `/`, and the slug of the node. When this address equals the slug of a type of kind `content`, the public site shows the list of that type, so the node would be unreachable. Saving such a node SHALL be rejected with a validation error of the field `slug` when the node is created or its slug changes. Likewise, giving a type a slug SHALL be rejected with a validation error of the field `slug` when the slug equals the address of an existing node (the slug of its type, `/`, and the node's slug), or when the type slug of a type with nodes is changed so that the address of one of its nodes becomes equal to the slug of another type. An existing match SHALL NOT block other edits.

#### Scenario: Node address equal to a type slug
- **WHEN** a node with the slug `institutions` is created in the type with the slug `common`, and another type has the slug `common/institutions`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Type slug equal to a node address
- **WHEN** a type is created with the slug `common/work-plan`, and the type with the slug `common` has a node with the slug `work-plan`
- **THEN** saving is rejected with a validation error of the field `slug`

#### Scenario: Node of a type without a slug
- **WHEN** a node is created in a type without a slug
- **THEN** the check does not apply, and the node is saved

#### Scenario: Node address that is not a type slug
- **WHEN** a node with the slug `news` is created in the type with the slug `news`, and no type has the slug `news/news`
- **THEN** the node is saved

#### Scenario: Changing the slug of a type with nodes
- **WHEN** the slug of a type with a node `work-plan` is changed to `common`, and another type has the slug `common/work-plan`
- **THEN** the change is rejected with a validation error of the field `slug`
