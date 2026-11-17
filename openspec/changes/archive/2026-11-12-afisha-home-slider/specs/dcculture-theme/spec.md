# Spec Delta

## MODIFIED Requirements

### Requirement: Home block "Afisha" shows the nearest events
The "Afisha" block of the home page SHALL show up to five not-past records of the events type, in the main public sort of that type, instead of the static card. When the type does not exist, has no archive, or has no such record, the block SHALL show only its heading, and the link "all" SHALL lead to the events list. The block SHALL read the same not-past selection as the list page, so the home page and `/events` cannot disagree. Each event SHALL be one slide of a horizontally scrolled slider, with the same structure for every slide (poster, date, title, link to the record) and no separate small blocks; the slider SHALL show one slide at a time, the slide being as wide as the block. Each slide SHALL print the date of the event in the format of the requirement "Event dates" of the capability `dcculture-events`, the same as the list page. The poster SHALL be shown whole in a picture of a fixed width and height, whatever the proportions of the original, without cropping. A record without a poster SHALL give a slide without a picture.

#### Scenario: Upcoming events
- **WHEN** the type `events` has an archive and seven upcoming visible records
- **THEN** the slider has five slides, the five nearest first, and each slide links to its record

#### Scenario: Fewer events than the limit
- **WHEN** the type `events` has an archive and two upcoming visible records
- **THEN** the slider has two slides and no empty slide

#### Scenario: Only past events
- **WHEN** all visible records of `events` are past
- **THEN** the block shows only its heading

#### Scenario: No events type
- **WHEN** there is no type `events`
- **THEN** the home page renders without an error and the block shows only its heading

#### Scenario: Dates in the list format
- **WHEN** the slider shows an event with one date and an event with a range of dates
- **THEN** the first slide prints the single date and the second the range, written exactly as the list page writes them

#### Scenario: Posters of different proportions
- **WHEN** one event has a wide poster and another a tall one
- **THEN** both pictures have the same width and height, and each poster is visible whole, with the free space filled by a blurred copy of the same poster

## ADDED Requirements

### Requirement: Afisha slider navigation
The slider of the "Afisha" block SHALL scroll horizontally and stop on a slide edge, so that it can be swiped, scrolled with the wheel and reached with the keyboard without JavaScript. With JavaScript it SHALL also have the previous and next buttons (the one that cannot move is disabled at the edge), one dot per slide that marks the visible slide and moves to a slide, and an autoplay. The buttons, the dots and the slider SHALL have accessible names in the language of the site. A slider with a single slide SHALL show no buttons and no dots.

#### Scenario: Next button
- **WHEN** the visitor presses the next button on the first slide
- **THEN** the second slide becomes the visible one and its dot is marked

#### Scenario: Edges
- **WHEN** the first slide is visible
- **THEN** the previous button is disabled, and on the last slide the next button is disabled

#### Scenario: Without JavaScript
- **WHEN** the script is not loaded
- **THEN** the slides can still be scrolled by swipe or wheel, and each slide is a working link

#### Scenario: Single event
- **WHEN** only one event is upcoming
- **THEN** the block shows its slide without buttons and dots

### Requirement: Afisha slider autoplay
The slider SHALL move to the next slide every six seconds and go back to the first slide after the last one. The autoplay SHALL pause while the pointer is over the slider, while the keyboard focus is inside it, and while the browser tab is hidden, and SHALL continue when the cause is gone. The autoplay SHALL NOT run when the visitor prefers reduced motion, and SHALL stop for good after the visitor moves the slider by hand (button, dot, swipe, wheel or keyboard).

#### Scenario: Automatic change
- **WHEN** the visitor does nothing for six seconds
- **THEN** the next slide becomes the visible one, and after the last slide the first one

#### Scenario: Pause on hover and focus
- **WHEN** the pointer is over the slider or the focus is on a link or button inside it
- **THEN** the slides do not change until the pointer leaves and the focus moves out

#### Scenario: Hidden tab
- **WHEN** the visitor switches to another tab and comes back
- **THEN** no slides were skipped meanwhile, and the autoplay continues after the return

#### Scenario: Reduced motion
- **WHEN** the visitor's system asks for reduced motion
- **THEN** the slider does not change slides by itself

#### Scenario: Manual control
- **WHEN** the visitor presses a button or swipes the slider
- **THEN** the autoplay does not start again until the page is reloaded
