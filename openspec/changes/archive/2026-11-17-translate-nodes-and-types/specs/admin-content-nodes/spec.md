# Spec Delta

## ADDED Requirements

### Requirement: Translation mode of the node edit page
The edit page of a node of any kind SHALL follow the translation mode contract. In the translation mode of a language the form SHALL keep the layout of the base form (the main column, the side sections, the action bar) with only the translatable fields editable: the title, the `meta_*` fields and the regular fields marked translatable. A field SHALL be shown prefilled with its stored translation, or with its base value when no translation exists; a multiple field SHALL be prefilled and saved as a whole list. The slug, status, dates, author, activity and every non-translatable field SHALL stay in place, shown disabled with their base values, and SHALL NOT be changed; nothing SHALL be marked required, and "Save" SHALL stay disabled until a translatable value changes. The save SHALL write only the fields of the current language, SHALL NOT enforce required fields, SHALL NOT store a value equal to the base value and SHALL remove the translation of a field that is emptied. Saving a translation SHALL count as a change of the node (its modification time is updated). The right to open and save a translation SHALL be the right to edit that node in the base language, including the access level of its type.

#### Scenario: Translation mode keeps the layout
- **WHEN** a node has a translatable `title`, a translatable `body` and a non-translatable `price`, and the user opens it with `?lang=en`
- **THEN** the form has the layout of the base form, the title and the body are editable and prefilled, and the price, the slug, the status and the dates stay in place, disabled

#### Scenario: Saving a translated title
- **WHEN** the user changes the prefilled title to "Hello" and saves
- **THEN** the English title "Hello" is stored, the base title is unchanged, and the user stays on the English form

#### Scenario: Required field left empty
- **WHEN** a required translatable field is emptied in the translation mode and the user saves
- **THEN** the save succeeds and the translation of that field is removed

#### Scenario: Value equal to the base
- **WHEN** the user saves a form in which a prefilled field was not changed
- **THEN** no translation is stored for that field

#### Scenario: Multiple field
- **WHEN** a translatable multiple field has the base list `a, b` and the user saves the English list `a, c`
- **THEN** the whole English list is stored as `a, c` and the base list is unchanged

#### Scenario: Node of another owner
- **WHEN** a user whose access level allows editing only their own nodes opens the translation mode of another user's node
- **THEN** the response is the same as for the base edit page

#### Scenario: Clearing
- **WHEN** the user confirms "Clear translation" in the English mode
- **THEN** every English value of the node is removed and the form shows the base values

### Requirement: Translations column and missing-translation filter
When multilingual mode is on and at least one additional language is active, the node list SHALL offer a "Translations" column that shows the languages in which the node has a translation, and a filter "Without translation in" with the active languages. The column SHALL be hideable like other columns and SHALL NOT be sortable. The filter SHALL keep only the nodes that have no translation in the chosen language, combined with the other parameters of the list. When multilingual mode is off or no language is active, neither SHALL appear and the list SHALL behave as before. A node is translated into a language when it has at least one stored translated value in it.

#### Scenario: Column
- **WHEN** a node has translations in `en` only and the languages `en` and `pl` are active
- **THEN** the Translations column shows `en` for that node and nothing for a node without translations

#### Scenario: Filter
- **WHEN** the user chooses "Without translation in `pl`"
- **THEN** only the nodes that have no Polish value remain in the list

#### Scenario: Single-language site
- **WHEN** multilingual mode is off
- **THEN** the list has no Translations column and no filter

#### Scenario: Number of queries
- **WHEN** the list shows a page of nodes
- **THEN** the translated languages of the whole page are read with a constant number of queries, not one per node
