# Spec Delta

## ADDED Requirements

### Requirement: File names and descriptions in the translation mode of a type
In the translation mode of the Display and SEO tabs, the files of the type's image values (the section cover and the meta image) SHALL let the user edit the display name and the description of each file in the current language, under the same rules as in a node translation: prefilled from the translated or the base value, stored only when different from the base, removed when emptied, the original name and the base description unchanged.

#### Scenario: Description of the meta image
- **WHEN** the user opens the SEO tab of a type with `?lang=en`, changes the description of the meta image and saves
- **THEN** the English description of that file is stored and the base description and the original name are unchanged

#### Scenario: Description of the section cover
- **WHEN** the user opens the Display tab with `?lang=en` for a type that has a section cover
- **THEN** the cover is shown with an editable English name and description prefilled from the base ones
