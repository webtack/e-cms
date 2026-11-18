# Spec Delta

## ADDED Requirements

### Requirement: Панель «SEO» у формі запису
The form of a record of a type that has the fields `meta_title`, `meta_description` and `meta_image` (kinds `content` and `page`) SHALL show these three fields in a separate panel «SEO», not among the main fields of the type. The panel SHALL work with the base language, as the rest of the form, be saved together with the record by the same action, and use the same input elements and validation as the other fields. The values SHALL be optional. A type without these fields (`block`) has no panel. The other fields of the type are shown, as before, in the order of weight.

#### Scenario: Form of a record of a content type
- **WHEN** the user opens the form of creating a record of a type of the kind `content` or `page`
- **THEN** the fields `meta_title`, `meta_description`, `meta_image` are shown in the panel «SEO», not among the main fields

#### Scenario: Saving SEO values
- **WHEN** the user fills in `meta_title`, `meta_description`, chooses `meta_image` and saves the record
- **THEN** the values are saved for the record, and after opening the edit form again they are shown in the panel

#### Scenario: Record without SEO values
- **WHEN** the user saves a record without filling in the panel «SEO»
- **THEN** the record is created, there are no values of the metafields

#### Scenario: Block type
- **WHEN** the user opens the form of a record of a block
- **THEN** there is no panel «SEO»

#### Scenario: Columns of the table of records
- **WHEN** the settings of the list of a type refer to `meta_title`
- **THEN** the field is available as a column in the same way as any other field of a record

## MODIFIED Requirements

### Requirement: Форма запису
The form of creation (`/admin/content-types/{key}/nodes/create`) and of editing (`/admin/content-types/{key}/nodes/{uuid}/edit`) SHALL show above the form the menu of the section of the type, and one input element for each field of the record of the type in the order of weight (`weight`), with the name of the field as the label; required fields SHALL be marked. Fields that belong only to the type itself (`section_*`) SHALL be absent from the form of a record, and the fields `meta_*` SHALL be shown in the panel «SEO», not among the main fields. The service attributes of a record (slug and the others, see the requirement about the details of a record) SHALL not be among the fields of the type, but in a separate service column. The input element SHALL correspond to the value type: «короткий текст» («short text») — a single-line field, «текст» («text») — a multi-line one, «ціле число» («integer») and «десяткове число» («decimal») — a numeric field, «булеве» («boolean») — a checkbox, «дата» («date») — a date choice, «дата й час» («date and time») — a date and time choice. A multiple field SHALL be edited by a list of elements, to which elements can be added, from which they can be removed, and in which their order can be changed (by dragging and from the keyboard). The form does not show a language choice. The edit form SHALL be filled with the current values of the record in the language `default`, and its slug — with the current slug.

#### Scenario: Form of creation
- **WHEN** the user opens the form of creating a record of a type with the fields `title` (weight 0), `price` (integer, weight 10) and `body` (text, weight 20)
- **THEN** they see empty fields `title`, `price` and `body` in this order, `title` is marked as required, and the empty slug field is in the service column

#### Scenario: Elements by type
- **WHEN** the type has fields of the types «булеве», «дата» and «дата й час»
- **THEN** they are shown as a checkbox, a date choice and a date and time choice respectively

#### Scenario: Multiple field
- **WHEN** the type has a multiple field `tags`, and the user adds two elements and removes the first one
- **THEN** one element remains in the list, and adding a new element adds an empty input row

#### Scenario: Changing the order of elements
- **WHEN** the user rearranges the elements of the multiple field `tags` from `a, b, c` to `c, a, b` and saves
- **THEN** after reopening the edit form the elements go in the order `c, a, b`, and in the list of records the value is shown as «c, a, b»

#### Scenario: Edit form
- **WHEN** the user opens the edit form of a record with the slug `pro-nas`, `title` «Про нас» and `tags` `["a", "b"]`
- **THEN** the slug field in the service column contains `pro-nas`, `title` — «Про нас», and the list `tags` — two elements «a» and «b»

#### Scenario: Section fields are not in the form of a record
- **WHEN** the user opens the form of a record of a type of the kind `content`
- **THEN** the fields `section_title`, `section_description`, `section_cover` are absent from the form

#### Scenario: Record of another type
- **WHEN** the user opens the edit form for the `uuid` of a record that belongs to another type
- **THEN** the server responds with 404
