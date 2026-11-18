## MODIFIED Requirements

### Requirement: Панелі «Розділ» і «SEO» на сторінці налаштувань типу контенту
For a type `kind = content` the menu of the section of the type SHALL contain, besides «Налаштування» ("Settings"), «Поля» ("Fields") and «Записи» ("Records"), two separate tabs of values of the type itself: «Розділ» ("Section", `/admin/content-types/{key}/section`, the fields `section_title`, `section_description`, `section_cover`) and «SEO» (`/admin/content-types/{key}/seo`, the fields `meta_title`, `meta_description`, `meta_image`). Each tab SHALL be a separate page with its own form and save button, which saves only the fields of this tab. The settings page of a type (`/admin/content-types/{key}/edit`) SHALL contain only the main form of the type, without panels of values. The labels, the hints (the description of the field) and the length limits SHALL be taken from the definitions of the fields. The input element SHALL correspond to the type of the field (short text, multi-line text, formatted text, a choice of an image from the media library). All values are optional; an empty value SHALL clear the saved one. The tabs SHALL work only with the base language: there is no language switch, the values are saved as base ones. Each tab SHALL have the same sticky action bar at the bottom of the window with «Скасувати» ("Cancel") and «Зберегти» ("Save") as the other forms of the admin; «Зберегти» submits the form of the tab. Saving a tab SHALL not change the values of the other tab. For the types `page` and `block` there are no tabs «Розділ» and «SEO», and their addresses SHALL respond with 404. A saving SHALL be rejected with an error next to the corresponding field, if the value does not pass the validation of the field.

#### Scenario: Menu of a content type
- **WHEN** the user opens any page of the type `news` of the kind `content`
- **THEN** the menu of the type contains the tabs «Налаштування», «Розділ», «SEO», «Поля», «Записи», and the current one is active

#### Scenario: Opening the settings page
- **WHEN** the user opens the settings of the type `news` of the kind `content`
- **THEN** the page contains only the main form of the type; there are no panels «Розділ» and «SEO» on it

#### Scenario: Opening the tab «Розділ»
- **WHEN** the user opens the tab «Розділ» of the type `news`
- **THEN** they see the fields of the section with the current values of the base language, and they do not see the SEO fields

#### Scenario: Saving values of the section
- **WHEN** the user fills in `section_title` «Новини», chooses `section_cover` from the media library and saves on the tab «Розділ»
- **THEN** the values are saved for the type, after reloading the page they are shown

#### Scenario: Sticky action bar on the tabs
- **WHEN** the user opens the tab «Розділ» or «SEO» in a window 400 px high (at 1280 and 390 px wide)
- **THEN** the panel with «Скасувати» and «Зберегти» is fully visible at the bottom of the window

#### Scenario: Saving SEO does not touch the section
- **WHEN** the user changes `meta_title` on the tab «SEO» and saves
- **THEN** the values `section_*` of the type remain unchanged

#### Scenario: Clearing a value
- **WHEN** the user clears `meta_description` on the tab «SEO» and saves
- **THEN** the saved value is deleted

#### Scenario: Invalid value
- **WHEN** the value of `meta_title` exceeds the permitted length
- **THEN** the saving is rejected, an error is shown next to the field, the values remain the previous ones

#### Scenario: Type of pages or blocks
- **WHEN** the user opens the settings of a type of the kind `page` or `block`
- **THEN** there are no tabs «Розділ» and «SEO» in the menu, and the addresses `/section` and `/seo` of these kinds respond with 404
