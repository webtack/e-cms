# Spec Delta

## MODIFIED Requirements

### Requirement: Creating a term
The form `/admin/taxonomy/{key}/terms/create` SHALL have the fields: the name in the base language (required, up to 255 characters), the description, an image (optional; a file of the `image` profile), `slug` (optional; an empty one is generated from the name), the parent term (a choice among the terms of the vocabulary or "No parent"), and the weight (an integer from 0). The list of parents SHALL show the tree with indents and SHALL not offer the terms under which the new child would exceed the maximum depth. The form SHALL NOT contain translations of any language: translations are entered in the translation mode of the edit form of an existing term. The default weight SHALL equal the largest weight among the sibling terms plus one. After success, the user SHALL land on the terms page with a success message.

#### Scenario: Creating a root
- **WHEN** the user enters the name "Clothing", leaves the parent empty, and saves
- **THEN** the term is created as a root with a slug generated from the name, and a success message is shown

#### Scenario: Sub-term
- **WHEN** the user selects the parent "Clothing" and saves the term "Jackets"
- **THEN** "Jackets" is displayed under "Clothing" with an indent

#### Scenario: Taken slug
- **WHEN** the user enters a slug that already exists in the vocabulary
- **THEN** an error appears next to the slug field, and the entered text is saved

#### Scenario: Depth limit
- **WHEN** the maximum depth of the vocabulary is 2
- **THEN** among the possible parents there are no terms of the second level

#### Scenario: Translations
- **WHEN** multilingual mode is enabled, a language is active, and the user opens the form of a new term
- **THEN** the form has no translation fields and no language switcher, and the term can be translated after it is created, in the translation mode of its edit page

#### Scenario: Without multilingual mode
- **WHEN** multilingual mode is disabled
- **THEN** neither the create form nor the edit form shows a language switcher

### Requirement: Editing a term
The form `/admin/taxonomy/{key}/terms/{uuid}/edit` SHALL have the same fields with the current values. The list of parents SHALL not offer the term itself and its descendants, nor the terms under which the term's subtree would exceed the maximum depth. Saving the base form SHALL update the term and its image in one operation and SHALL NOT change, create or delete any translation; clearing the image SHALL remove the relation with the file. Translations of the term SHALL be edited in the translation mode of this page, where the translatable fields are the name and the description. The vocabulary of a term cannot be changed.

#### Scenario: Moving under another parent
- **WHEN** the user selects a new parent "Footwear" for "Jackets" and saves
- **THEN** "Jackets" is shown under "Footwear"

#### Scenario: Parent among descendants
- **WHEN** the user opens the form of the term "Clothing"
- **THEN** among the possible parents there are no "Clothing" and its descendants

#### Scenario: Image of a term
- **WHEN** the user selects an image for the term from the media library and saves
- **THEN** on reopening the form the image is shown, and the media library counts it as used

#### Scenario: Replacing and clearing an image
- **WHEN** the user replaces the image of the term with another and then clears it
- **THEN** the relation with the previous file is removed, and after clearing the term has no image

#### Scenario: File is not an image
- **WHEN** a file of another profile is specified as the image of the term
- **THEN** an error appears next to the field, and the term is not changed

#### Scenario: Base form keeps translations
- **WHEN** the user changes the base name of a term that has an English translation and saves the base form
- **THEN** the English translation is unchanged

#### Scenario: Translation mode fields of a term
- **WHEN** the user opens the edit page of a term with `?lang=en`
- **THEN** only the name and the description are shown, and the slug, parent, weight and image are not

#### Scenario: Clearing a translation
- **WHEN** in the translation mode of `en` the user clears the translation of a term
- **THEN** the English translation is deleted
