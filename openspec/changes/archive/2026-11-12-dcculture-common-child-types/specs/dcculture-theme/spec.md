## ADDED Requirements

### Requirement: Child types on the common page
The page of the type `common` (`/common`) SHALL print, after the list of its records and before the pagination, a list of cards with the direct child types of `common` (types whose parent type is `common`) that have a non-empty slug. Each card SHALL have the style of a record card of the list (a dot marker and a title) and SHALL show the public title of the child type (its `section_title` in the current language, else its name) as a link to `/{child type slug}`. The cards SHALL be sorted by that public title in the Ukrainian collation order. Types without a slug and types that are not direct children of `common` SHALL NOT be shown. When `common` has no such child type, the list SHALL NOT be printed. The page of any other type SHALL NOT show child type cards.

#### Scenario: Cards are shown under the records
- **WHEN** a guest opens `/common` and `common` has the child types "Заклади культури" (`common/institutions`) and "Міські нагороди" (`common/city-awards`)
- **THEN** below the records of `common` the page shows two cards whose titles link to `/common/institutions` and `/common/city-awards`

#### Scenario: Sorted by public title
- **WHEN** the public titles of the child types are "Фестивалі та конкурси", "Заклади культури" and "Міські нагороди"
- **THEN** the cards appear in the order "Заклади культури", "Міські нагороди", "Фестивалі та конкурси"

#### Scenario: A child type without a slug
- **WHEN** a child type of `common` has no slug
- **THEN** it has no card

#### Scenario: No child types
- **WHEN** `common` has no child type with a slug
- **THEN** the page shows no child type list

#### Scenario: Other types
- **WHEN** a guest opens the list of another type, for example `/news` or `/collectives`
- **THEN** the page shows no child type cards
