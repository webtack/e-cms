# Spec Delta

## MODIFIED Requirements

### Requirement: Home block "Afisha" shows the nearest events
The "Afisha" block of the home page SHALL show the not-past records of the events type, in the main public sort of that type, instead of the static card. When the type does not exist, has no archive, or has no such record, the block SHALL show only its heading, and the link "all" SHALL lead to the events list. The block SHALL read the same not-past selection as the list page, so the home page and `/events` cannot disagree. Each card SHALL print the date of the event in the format of the requirement "Event dates" of the capability `dcculture-events`, the same as the list page.

#### Scenario: Upcoming events
- **WHEN** the type `events` has an archive and three upcoming visible records
- **THEN** the block shows them nearest first, and each card links to its record

#### Scenario: Only past events
- **WHEN** all visible records of `events` are past
- **THEN** the block shows only its heading

#### Scenario: No events type
- **WHEN** there is no type `events`
- **THEN** the home page renders without an error and the block shows only its heading

#### Scenario: Dates in the list format
- **WHEN** the block shows an event with one date and an event with a range of dates
- **THEN** the first card prints the single date and the second the range, written exactly as the list page writes them
