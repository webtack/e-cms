# Spec Delta

## ADDED Requirements

### Requirement: Translation mode of the Display and SEO tabs
The "Display" tab (the section values `section_title`, `section_description`, `section_cover`) and the "SEO" tab (the type's `meta_*` values) of a type SHALL follow the translation mode contract for the values the tab edits. In the translation mode the form SHALL keep the layout of the base form with only those values editable, prefilled from the base when empty; the shared settings of the tab (public display settings, archive, meta data options) SHALL stay in place, shown disabled, and SHALL NOT be changed. Saving SHALL change only the translations of the values of the current tab, SHALL NOT store a value equal to the base and SHALL remove a value that is emptied. The switcher SHALL mark a language when the type has any stored translated value in it on either tab. Types of kind `block` have no such values and SHALL show no switcher. The right to translate SHALL be the right to configure the type.

#### Scenario: Section values in a translation
- **WHEN** the user opens the Display tab of a content type with `?lang=en`
- **THEN** the section title, description and cover are editable and prefilled from the base, and the public display settings stay in place, disabled

#### Scenario: Saving a translated section title
- **WHEN** the user changes the section title to "News" and saves in English
- **THEN** the English section title is stored and the base title and the display settings are unchanged

#### Scenario: SEO tab
- **WHEN** the user opens the SEO tab of a page type with `?lang=en`
- **THEN** the meta title, meta description and meta image are editable, and the meta data block stays in place, disabled

#### Scenario: Block types
- **WHEN** the user opens the settings of a block type
- **THEN** no language switcher is shown

#### Scenario: Marker
- **WHEN** the type has an English `meta_title` and no other translation
- **THEN** `en` is marked on both tabs
