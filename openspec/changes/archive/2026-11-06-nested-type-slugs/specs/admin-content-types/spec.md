# Spec Delta

## ADDED Requirements

### Requirement: Slug of a nested type in the type forms
The slug field of the create and edit forms of a type of kind `content` SHALL accept several segments separated by `/` and explain the format in its hint (lowercase Latin letters, digits and hyphens in each segment, segments separated by `/`). When a parent type that has a slug is selected, the form SHALL show the prefix the slug has to start with (the parent's slug and `/`), and SHALL say so in the hint when the parent has no slug. The create and edit requests SHALL reject a slug that breaks the hierarchy of type slugs (a prefix other than the parent's slug, or a parent without a slug) and a change or clearing of the slug of a type whose child types have slugs, with an error next to the slug field; the other entered values SHALL remain in the form. Changing the parent type SHALL re-check the slug the same way. The messages SHALL be displayed in the interface language. The page-type and block-type forms have no slug and SHALL NOT change.

#### Scenario: Slug of several segments
- **WHEN** the user enters the slug `common/institutions` and no parent type, and submits the create form
- **THEN** the type is created with this slug

#### Scenario: Prefix hint for a parent with a slug
- **WHEN** the user selects a parent type with the slug `common` in the form
- **THEN** the form shows that the slug has to start with `common/`

#### Scenario: Hint for a parent without a slug
- **WHEN** the user selects a parent type without a slug
- **THEN** the form says that the parent has to have a slug before a child can have one

#### Scenario: Slug without the prefix of the parent
- **WHEN** the user selects the parent with the slug `common`, enters the slug `institutions`, and submits the form
- **THEN** the type is not created, an error is shown next to the slug field, and the entered values remain

#### Scenario: Changing the slug of a parent that has children with slugs
- **WHEN** the user changes the slug of a type whose child type has a slug, and saves
- **THEN** the change is not saved, and an error next to the slug field says that the slugs of the child types must be changed first

#### Scenario: Changing the parent
- **WHEN** the user changes the parent of a type with the slug `common/institutions` to a type with another slug and saves
- **THEN** the change is not saved, and an error is shown next to the slug field

#### Scenario: Reserved first segment
- **WHEN** the user enters the slug `admin/reports`
- **THEN** the type is not created, and an error is shown next to the slug field that the slug is reserved
