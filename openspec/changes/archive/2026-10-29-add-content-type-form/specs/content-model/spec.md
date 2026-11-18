## ADDED Requirements

### Requirement: Public slug of a content type
A content type of the `content` kind SHALL have an optional `slug` — the public path of the site that opens the list of nodes of this type. The slug SHALL consist of lowercase Latin letters, digits and hyphens without leading, trailing and repeated hyphens, up to 190 characters long. The slug SHALL be unique among all types (case-insensitive); a type without a slug has no public list. For types of the `page` and `block` kinds the slug SHALL be empty, and any attempt to specify it SHALL be rejected. The slug of a type SHALL be changeable after creation (unlike `key` and `kind`), keeping all checks, including clearing. The slug of a type does not depend on node slugs and is not checked for a match with them.

#### Scenario: Type with a slug
- **WHEN** a type of the `content` kind is created with the slug `news`
- **THEN** the type is saved, and the slug `news` belongs to this very type

#### Scenario: Type without a slug
- **WHEN** a type of the `content` kind is created without a slug
- **THEN** the type is saved, and its slug is empty (there is no public list)

#### Scenario: Duplicate slug
- **WHEN** a type with the slug `News` is created, and another type already has the slug `news`
- **THEN** the save is rejected with a validation error of the `slug` field

#### Scenario: Invalid format
- **WHEN** a type is saved with the slug `About us`, `news_1`, `-news` or `a--b`
- **THEN** the save is rejected with a validation error of the `slug` field

#### Scenario: Slug for a non-content type
- **WHEN** a type of the `page` or `block` kind is created or changed with a slug
- **THEN** the save is rejected with a validation error of the `slug` field

#### Scenario: Changing the slug
- **WHEN** the slug of an existing type of the `content` kind is changed to a free valid value
- **THEN** the change is saved, and the `key` and `kind` of the type remain unchanged

#### Scenario: Clearing the slug
- **WHEN** the slug of an existing type of the `content` kind is cleared
- **THEN** the slug becomes empty, and the type is saved
