## MODIFIED Requirements

### Requirement: Панель «SEO» у формі запису
The form of a record of a type that has the fields `meta_title`, `meta_description` and `meta_image` (kinds `content` and `page`) SHALL show these three fields not among the main fields, but in the right service column, in the section «SEO» of the accordion under the section «Деталі» ("Details"). The accordion SHALL have two sections: «Деталі» (open by default) and «SEO» (closed by default); the whole accordion SHALL remain in the shared frame of the side column, and each section SHALL be a separate element in its own nested frame. At the same time no more than one section SHALL be open: opening one automatically closes the other; an open section can be closed, then no section is open. The section «SEO» SHALL work with the base language, as the rest of the form, be saved together with the record by the same action, and use the same input elements and validation as the other fields. The values SHALL be optional. If after an unsuccessful saving the field «SEO» has an error, the section SHALL be expanded, the other section closed, and the error visible. A type without these fields (`block`) has no section «SEO», and the accordion shows only «Деталі». The other fields of the type are shown, as before, in the main column in the order of weight. On a screen narrower than `lg` (mobile) the service column SHALL open by a button in the bottom sheet with the same accordion; the error indicator on the button SHALL be shown also for errors in the fields «SEO».

#### Scenario: Form of a record of a content type
- **WHEN** the user opens the form of a record of a type that has the fields `meta_*`, on a wide screen
- **THEN** in the right column there is an accordion with the sections «Деталі» (open) and «SEO» (closed), each in its own frame inside the shared frame of the column; the main column contains no fields `meta_*`

#### Scenario: Expanding the section «SEO»
- **WHEN** the user expands the section «SEO»
- **THEN** they see the fields `meta_title`, `meta_description`, `meta_image` with the current values, and the section «Деталі» is closed

#### Scenario: One open section
- **WHEN** the section «Деталі» is open, and the user opens «SEO»
- **THEN** «SEO» is open, «Деталі» is closed

#### Scenario: Separate frames
- **WHEN** the form shows both sections
- **THEN** each section has its own frame, and both together remain in the shared frame of the side column

#### Scenario: Saving SEO values
- **WHEN** the user fills in the fields in the section «SEO» and saves the record
- **THEN** the values are saved together with the record and shown in the section «SEO» after opening the edit form

#### Scenario: Error in a collapsed section
- **WHEN** the section «SEO» is collapsed, and the saving returns an error for `meta_title`
- **THEN** the section «SEO» is open, «Деталі» is closed, and the error is visible next to the field

#### Scenario: Sticky action bar
- **WHEN** the user opens the form of a record with an open section of the accordion in a window 400 px high (at 1280 and 390 px wide) and scrolls the form
- **THEN** the panel with «Скасувати» and «Зберегти» (and the button of details on a narrow screen) remains fully visible at the bottom of the window, does not overlap and is not overlapped by the accordion

#### Scenario: A lot of content in the right column
- **WHEN** on a wide screen an open section is taller than the window
- **THEN** the right column scrolls by itself, does not go beyond the window and does not overlap the action bar

#### Scenario: Mobile
- **WHEN** the user presses the button «Деталі» on a screen narrower than `lg`
- **THEN** the bottom sheet with the accordion opens: «Деталі» is open, «SEO» is closed

#### Scenario: Record without SEO values
- **WHEN** the user saves a record without filling in the section «SEO»
- **THEN** the record is saved without values of `meta_*`

#### Scenario: Block type
- **WHEN** the user opens the form of a record of a type of the kind `block`
- **THEN** there is no section «SEO», the accordion contains only «Деталі»

#### Scenario: Columns of the table of records
- **WHEN** the settings of the list of the type refer to `meta_title`
- **THEN** the field is available as a column in the same way as any other field of the record

### Requirement: Форма запису
The form of creation (`/admin/content-types/{key}/nodes/create`) and of editing (`/admin/content-types/{key}/nodes/{uuid}/edit`) SHALL show above the form the menu of the section of the type, and one input element for each field of the record of the type in the order of weight (`weight`), with the name of the field as the label; required fields SHALL be marked. The fields that belong only to the type itself (`section_*`) SHALL be absent from the form of a record, and the fields `meta_*` SHALL be shown in the section «SEO» of the right column (see the requirement about the section «SEO»), not among the main fields. The service attributes of a record (slug and the others, see the requirement about the details of a record) SHALL not be among the fields of the type, but in a separate service column. The input element SHALL correspond to the value type: «короткий текст» («short text») — a single-line field, «текст» («text») — a multi-line one, «ціле число» («integer») and «десяткове число» («decimal») — a numeric field, «булеве» («boolean») — a checkbox, «дата» («date») — a date choice, «дата й час» («date and time») — a date and time choice. A multiple field SHALL be edited by a list of elements, to which elements can be added, from which they can be removed, and in which their order can be changed (by dragging and from the keyboard). The form does not show a language choice. The edit form SHALL be filled with the current values of the record in the language `default`, and its slug — with the current slug.

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
