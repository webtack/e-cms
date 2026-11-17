## ADDED Requirements

### Requirement: Archive period of the list of a content type
When a type of kind `content` has an archive in its public settings, `GET /{type.slug}` SHALL show only the visible nodes that are **not past** by the archive date, sorted by the main public sort, and `GET /{type.slug}?period=archive` SHALL show only the visible **past** nodes, sorted by the archive sort. "Past" SHALL be decided against the start of the current day in UTC, so a node stays in the main list during the whole day of its date. Both lists SHALL use the type's `per_page` and paginator, the page parameter SHALL work in both, and the links of the paginator SHALL keep `period=archive` in the archive list. A `period` value other than `archive` SHALL be treated as the main list, without an error. For a type without an archive the list SHALL stay as it is without the parameter, and `period=archive` SHALL be ignored (the same list is shown). The template SHALL receive which period is shown and whether the type has an archive, so that it can render a switch between the two lists.

#### Scenario: Main list hides the past
- **WHEN** a type `events` has `archive.field = date`, one visible node dated today and one dated last week, and a guest opens `/events`
- **THEN** the response 200 contains the node of today and does not contain the node of last week

#### Scenario: Archive list shows the past
- **WHEN** the same type, and a guest opens `/events?period=archive`
- **THEN** the response 200 contains the node of last week and does not contain the node of today

#### Scenario: A node of today stays
- **WHEN** a node has the `date` value of today 00:00 and the current time is 15:00
- **THEN** the node is in `/events` and not in `/events?period=archive`

#### Scenario: Order of the lists
- **WHEN** the main sort is `date` ascending and the archive sort is `date` descending, and there are several nodes in each period
- **THEN** `/events` lists the nearest date first, and `/events?period=archive` lists the most recent past date first

#### Scenario: Invisible nodes stay hidden
- **WHEN** a node is disabled or scheduled and its date fits the requested period
- **THEN** it is not shown in either list

#### Scenario: Paginator keeps the period
- **WHEN** the archive has more nodes than `per_page` and a guest opens `/events?period=archive`
- **THEN** the paginator links to the other pages contain `period=archive`

#### Scenario: Unknown period value
- **WHEN** a guest opens `/events?period=foo`
- **THEN** the response is the main list

#### Scenario: Type without an archive
- **WHEN** a type has no archive and a guest opens `/news?period=archive`
- **THEN** the response is the same list as `/news`, sorted by the public sort of the type

#### Scenario: Template data
- **WHEN** a guest opens the list of a type with an archive
- **THEN** the template can tell that the type has an archive and which period is shown
