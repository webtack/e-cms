# Spec Delta

## ADDED Requirements

### Requirement: Панелі «Розділ» і «SEO» на сторінці налаштувань типу контенту
The settings page of a type with `kind = content` (`/admin/content-types/{key}/edit`) SHALL show, besides the main form of the type, two panels of values of the type itself: «Розділ» («Section», the fields `section_title`, `section_description`, `section_cover`) and «SEO» (the fields `meta_title`, `meta_description`, `meta_image`). The labels, the hints (the description of the field) and the length limits SHALL be taken from the definitions of these fields. The input element SHALL correspond to the type of the field (short text, multi-line text, formatted text, a choice of an image from the media library). All values are optional; an empty value SHALL clear the saved one. The panels SHALL work only with the base language: there is no language switch, the values are saved as base ones. For the types `page` and `block` there are no panels. Saving the values of the panels SHALL be rejected with an error next to the corresponding field, if the value does not pass the validation of the field.

#### Scenario: Opening the settings page
- **WHEN** the user opens the settings of the type `news` of the kind `content`
- **THEN** they see the panels «Розділ» and «SEO» with the current values of the base language

#### Scenario: Saving section values
- **WHEN** the user fills in `section_title` «Новини», chooses `section_cover` from the media library and saves
- **THEN** the values are saved for the type, after reloading the page they are shown

#### Scenario: Clearing a value
- **WHEN** the user clears `meta_description` and saves
- **THEN** the saved value is deleted

#### Scenario: Invalid value
- **WHEN** the value of `meta_title` exceeds the permitted length
- **THEN** the saving is rejected, an error is shown next to the field, the values remain the previous ones

#### Scenario: Type of pages or blocks
- **WHEN** the user opens the settings of a type of the kind `page` or `block`
- **THEN** there are no panels «Розділ» and «SEO»
