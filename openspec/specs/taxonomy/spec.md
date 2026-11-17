# taxonomy Specification

## Purpose

Defines the taxonomy vocabularies and terms for categorizing content: a tree of terms with one parent, term translations, rules of uniqueness and safe deletion. The taxonomy exists separately from content types and nodes.

## Requirements

### Requirement: Term vocabularies
The system SHALL store term vocabularies, each with an immutable unique key (format `^[a-z][a-z0-9_]*$`, up to 64 characters), which is its identifier, a name, an optional description, and settings. The settings SHALL contain an optional maximum depth of the tree (an integer from 1); without it the depth is not limited. A vocabulary SHALL be global: it does not belong to a content type, and the same vocabulary can be used by fields of different types. An attempt to change the key of an existing vocabulary SHALL be rejected with a validation error.

#### Scenario: Creating a vocabulary
- **WHEN** a vocabulary is created with the key `categories` and the name "Categories"
- **THEN** the vocabulary is saved, and its key is immutable from then on

#### Scenario: Invalid key
- **WHEN** the key of a vocabulary does not match the format or is already taken
- **THEN** saving is rejected with a validation error for the field `key`

#### Scenario: Changing the key
- **WHEN** the key of an existing vocabulary is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Invalid depth
- **WHEN** the maximum depth of a vocabulary is specified as `0`, a negative number, or a non-integer
- **THEN** saving is rejected with a validation error

### Requirement: Terms and the tree with one parent
The system SHALL store the terms of a vocabulary, each with an immutable unique `uuid` (UUID v7: set explicitly or generated), `slug`, weight, an optional parent term, and translations; the term's relation to its vocabulary SHALL be determined by the vocabulary key, and to its parent — by the parent's `uuid`. The set of term attributes SHALL be fixed. A parent SHALL belong to the same vocabulary; a term SHALL have at most one parent. The tree SHALL NOT contain cycles: a term cannot become its own descendant. The vocabulary of a term SHALL be immutable after creation. The `slug` of a term SHALL match the slug format of a node (`^[a-z0-9]+(-[a-z0-9]+)*$`, up to 190 characters), not be translated, and be unique within the vocabulary (not the parent) case-insensitively; in different vocabularies the `slug` may repeat. Sibling terms of one parent SHALL be ordered by weight, and for equal weight — by the internal numeric identifier.

#### Scenario: Root and sub-term
- **WHEN** the term "Clothing" is created without a parent, and the term "Jackets" with the parent "Clothing"
- **THEN** "Clothing" is a root, and "Jackets" is its child

#### Scenario: Parent from another vocabulary
- **WHEN** a term of another vocabulary is selected as the parent of a term
- **THEN** saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Cycle
- **WHEN** the descendant "Winter" is selected as the parent of the term "Clothing"
- **THEN** the change is rejected with a validation error

#### Scenario: Term as its own parent
- **WHEN** the term itself is selected as its parent
- **THEN** the change is rejected with a validation error

#### Scenario: Uniqueness of slug within a vocabulary
- **WHEN** a term with the slug `jackets` already exists in the vocabulary, and another one with `Jackets` is created
- **THEN** saving is rejected with a validation error for the field `slug`

#### Scenario: The same slug in different vocabularies
- **WHEN** a term with the slug `news` exists in the vocabulary `categories`, and a term with the slug `news` is created in the vocabulary `tags`
- **THEN** both terms are saved

#### Scenario: Changing the vocabulary of a term
- **WHEN** the vocabulary of an existing term is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Changing the uuid of a term
- **WHEN** the `uuid` of an existing term is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Order of sibling terms
- **WHEN** the children of one parent have weights 2, 0, and 1
- **THEN** they are returned in the order of weights 0, 1, 2, and for equal weight — by the internal identifier

### Requirement: Limit of the tree depth
The system SHALL NOT allow the depth of a term (a root has depth 1) to exceed the maximum depth of the vocabulary. Moving a term under a new parent SHALL take into account the depth of its whole subtree. Reducing the maximum depth of a vocabulary below the actual depth of the existing tree SHALL be rejected.

#### Scenario: Creation at the depth limit
- **WHEN** the maximum depth of a vocabulary is 2, and a term is created under a term of the second level
- **THEN** saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Moving a subtree
- **WHEN** the maximum depth is 3, and a term with two levels of descendants is moved under a term of the second level
- **THEN** the move is rejected, because the depth of the subtree would exceed the limit

#### Scenario: Flat vocabulary
- **WHEN** the maximum depth of a vocabulary is 1, and a term with a parent is created
- **THEN** saving is rejected with a validation error

#### Scenario: Reducing the limit below the current depth
- **WHEN** a vocabulary has a tree of depth 3, and the maximum depth is changed to 2
- **THEN** the change is rejected with a validation error

### Requirement: Image of a term
The system SHALL allow a term one optional image — a media library file with the `image` profile, stored as `{uuid}.{extension}`. The image is not translated. The relation of a term with the file SHALL be counted in the media library as a usage of the file by the owner `taxonomy_term`; a file used by a term SHALL be unavailable for deletion from the media library. Replacing and clearing the image, as well as deleting the term, SHALL remove the relation (the file itself remains in the media library).

#### Scenario: Binding an image
- **WHEN** an image from the media library is set for a term
- **THEN** the value is saved, and the media library shows the term among the usages of the file

#### Scenario: Replacing and clearing
- **WHEN** the image of a term is replaced with another or cleared
- **THEN** the relation with the previous file is removed, and the file remains in the media library

#### Scenario: Not an image
- **WHEN** a file of another profile or a non-existent file is specified as the image
- **THEN** saving is rejected with a validation error for the field `image`

#### Scenario: Deleting a term with an image
- **WHEN** a term that has an image is deleted
- **THEN** the relation with the file is removed, and the file remains in the media library

### Requirement: Translations of terms
The system SHALL store the name and an optional description of a term separately for each language: the base language `default` and the content languages, admissible by the same rules as the translations of content values (the code is normalized; an additional language requires multilingual mode to be enabled and the language to be active). The name of a term in the language `default` SHALL be required. In any other language the name and the description SHALL each be optional: a translation MAY hold only one of them, and the other reads as missing; a translation that holds neither SHALL NOT exist. The system SHALL be able to save the translation of one language on its own: it SHALL change only that language, SHALL NOT store a value that equals the `default` value of the same field, and SHALL delete the translation when no field of it remains. For one language a term SHALL have at most one translation. A fallback to `default` when reading a missing translation SHALL NOT be applied: a missing translation is returned as missing.

#### Scenario: Name in the base language
- **WHEN** a term is created without a name in the language `default`
- **THEN** saving is rejected with a validation error

#### Scenario: Translation of a name
- **WHEN** the name "Jackets" is written for a term in the language `en`
- **THEN** there are two independent names (`default` and `en`), and changing one does not change the other

#### Scenario: Invalid language
- **WHEN** a translation is written in a language that is not in the list, or multilingual mode is disabled
- **THEN** the record is rejected with a validation error

#### Scenario: Missing translation
- **WHEN** the name of a term is read in the language `en`, and there is no English translation
- **THEN** the name is absent, and `default` is not substituted

#### Scenario: Translation with only a description
- **WHEN** a translation in `en` is saved with a description and without a name
- **THEN** the translation exists, its name reads as missing and its description is stored

#### Scenario: Value equal to the base
- **WHEN** the translation of `en` is saved with a name equal to the `default` name and a different description
- **THEN** only the description is stored for `en`

#### Scenario: Saving one language
- **WHEN** the translation of `en` is saved for a term that also has a translation in `pl`
- **THEN** the translation in `pl` and the `default` values are unchanged

#### Scenario: Nothing left to store
- **WHEN** the translation of `en` is saved with every value empty or equal to `default`
- **THEN** the `en` translation does not exist afterwards

### Requirement: Descendants of a term
The system SHALL be able to return, for a set of terms, their `uuid`s together with the `uuid`s of all descendants at any depth. The result SHALL contain no duplicates, even if the input terms are nested in one another.

#### Scenario: Descendants at several levels
- **WHEN** the descendants of the term "Clothing" are requested, which has the children "Jackets" and "Footwear", and "Jackets" has the child "Winter"
- **THEN** the result contains "Clothing", "Jackets", "Footwear", and "Winter"

#### Scenario: Nested input terms
- **WHEN** the descendants of "Clothing" and "Jackets" are requested at the same time
- **THEN** each term is present in the result once

#### Scenario: Term without children
- **WHEN** the descendants of a leaf are requested
- **THEN** the result contains only the leaf itself

### Requirement: Deleting a term
The system SHALL reject the deletion of a term that has children, and the deletion of a term that is used by the values of node fields. The deletion of a term and the write of a value that references it SHALL be serialized: in case of concurrency only one of them succeeds. The system SHALL provide a list of the usages of a term (the value's owner, the field, the count) so that the reason for refusal can be shown. The deletion of a free leaf SHALL remove the term together with its translations. Field values SHALL NOT be deleted by cascade together with the term.

#### Scenario: Term with children
- **WHEN** a term that has child terms is deleted
- **THEN** the deletion is rejected with a validation error, and the term and its children remain

#### Scenario: Term in use
- **WHEN** a term that is referenced by a value of a node field is deleted
- **THEN** the deletion is rejected with a validation error, and the value remains

#### Scenario: Free leaf
- **WHEN** a term without children and without usages is deleted
- **THEN** the term and its translations are deleted

#### Scenario: List of usages
- **WHEN** the usages of a term that is specified in the field `tags` of two nodes are requested
- **THEN** the result reports two usages of this field

### Requirement: Deleting a vocabulary
The system SHALL reject the deletion of a vocabulary that has terms, and of a vocabulary that is referenced by the `vocabulary` setting of at least one field of type "term". An empty vocabulary that no one references SHALL be deleted.

#### Scenario: Vocabulary with terms
- **WHEN** a vocabulary that contains terms is deleted
- **THEN** the deletion is rejected with a validation error

#### Scenario: Vocabulary referenced by a field
- **WHEN** an empty vocabulary that is specified in `settings.vocabulary` of a field of type "term" is deleted
- **THEN** the deletion is rejected with a validation error

#### Scenario: Free empty vocabulary
- **WHEN** a vocabulary without terms, which no field references, is deleted
- **THEN** the vocabulary is deleted

### Requirement: Reading a term with fallback
The system SHALL be able to read the name and the description of a term in a language with an explicit fallback mode used by the public site: each of the two fields missing in the requested language SHALL be replaced by its `default` value, independently of the other field. Reading without the mode SHALL stay strict (a missing translation reads as missing).

#### Scenario: Name falls back
- **WHEN** the name of a term is read in English with the fallback mode and there is no English name
- **THEN** the `default` name is returned

#### Scenario: Partial translation
- **WHEN** a term has an English description only and is read in English with the fallback mode
- **THEN** the name is the `default` name and the description is the English one

#### Scenario: Strict reading
- **WHEN** the name is read in English without the mode and there is no English name
- **THEN** it reads as missing
