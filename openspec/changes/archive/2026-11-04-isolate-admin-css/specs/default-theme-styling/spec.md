# Spec Delta

## ADDED Requirements

### Requirement: Ізоляція збірки стилів адмінки від теми
The build of the admin styles SHALL NOT contain utilities that exist only in the templates of the theme, and these templates SHALL NOT affect the look of the admin. A change of the templates or the styles of the theme SHALL NOT change the styles of the admin. The theme SHALL have full freedom in styling: its templates and stylesheet do not depend on the styles of the admin.

#### Scenario: A utility only from the theme does not get into the admin
- **WHEN** a utility that is absent from the admin code, for example `aspect-video`, is used in a template of the theme, and the build of the resources is run again
- **THEN** the compiled stylesheet of the admin does NOT contain a rule for this utility

#### Scenario: A change of the theme does not change the admin
- **WHEN** a template or a style of the theme `default` is changed and the build of the resources is run again
- **THEN** the styles used by the admin look the same as before the change of the theme

#### Scenario: The theme uses its own utilities
- **WHEN** a template of the theme uses a utility that is absent from the admin code
- **THEN** this utility is applied on the public page, and the stylesheet of the theme contains its rule
