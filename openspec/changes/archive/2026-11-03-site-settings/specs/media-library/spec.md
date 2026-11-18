# Spec Delta

## ADDED Requirements

### Requirement: Файли налаштувань сайту
The system SHALL keep account of the files of the site logos by a link whose owner is the site settings, with the role equal to the key of the logo (`logo_large`, `logo_small`), and the language of the value. The links SHALL be updated in the same transaction as the record of the settings. The page «Де використовується файл» ("Where the file is used") SHALL show such use with a caption like «Налаштування сайту» ("Site settings") and a link to the page of the site settings.

#### Scenario: File-logo in the list of uses
- **WHEN** the user opens «Де використовується» ("Where it is used") for a file chosen as the large logo
- **THEN** the list has a row with the owner «Налаштування сайту», the role of the large logo, the language `default` and the link to `/admin/settings`

#### Scenario: Deleting a used logo
- **WHEN** the user tries to delete from the media library a file that is the logo of the site
- **THEN** the deletion is rejected, the file and the link remain

#### Scenario: Freed file
- **WHEN** the logo is replaced by another file
- **THEN** the previous file has no links and can be deleted from the media library
