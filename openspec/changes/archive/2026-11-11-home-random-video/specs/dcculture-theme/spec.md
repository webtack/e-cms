# Spec Delta

## MODIFIED Requirements

### Requirement: Home page sections
The home page SHALL show, in this order after the frame's navigation: the hero (heading, lead text, photo strip between brand stripes), the "about the city" block, the news and the poster columns, the tourist banner, the contest band, and the map section. The text, images and links of these sections SHALL be the static content of the design, except for the video of the "about the city" block. That block SHALL show the video card (the same card, with the same modal player, as on the video list) of one visible record of the `video` content type that has a video, chosen at random on every page load; when the record's video field holds several videos, the block SHALL show only the first one. The card SHALL take the place and the size of the block's picture and SHALL keep the block's decoration. When no visible `video` record has a video, the block SHALL show the static picture of the design instead. The home page SHALL not depend on any content node, menu or block existing.

#### Scenario: Home sections present
- **WHEN** a guest opens `/` on an empty database
- **THEN** the six sections are present in the stated order and the page responds without error

#### Scenario: Photo strip without JavaScript
- **WHEN** the home page is opened with scripts disabled
- **THEN** the photo strip still scrolls continuously by CSS animation

#### Scenario: Random video in the about block
- **WHEN** a guest opens `/` and the `video` section has several visible records with a video
- **THEN** the "about the city" block shows the video card of one of them, and reloading the page may show another one

#### Scenario: The same card as on the video list
- **WHEN** the home page shows the video of a record
- **THEN** the card looks and works as the card of that record on `/video`: the screenshot, the play icon and the title, and a click opens the video in the modal player

#### Scenario: Only visible records with a video
- **WHEN** some `video` records are disabled, scheduled, expired or have no video
- **THEN** the home page never shows them in the "about the city" block

#### Scenario: Several videos in one record
- **WHEN** the chosen record holds more than one video
- **THEN** the block shows one card, for the first video

#### Scenario: Static picture without videos
- **WHEN** the `video` section has no visible record with a video
- **THEN** the "about the city" block shows the static picture of the design and the page responds without error

#### Scenario: Card in the block on every width
- **WHEN** the home page is shown at 1440 px, 768 px and 375 px
- **THEN** the video card fills the place of the former picture, the block's decoration stays in place, and the page does not scroll horizontally
