# Spec Delta

## MODIFIED Requirements

### Requirement: Тип контенту визначається рядком у БД
The system SHALL allow creating a content type as a row in the DB with a unique machine key, a name and an optional text description (`description`), without new migrations and code changes. The key of a type SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters), SHALL NOT match a reserved key (`content`, `pages`, `blocks`, `fields`, `menus`, `errors`: the names of the service folders of the templates of the site) and SHALL NOT change after creation; the name and the description may be changed. The prohibition of reserved keys SHALL apply to new types and SHALL NOT change or break the existing types.

#### Scenario: Creating a type
- **WHEN** a content type with the key `article` and the name «Стаття» («Article») is created
- **THEN** the type is saved, and it can be used to create content records; the description is not required

#### Scenario: Description of a type
- **WHEN** a content type is created or changed with an arbitrary text description
- **THEN** the description is saved and returned on reading; a missing description is `null`

#### Scenario: Duplicate key
- **WHEN** a content type is created with a key that already exists
- **THEN** the system rejects the creation with a uniqueness error

#### Scenario: Invalid format of the key
- **WHEN** a content type is created with the key `My Type` or `1news`
- **THEN** the creation is rejected with a validation error

#### Scenario: Reserved key
- **WHEN** a type of any kind is created with the key `content`, `pages`, `blocks`, `fields`, `menus` or `errors`
- **THEN** the creation is rejected with a validation error for the key `key`, the type is not created

#### Scenario: A key similar to a reserved one
- **WHEN** a type is created with the key `pages_list` or `page`
- **THEN** the type is created, because the match is checked only against the full reserved key

#### Scenario: Existing type with a reserved key
- **WHEN** the DB already has a type whose key is among the reserved ones, and its name, description or settings are changed
- **THEN** the change is saved, because the prohibition applies only on creation

#### Scenario: Attempt to change the key of a type
- **WHEN** the `key` of an existing content type is changed
- **THEN** the change is rejected, the key stays the previous one; changing the name is allowed

### Requirement: Публічний slug типу контенту
A content type of the kind `content` SHALL have an optional `slug` — the public path of the site that opens the list of records of this type. The slug SHALL consist of lower-case Latin letters, digits and hyphens without leading, trailing and repeated hyphens, up to 190 characters long. The slug SHALL be unique among all types (case-insensitive); a type without a slug has no public list. For types of the kinds `page` and `block` the slug SHALL be empty, and any attempt to specify it SHALL be rejected. The slug of a type SHALL be allowed to change after creation (unlike `key` and `kind`) with all the checks preserved, including clearing. The slug of a type SHALL NOT match the slug of any page nor a reserved path of the site (see the requirement «Public first-level paths without collisions»).

#### Scenario: Type with a slug
- **WHEN** a type of the kind `content` is created with the slug `news`
- **THEN** the type is saved, and the slug `news` belongs exactly to this type

#### Scenario: Type without a slug
- **WHEN** a type of the kind `content` is created without a slug
- **THEN** the type is saved, and its slug is empty (there is no public list)

#### Scenario: Duplicate slug
- **WHEN** a type is created with the slug `News`, and another type already has the slug `news`
- **THEN** the saving is rejected with a validation error of the field `slug`

#### Scenario: Invalid format
- **WHEN** a type is saved with the slug `Про нас` («Про нас» ("About us")), `news_1`, `-news` or `a--b`
- **THEN** the saving is rejected with a validation error of the field `slug`

#### Scenario: Slug for a non-content type
- **WHEN** a type of the kind `page` or `block` is created or changed with a slug
- **THEN** the saving is rejected with a validation error of the field `slug`

#### Scenario: Change of slug
- **WHEN** the slug of an existing type of the kind `content` is changed to a free admissible value
- **THEN** the change is saved, and the `key` and `kind` of the type stay unchanged

#### Scenario: Clearing the slug
- **WHEN** the slug of an existing type of the kind `content` is cleared
- **THEN** the slug becomes empty, the type is saved

### Requirement: Автоматичний slug для сторінок і контенту
For records of types with the kind `content` or `page` the system SHALL, on creation without an explicit slug, form it from the `title` in the language `default` (transliteration of Cyrillic, lower case, the separator `-`), and on a match with an existing slug SHALL add a numeric suffix (`-2`, `-3`, …). For records of the kind `page` a match SHALL also be considered the slug of any type `content` and a reserved path of the site, so the formed slug never violates the requirement «Public first-level paths without collisions». An explicitly specified slug SHALL be checked for admissibility and uniqueness without automatic changes. A change of the `title` of an existing record does NOT change its slug. The prefix `block-` is NOT restricted for these kinds and the automatic prefix is NOT added to the slug.

#### Scenario: Slug from the title
- **WHEN** a record of the type `page` with the `title` «Про нас» is created without a slug
- **THEN** the slug is formed from the name (for example `pro-nas`)

#### Scenario: Slug match
- **WHEN** the slug formed from the `title` already exists
- **THEN** a numeric suffix is added to it (`pro-nas-2`)

#### Scenario: Match with a reserved path
- **WHEN** a record of the kind `page` with the `title` «Admin» is created without a slug
- **THEN** the slug equals `admin-2`, not `admin`

#### Scenario: Match with the slug of a type
- **WHEN** a record of the kind `page` with the `title` «News» is created without a slug, and a type of the kind `content` already has the slug `news`
- **THEN** the slug equals `news-2`

#### Scenario: Record content does not depend on the slugs of types
- **WHEN** a record of the kind `content` with the `title` «News» is created without a slug, and the type has the slug `news`
- **THEN** the slug of the record equals `news`, because a record `content` does not stand on the first level of the path

#### Scenario: Explicit slug
- **WHEN** a record `content` is created with the explicit slug `about`
- **THEN** exactly `about` is saved; if it is taken, the creation is rejected

#### Scenario: Title without suitable characters
- **WHEN** the `title` contains no character suitable for a slug
- **THEN** the fallback slug `node` is used, with a numeric suffix if needed

#### Scenario: Slug with the prefix block- for a page
- **WHEN** a record of the type `page` with the `title` «Block-1» is created without a slug
- **THEN** the slug `block-1` is saved unchanged and without an additional prefix

#### Scenario: Change of title
- **WHEN** the `title` of an existing record is changed
- **THEN** its slug stays unchanged

## ADDED Requirements

### Requirement: Публічні шляхи першого рівня без колізій
The slug of a record of the kind `page`, the slug of a type of the kind `content` and the reserved paths of the site SHALL share one space of the first segment of the public address and SHALL NOT match each other (case-insensitive). The reserved paths are the service paths of the application and the directories of the public directory: at least `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`; the list SHALL be configurable, and each first segment of a service route of the application SHALL be included in it. The slug of a page SHALL NOT equal the slug of any type `content` nor a reserved path; the slug of a type `content` SHALL NOT equal the slug of any page nor a reserved path. The slugs of records of the kinds `content` and `block` do NOT pass this check. The check SHALL be performed on creation of a record or a type and on a change of the slug (and not on every save), in the forms and at the level of the model, so an existing match does not block other edits. A violation SHALL reject the saving with a validation error of the field `slug`.

#### Scenario: Page with the slug of a type
- **WHEN** a record of the kind `page` with the slug `news` is created or changed, and a type of the kind `content` has the slug `news`
- **THEN** the saving is rejected with an error of the field `slug`

#### Scenario: Type with the slug of a page
- **WHEN** a type of the kind `content` is created or receives the slug `about-us`, and a record of the kind `page` already has the slug `about-us`
- **THEN** the saving is rejected with an error of the field `slug`

#### Scenario: Comparison without regard to case
- **WHEN** a type is saved with the slug `News`, and a page has the slug `news`
- **THEN** the saving is rejected with an error of the field `slug`

#### Scenario: Reserved path for a page
- **WHEN** a record of the kind `page` is saved with the slug `admin`, `login` or `storage`
- **THEN** the saving is rejected with an error of the field `slug`

#### Scenario: Reserved path for a type
- **WHEN** a type of the kind `content` is saved with the slug `admin`
- **THEN** the saving is rejected with an error of the field `slug`

#### Scenario: Records content and block are not checked
- **WHEN** a record of the kind `content` is saved with the slug `news` or `admin`, and the type has the slug `news`
- **THEN** the saving passes, because these slugs do not stand on the first level of the path

#### Scenario: A match in the existing data does not block other edits
- **WHEN** a page and a type already have the same slug (created before this requirement), and they change the `title` or another field of the page or the name of the type without changing the slug
- **THEN** the change is saved

#### Scenario: Service route without protection
- **WHEN** a service route appears in the application whose first segment is missing from the list of reserved paths
- **THEN** the automatic test fails and requires adding the segment to the list

#### Scenario: Language prefixes
- **WHEN** the slug of a page `en` or `uk` is checked
- **THEN** the requirement does not take the language codes into account (they will be added by the separate change about the languages of the site)
