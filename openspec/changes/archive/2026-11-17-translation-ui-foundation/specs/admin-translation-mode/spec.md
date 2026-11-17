# Spec Delta

## Purpose

Defines one behavior for editing the translations of any admin entity (term, menu item, node, type values, site settings, file description): one language at a time, on the edit page of the entity, with the same switcher, prefilling, saving and clearing rules everywhere.

## ADDED Requirements

### Requirement: Language switcher
An edit page of a translatable entity SHALL show a language switcher above the form when multilingual mode is enabled and at least one additional language is active. The switcher SHALL list the base language first, marked as the base, followed by the active additional languages in order of weight. Each additional language SHALL carry a marker when the entity already has a translation in it. The switcher SHALL NOT appear on pages that create an entity, and SHALL NOT appear when multilingual mode is off or no additional language is active.

#### Scenario: Multilingual site
- **WHEN** multilingual mode is on, the languages `en` and `pl` are active, and the user opens the edit page of an entity that has an English translation
- **THEN** the switcher shows the base language, `en` with a marker and `pl` without one

#### Scenario: Single-language site
- **WHEN** multilingual mode is off or no additional language is active
- **THEN** the edit page shows no switcher and looks as it did before translations existed

#### Scenario: Creation page
- **WHEN** the user opens the page that creates an entity
- **THEN** no switcher is shown

### Requirement: Language in the address
The language of the page SHALL be carried by the address parameter `lang`. A page without the parameter, or with the code of the base language, SHALL be the base mode. A page with the code of an active additional language SHALL be the translation mode of that language. Any other value (an unknown code, an inactive language, or any language while multilingual mode is off) SHALL answer 404. Choosing a language in the switcher SHALL open the same entity with the matching parameter.

#### Scenario: Active language
- **WHEN** the user opens the edit page with `?lang=en` and `en` is active
- **THEN** the page opens in the translation mode of `en`

#### Scenario: Unknown or inactive language
- **WHEN** the user opens the edit page with `?lang=xx` or with an inactive language
- **THEN** the response is 404

#### Scenario: Multilingual mode is off
- **WHEN** multilingual mode is off and the user opens the edit page with `?lang=en`
- **THEN** the response is 404

#### Scenario: Switching
- **WHEN** the user chooses `en` in the switcher
- **THEN** the same entity opens with `?lang=en`

### Requirement: Fields of the translation mode
In the translation mode the form SHALL show only the translatable fields of the entity. Every other field (identifiers, addresses, parents, weights, statuses, dates, files that are not translated, and any other shared attribute) SHALL be hidden, so the translation mode cannot change them. In the base mode the form SHALL show the shared fields and the base values of the translatable fields, and saving it SHALL NOT change any translation.

#### Scenario: Shared attributes are not editable in a translation
- **WHEN** the user opens an entity in the translation mode of `en`
- **THEN** its shared attributes are not shown and cannot be sent through this form

#### Scenario: Base form leaves translations alone
- **WHEN** the user saves the base form of an entity that has translations
- **THEN** its translations are unchanged

### Requirement: Prefilled values in the translation mode
In the translation mode each translatable field SHALL show its stored translation, or, when the translation is empty, the value of the base language as an editable starting value. The form SHALL show no separate hint or copy of the base value under the fields. Prefilling SHALL NOT save anything by itself.

#### Scenario: Field without a translation
- **WHEN** the user opens the translation mode of `en` and a field has no English value
- **THEN** the field contains the base value and nothing is saved

#### Scenario: Field with a translation
- **WHEN** a field has an English value
- **THEN** the field contains the English value, not the base value

### Requirement: Saving a translation
A translation SHALL be saved only when the user presses the save button of the translation mode. The button SHALL stay disabled while the form holds no change relative to its initial state. Saving SHALL change only the translatable fields of the current language and SHALL NOT change other languages, the base values or the shared attributes. A value that equals the base value of the same field SHALL NOT be stored as a translation, and an empty value SHALL remove the stored translation of that field. After a successful save the user SHALL stay on the same entity and the same language and see a success message; errors SHALL be shown next to their fields. Fields SHALL NOT be required in the translation mode, because an empty field means "no translation, use the base".

#### Scenario: Pressing save after editing
- **WHEN** the user changes the prefilled name to "Jackets" and presses save
- **THEN** the English name "Jackets" is stored, the base name is unchanged, and the user stays on the English form with a success message

#### Scenario: Unchanged form
- **WHEN** the user has not changed anything
- **THEN** the save button is disabled and nothing can be sent

#### Scenario: Value equal to the base
- **WHEN** the user presses save with a field whose value equals the base value of that field
- **THEN** no translation is stored for that field

#### Scenario: Emptying a field
- **WHEN** the user empties a field that had a stored translation and saves
- **THEN** the translation of that field is removed and the field reads as the base value on the next opening

#### Scenario: Other languages
- **WHEN** an entity has translations in `en` and `pl` and the user saves `en`
- **THEN** the `pl` translation is unchanged

### Requirement: Clearing a translation
The translation mode SHALL offer a "Clear translation" action. It SHALL ask for confirmation, then remove every stored translation of the entity in the current language, and keep the user on the page with the form refilled from the base values. The action SHALL be available only when a translation exists.

#### Scenario: Clearing
- **WHEN** an entity has an English translation and the user confirms "Clear translation" in the English mode
- **THEN** the English translation no longer exists, and the form shows the base values

#### Scenario: Nothing to clear
- **WHEN** the entity has no translation in the current language
- **THEN** the action is not offered

### Requirement: Unsaved changes when switching language
When the form holds unsaved changes, choosing another language SHALL ask for confirmation before leaving, and cancelling SHALL keep the user on the current form with the changes intact. A form without changes SHALL switch without a question.

#### Scenario: Switching with changes
- **WHEN** the user edited a field and chooses another language
- **THEN** a confirmation appears, and on cancel the edited field keeps its value

#### Scenario: Switching without changes
- **WHEN** the user has not edited the form and chooses another language
- **THEN** the other language opens immediately

### Requirement: Access follows the entity
Viewing and saving a translation SHALL require the same right as editing the entity in the base language, and no additional right. A user who cannot edit the entity SHALL receive the same response (403 or redirect) for the translation mode and for saving or clearing a translation.

#### Scenario: User without the right
- **WHEN** a user who cannot edit the entity opens its translation mode or sends a save
- **THEN** the response is the same as for the base edit page and nothing changes

### Requirement: Marker of translated languages
An entity SHALL be considered translated into a language when it has at least one stored translated value in it, with the stored values being those that differ from the base. The marker SHALL come from this single definition everywhere it is shown (the switcher and the lists that show translation status).

#### Scenario: Stored translation
- **WHEN** an entity has one stored English value
- **THEN** `en` is marked as translated

#### Scenario: Only values equal to the base
- **WHEN** the user saved the English form without changing any prefilled value
- **THEN** nothing is stored and `en` is not marked
