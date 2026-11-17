# Spec Delta

## Purpose

Describes how the public theme `dcculture` shows records of the `events` type: how an event's dates are written and how the list of events looks as rows of poster cards.

## ADDED Requirements

### Requirement: Event dates
An event's dates SHALL be written by the values of its `date` field: no value gives no date; one value, or several values on the same calendar day, gives the start date only; values on different calendar days give a range from the first value to the last. A date SHALL be written as the day number without a leading zero and the month name in the genitive case of the site language; the year SHALL be added only when it is not the current year. A range SHALL be written with an en dash with spaces: when both ends are in the same month and year the month is named once (`5 – 22 листопада`), otherwise each end carries its own month (`28 листопада – 3 грудня`). The year of a range SHALL be written once, after the last end, when both ends are in the same year and it is not the current year (`5 – 22 січня 2027`, `28 січня – 3 лютого 2027`); when the ends are in different years each end SHALL carry its own year (`28 грудня 2026 – 3 січня 2027`); when both ends are in the current year no year is written. The time of day SHALL NOT be shown. The values SHALL be read as they are stored, in the application timezone, without conversion. The same format SHALL be used on every page that prints event dates.

#### Scenario: Single date
- **WHEN** an event has one `date` value in the current year, the 13th of November
- **THEN** its date is written `13 листопада`

#### Scenario: Two values on one day
- **WHEN** an event has two `date` values on the same calendar day with different times
- **THEN** its date is written as that one day, without a range and without a time

#### Scenario: Range within a month
- **WHEN** an event has the values the 5th and the 22nd of November of the current year
- **THEN** its date is written `5 – 22 листопада`

#### Scenario: Range across months
- **WHEN** an event has the values the 28th of November and the 3rd of December of the current year
- **THEN** its date is written `28 листопада – 3 грудня`

#### Scenario: Other year
- **WHEN** an event has one value in a year other than the current one
- **THEN** its date is written with the year, for example `13 листопада 2027`

#### Scenario: Range in another year
- **WHEN** an event has the values the 5th and the 22nd of January of a year other than the current one
- **THEN** its date is written `5 – 22 січня` followed by that year, once

#### Scenario: Range across years
- **WHEN** an event has the values the 28th of December 2026 and the 3rd of January 2027
- **THEN** its date is written `28 грудня 2026 – 3 січня 2027`

#### Scenario: No date
- **WHEN** an event has no `date` value
- **THEN** no date is printed and the card has no empty date element

### Requirement: Events list as rows of poster cards
The list page of the `events` type (`/events` and `/events?period=archive`) SHALL show its records as a list of cards, one card per row, inside one wrapper limited to a maximum width of 60rem (960 px), 25rem (400 px) below 640 px, and centred under the page head, with no horizontal page scroll. A card SHALL show the poster (the first value of the `poster` field) whole, never cropped, on the left in a column of fixed width, its height following the picture, and the event dates (with a calendar icon before them), the title and, when `body` is not empty, its plain-text excerpt (cut, a few lines) on the right, with an arrow icon under the text at the right edge marking the card as a link; on hover and keyboard focus the card SHALL be visibly highlighted (teal border, larger shadow, teal title, shifted arrow; the card itself SHALL NOT move or change size); on a phone the poster SHALL be above the content and the content (date, title, excerpt, arrow) SHALL be centred, and the card SHALL have a light grey background and the whole card SHALL be one link to the event. A record without a poster SHALL give a card without the picture, and a record without a title SHALL show its slug. The pagination and the archive switch link SHALL stay under the list, and an empty list SHALL still show the archive link.

#### Scenario: Portrait poster
- **WHEN** a guest opens `/events` and an event has a portrait poster
- **THEN** the card shows the whole poster with the date and the title beside it, and the card links to `/events/{slug}`

#### Scenario: Landscape poster
- **WHEN** an event has a poster whose width is much larger than its height
- **THEN** the poster is shown whole at the column width, with its own height, not cropped

#### Scenario: Event without a poster
- **WHEN** an event has no poster
- **THEN** the card is printed without the picture and its date and title are still shown

#### Scenario: Poster side follows the width
- **WHEN** a guest opens `/events` at 1440 px, 1024 px, 768 px and 375 px wide
- **THEN** from 640 px the poster is left of the content, at 375 px it is above the content, there is one card per row, and the page does not scroll horizontally

#### Scenario: Archive link and pagination
- **WHEN** the list has more records than one page and the type has an archive
- **THEN** the pagination and the archive link are shown under the list

#### Scenario: Archive period uses the same cards
- **WHEN** a guest opens `/events?period=archive`
- **THEN** the past events are shown as the same cards in the archive sort

### Requirement: Event details page
The page of one `events` record (`/events/{slug}`) SHALL show, in this order: a light grey block with the poster (the first value of `poster`) and under it the event dates in the same format as on the list; the title; and, only when `body` has content, a divider and the text. The poster SHALL be small (at most 320 px high), shown whole without cropping, and SHALL open in the photo lightbox on click. The block SHALL NOT be printed when the record has neither a poster nor a date. The remaining visible fields (`youtube`, `files`, ...) SHALL follow the text.

#### Scenario: Poster, dates and text
- **WHEN** a guest opens an event that has a poster, dates and a text
- **THEN** the grey block shows the small poster with the dates under it, then the title, the divider and the text follow

#### Scenario: Poster opens the lightbox
- **WHEN** a guest clicks the poster
- **THEN** the original poster opens whole in the photo lightbox, without the previous / next buttons

#### Scenario: Event without a text
- **WHEN** an event has an empty `body`
- **THEN** the title is printed without the divider and the text

#### Scenario: Event without a poster
- **WHEN** an event has dates but no poster
- **THEN** the grey block shows only the dates
