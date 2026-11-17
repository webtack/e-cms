## ADDED Requirements

### Requirement: Archive link after the list of a type
The list page of a content type that has an archive SHALL end with one switch link after the list and its paginator. On the main list it SHALL be the link "Archive" with the archive icon on the left and an arrow on the right to `/{type.slug}?period=archive`; on the archive it SHALL be the link "Current" with a back arrow to `/{type.slug}`. The labels SHALL be generic, translatable (Ukrainian "Архів" / "Активне", English "Archive" / "Current"). A type without an archive SHALL show no link, an empty list SHALL still show it, and the theme SHALL NOT show tabs.

#### Scenario: Link on the main list
- **WHEN** a guest opens `/events` and the type has an archive
- **THEN** the page ends with the link "Archive" with the archive icon to `/events?period=archive`

#### Scenario: Link on the archive
- **WHEN** a guest opens `/events?period=archive`
- **THEN** the page ends with the link "Current" to `/events`

#### Scenario: Type without an archive
- **WHEN** a guest opens the list of a type that has no archive
- **THEN** the page shows no link

### Requirement: Home block "Afisha" shows the nearest events
The "Afisha" block of the home page SHALL show the not-past records of the events type, in the main public sort of that type, instead of the static card. When the type does not exist, has no archive, or has no such record, the block SHALL show only its heading, and the link "all" SHALL lead to the events list. The block SHALL read the same not-past selection as the list page, so the home page and `/events` cannot disagree.

#### Scenario: Upcoming events
- **WHEN** the type `events` has an archive and three upcoming visible records
- **THEN** the block shows them nearest first, and each card links to its record

#### Scenario: Only past events
- **WHEN** all visible records of `events` are past
- **THEN** the block shows only its heading

#### Scenario: No events type
- **WHEN** there is no type `events`
- **THEN** the home page renders without an error and the block shows only its heading
