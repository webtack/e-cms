## ADDED Requirements

### Requirement: Block "Archive" on the "Display" tab
The "Display" tab of a type of kind `content` SHALL contain the group "Archive" with the choice of the archive field and the sort of the archive list (field and direction). The group SHALL be a bordered `fieldset` with a `legend`, like the other groups of the tab. The archive field choice SHALL offer an empty option "Do not use", the service dates "Unpublishing date" (`unpublished_at`) and "Creation date" (`created_at`), and the added fields of type `date` and `datetime`; other fields SHALL NOT be offered. The sort controls SHALL be shown only while an archive field is chosen and SHALL offer the same fields as the main sort. The group SHALL NOT exist for kinds `page` and `block`. The state SHALL be saved with `settings.public` by the single "Save" button of the tab, without changing the other keys of `settings`, and a cleared choice SHALL remove the `archive` key. The default state is "Do not use".

Saving SHALL be rejected with a validation error, and nothing changed, when `settings.public.archive` has an unknown key, when `field` is not an offered field, when `sort` has an unknown field or direction, when `sort` is given without `field`, or when the type is not of kind `content`.

#### Scenario: Default state
- **WHEN** the "Display" tab of a type `content` without an archive is opened
- **THEN** the group "Archive" shows "Do not use" and no sort controls

#### Scenario: Group is a fieldset
- **WHEN** the "Display" tab of a type `content` is opened
- **THEN** the group "Archive" is a `fieldset` whose accessible name is its `legend` "Archive"

#### Scenario: Offered fields
- **WHEN** a type has the fields `date` (datetime), `starts` (date), `price` (integer), and `title`
- **THEN** the archive field choice offers "Do not use", "Unpublishing date", "Creation date", `date`, and `starts`, and does not offer `price`, `title`, or the publication and update dates

#### Scenario: Choosing a field
- **WHEN** the administrator chooses `date`, sets the archive sort to `date` in descending order, and saves
- **THEN** after reopening the tab the same choice is shown and `settings.public.archive` holds `field = date` and `sort = {date, desc}`

#### Scenario: Clearing the field
- **WHEN** the administrator chooses "Do not use" and saves
- **THEN** `settings.public` has no `archive` key and the sort controls of the group are hidden

#### Scenario: Other keys are not erased
- **WHEN** the tab is saved with a changed archive
- **THEN** `settings.list`, `settings.public.meta`, and the other keys of `settings.public` remain unchanged

#### Scenario: Inadmissible field
- **WHEN** a request contains `settings.public.archive.field` with the key of a `string` field, of a missing field, or of `published_at`
- **THEN** saving is rejected and nothing is changed

#### Scenario: Not a list kind
- **WHEN** the "Display" tab of a type of kind `page` or `block` is opened, or a request with `settings.public.archive` is sent for such a type
- **THEN** the tab has no group "Archive" and the request is rejected
