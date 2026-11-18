# Spec Delta

## Purpose

Defines the vocabularies and terms of the taxonomy for the categorization of content: the tree of terms with one parent, the translations of terms, the rules of uniqueness and safe deletion. The taxonomy exists separately from the types and records of the content.

## ADDED Requirements

### Requirement: Словники термінів
The system SHALL store vocabularies of terms, each with an immutable unique key (format `^[a-z][a-z0-9_]*$`, up to 64 characters), which is its identifier, a name, an optional description and settings. The settings SHALL contain an optional maximum depth of the tree (an integer from 1); without it the depth is not limited. A vocabulary SHALL be global: it does not belong to a content type, and the same vocabulary can be used by the fields of different types. An attempt to change the key of an existing vocabulary SHALL be rejected with a validation error.

#### Scenario: Creating a vocabulary
- **WHEN** a vocabulary with the key `categories` and the name «Категорії» is created
- **THEN** the vocabulary is saved, and its key remains immutable

#### Scenario: Invalid key
- **WHEN** the key of a vocabulary does not match the format or is already taken
- **THEN** the saving is rejected with a validation error for the field `key`

#### Scenario: Changing the key
- **WHEN** the key of an existing vocabulary is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Invalid depth
- **WHEN** the maximum depth of a vocabulary is set as `0`, a negative or a non-integer number
- **THEN** the saving is rejected with a validation error

### Requirement: Терміни та дерево з одним батьком
The system SHALL store terms of a vocabulary, each with an immutable unique `uuid` (UUID v7: set explicitly or generated), a `slug`, a weight, an optional parent term and translations; the link of a term to a vocabulary SHALL be defined by the key of the vocabulary, and to the parent — by the `uuid` of the parent. The set of attributes of a term SHALL be fixed. The parent SHALL belong to the same vocabulary; a term SHALL have at most one parent. The tree SHALL NOT contain cycles: a term cannot become its own descendant. The vocabulary of a term SHALL be immutable after creation. The `slug` of a term SHALL match the format of the slug of a record (`^[a-z0-9]+(-[a-z0-9]+)*$`, up to 190 characters), not be translated, and be unique within the vocabulary (not the parent) without regard to the case; in different vocabularies the `slug` may repeat. Sibling terms of one parent SHALL be ordered by weight, and with equal weight — by the internal numeric identifier.

#### Scenario: Root and sub-term
- **WHEN** the term «Одяг» without a parent and the term «Куртки» with the parent «Одяг» are created
- **THEN** «Одяг» is a root, «Куртки» its child

#### Scenario: Parent from another vocabulary
- **WHEN** a term of another vocabulary is chosen as the parent of a term
- **THEN** the saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Cycle
- **WHEN** the descendant «Зимові» of the term «Одяг» is chosen as its parent
- **THEN** the change is rejected with a validation error

#### Scenario: Term as its own parent
- **WHEN** the term itself is chosen as its parent
- **THEN** the change is rejected with a validation error

#### Scenario: Uniqueness of the slug within the vocabulary
- **WHEN** the vocabulary already has a term with the slug `jackets`, and another one with `Jackets` is created
- **THEN** the saving is rejected with a validation error for the field `slug`

#### Scenario: Same slug in different vocabularies
- **WHEN** a term with the slug `news` is in the vocabulary `categories`, and a term with the slug `news` is created in the vocabulary `tags`
- **THEN** both terms are saved

#### Scenario: Changing the vocabulary of a term
- **WHEN** the vocabulary of an existing term is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Changing the uuid of a term
- **WHEN** the `uuid` of an existing term is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Order of sibling terms
- **WHEN** the children of one parent have weights 2, 0 and 1
- **THEN** they are returned in the order of weights 0, 1, 2, and with equal weight — by the internal identifier

### Requirement: Обмеження глибини дерева
The system SHALL not allow the depth of a term (the root has depth 1) to exceed the maximum depth of the vocabulary. Moving a term under a new parent SHALL take into account the depth of its whole subtree. A reduction of the maximum depth of a vocabulary below the actual depth of the existing tree SHALL be rejected.

#### Scenario: Creation on the depth boundary
- **WHEN** the maximum depth of the vocabulary is 2, and a term is created under a term of the second level
- **THEN** the saving is rejected with a validation error for the field `parent_uuid`

#### Scenario: Moving a subtree
- **WHEN** the maximum depth is 3, and a term with two levels of descendants is moved under a term of the second level
- **THEN** the move is rejected, because the depth of the subtree would exceed the limit

#### Scenario: Flat vocabulary
- **WHEN** the maximum depth of the vocabulary is 1, and a term with a parent is created
- **THEN** the saving is rejected with a validation error

#### Scenario: Reduction of the limit below the current depth
- **WHEN** the vocabulary has a tree of depth 3, and the maximum depth is changed to 2
- **THEN** the change is rejected with a validation error

### Requirement: Зображення терміна
The system SHALL allow a term one optional image — a file of the media library with the profile `image`, stored as `{uuid}.{extension}`. The image is not translated. The link of the term with the file SHALL be counted in the media library as a usage of the file by the owner `taxonomy_term`; a file used by a term SHALL be unavailable for deletion from the media library. Replacing and clearing the image, as well as deleting the term, SHALL remove the link (the file itself remains in the media library).

#### Scenario: Image link
- **WHEN** an image from the media library is assigned to a term
- **THEN** the value is saved, and the media library shows the term among the usages of the file

#### Scenario: Replacing and clearing
- **WHEN** the image of a term is replaced with another one or cleared
- **THEN** the link with the previous file is removed, the file remains in the media library

#### Scenario: Not an image
- **WHEN** a file of another profile or a non-existent file is specified as the image
- **THEN** the saving is rejected with a validation error for the field `image`

#### Scenario: Deleting a term with an image
- **WHEN** a term that has an image is deleted
- **THEN** the link with the file is removed, the file remains in the media library

### Requirement: Переклади термінів
The system SHALL store the name and the optional description of a term separately for each language: the base language `default` and the content languages admissible by the same rules as the translations of the values of the content (the code is normalized; an additional language requires enabled multilingual support and an active language). The name of the term in the language `default` SHALL be required. For one language a term SHALL have at most one translation. The fallback to `default` when reading a missing translation SHALL NOT apply: a missing translation is returned as missing.

#### Scenario: Name in the base language
- **WHEN** a term is created without a name in the language `default`
- **THEN** the saving is rejected with a validation error

#### Scenario: Translation of the name
- **WHEN** the name «Jackets» in the language `en` is written for a term
- **THEN** two independent names exist (`default` and `en`), a change of one does not change the other

#### Scenario: Invalid language
- **WHEN** a translation in a language that is not in the list or with multilingual support disabled is written
- **THEN** the record is rejected with a validation error

#### Scenario: Missing translation
- **WHEN** the name of a term in the language `en` is read, and there is no English translation
- **THEN** the name is missing, and `default` is not substituted

### Requirement: Нащадки терміна
The system SHALL be able to return for a set of terms their `uuid` together with the `uuid` of all descendants at any depth. The result SHALL contain no duplicates, even if the input terms are nested in one another.

#### Scenario: Descendants at several levels
- **WHEN** the descendants of the term «Одяг» are requested, which has the children «Куртки» and «Взуття», and «Куртки» has the child «Зимові»
- **THEN** the result contains «Одяг», «Куртки», «Взуття» and «Зимові»

#### Scenario: Nested input terms
- **WHEN** the descendants are requested for «Одяг» and «Куртки» at the same time
- **THEN** each term is present in the result once

#### Scenario: Term without children
- **WHEN** the descendants of a leaf are requested
- **THEN** the result contains only the leaf itself

### Requirement: Видалення терміна
The system SHALL reject the deletion of a term that has children, and the deletion of a term that is used by the values of the fields of records. The deletion of a term and the writing of a value that references it SHALL be serialized: in case of simultaneity only one of them succeeds. The system SHALL provide the list of usages of a term (the owner of the value, the field, the count), so that the reason for refusal can be shown. The deletion of a free leaf SHALL remove the term together with its translations. The values of fields SHALL NOT be deleted in cascade together with the term.

#### Scenario: Term with children
- **WHEN** a term that has child terms is being deleted
- **THEN** the deletion is rejected with a validation error, the term and its children remain

#### Scenario: Used term
- **WHEN** a term that is referenced by the value of a field of a record is being deleted
- **THEN** the deletion is rejected with a validation error, the value remains

#### Scenario: Free leaf
- **WHEN** a term without children and without usages is being deleted
- **THEN** the term and its translations are deleted

#### Scenario: List of usages
- **WHEN** the usages of a term are requested, which is specified in the field `tags` of two records
- **THEN** the result reports two usages of this field

### Requirement: Видалення словника
The system SHALL reject the deletion of a vocabulary that has terms, and of a vocabulary that is referenced by the setting `vocabulary` of at least one field of the type «термін». An empty vocabulary that nobody references SHALL be deleted.

#### Scenario: Vocabulary with terms
- **WHEN** a vocabulary that contains terms is being deleted
- **THEN** the deletion is rejected with a validation error

#### Scenario: Vocabulary referenced by a field
- **WHEN** an empty vocabulary that is specified in `settings.vocabulary` of a field of the type «термін» is being deleted
- **THEN** the deletion is rejected with a validation error

#### Scenario: Free empty vocabulary
- **WHEN** a vocabulary without terms that no field references is being deleted
- **THEN** the vocabulary is deleted
