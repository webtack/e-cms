## ADDED Requirements

### Requirement: News details page
The page of one record of the `news` type (`/news/{slug}`) SHALL show, in this order inside the content column of the theme: the title of the record, a meta line with the date the record was added and its view counter (the same two items, with the same icons and format, as the card of the news list), the cover, the text, the photo gallery, and then the remaining visible fields of the type in their usual order. The cover SHALL be the first value of the field `preview`; a record without a cover SHALL show no cover and no empty space for it. The text SHALL be the field `body`. The photo gallery SHALL be the field `gallery`, shown as described in the requirement "Photo gallery for a field with the key `gallery`", and SHALL NOT repeat the cover. The `preview` and `gallery` fields SHALL stay separate: the page SHALL NOT take its cover from the gallery, and a record without a gallery SHALL show no gallery. The cover, the text and the gallery SHALL each be printed once, in the stated place, and SHALL NOT be printed a second time by the output of the remaining fields. Other types SHALL keep the generic details page.

#### Scenario: Order of the parts
- **WHEN** a guest opens a news record that has a cover, a text and a gallery
- **THEN** the title, the meta line, the cover, the text and the gallery appear in that order, and the gallery is printed once

#### Scenario: Meta line
- **WHEN** a guest opens a news record
- **THEN** the meta line shows the date added as `dd.mm.yyyy` and the number of views, as the news list card does

#### Scenario: Record without a gallery
- **WHEN** a guest opens a news record that has no `gallery` photos
- **THEN** the page shows the cover and the text, and no gallery and no empty block

#### Scenario: Record without a cover
- **WHEN** a guest opens a news record that has no cover
- **THEN** the page shows the title, the meta line and the text, and no cover and no empty block

#### Scenario: Cover is not taken from the gallery
- **WHEN** a news record has a gallery but no cover
- **THEN** the page does not show the first gallery photo as a cover

#### Scenario: Another type
- **WHEN** a guest opens a record of a type other than `news`
- **THEN** the page is the generic details page, not the news page

### Requirement: Photo gallery for a field with the key `gallery`
A field with the key `gallery` (of the type `image`) SHALL be shown as a photo gallery, defined once as the template of that field, so that it appears wherever such a field is shown (a list, a details page, a page or a block), not only for the `news` type. The caption of its photos SHALL be the title of the record that holds the field, and when that is empty, the name of the field. A field of the type `image` with any other key SHALL keep the output of the `default` theme. The photos of the gallery SHALL be shown as a grid of equal thumbnails in the proportion 4:3, three columns from 768 px wide and two columns below, with no carousel, no automatic scrolling and no magnifier icon. A thumbnail SHALL show its photo cropped to fill the tile. Hovering a thumbnail SHALL show a pointer cursor and a light dimming. The layout SHALL depend on the number of photos: one photo SHALL be shown over the full width of the content column in its own proportions, without a grid and without cropping; two photos SHALL be shown as the same 4:3 tiles in two columns at every width (the third column of the grid is not used); three to eight photos SHALL be shown in the grid; more than eight photos SHALL, when the script runs, show the first eight tiles, and the eighth tile SHALL be dimmed and SHALL show "+N" over its photo, where N is the number of photos that have no tile (the total minus eight). Activating the "+N" tile SHALL open the lightbox on the photo of that tile, the eighth. The page SHALL NOT scroll horizontally at any width from 360 px.

#### Scenario: Grid of several photos
- **WHEN** a news record has six gallery photos and is opened on a 1440 px wide screen
- **THEN** the six photos are shown as 4:3 tiles in two rows of three

#### Scenario: Two columns on a phone
- **WHEN** the same record is opened on a 375 px wide screen
- **THEN** the tiles are shown in three rows of two, and the page has no horizontal scrolling

#### Scenario: One photo
- **WHEN** a news record has one gallery photo
- **THEN** the photo is shown over the full width of the content column in its own proportions, not cropped and not in a grid

#### Scenario: Two photos
- **WHEN** a news record has two gallery photos
- **THEN** the photos are shown as two 4:3 tiles side by side, in two columns at every width, each cropped to fill its tile

#### Scenario: Exactly eight photos
- **WHEN** a news record has eight gallery photos
- **THEN** all eight tiles are shown and none of them shows "+N"

#### Scenario: More than eight photos
- **WHEN** a news record has twelve gallery photos
- **THEN** eight tiles are shown, the eighth is dimmed and shows "+4", and activating it opens the lightbox on the eighth photo with the counter "8 / 12"

#### Scenario: No magnifier
- **WHEN** a guest hovers a thumbnail on a 1440 px wide screen
- **THEN** the cursor is a pointer, the tile is lightly dimmed, and no magnifier icon is shown

### Requirement: Photo lightbox
Activating a gallery thumbnail, the "+N" tile, or a single photo SHALL open a lightbox over the page that shows the whole original photo, fitted into the screen without cropping, on a dimmed background. The lightbox SHALL show the caption (the title of the record) and, for a group of more than one photo, a counter "N / M" and previous and next buttons; it SHALL change the photo with the arrow keys and with a horizontal swipe, SHALL close with a close button, with Escape and with a click on the dimmed background, and SHALL return focus to the element that opened it. The cover SHALL NOT open the lightbox: it is a plain picture, not a link. Moving to the next or previous photo SHALL wrap from the last photo to the first and back. All photos of the gallery SHALL be reachable in the lightbox, including those not shown as tiles. The page behind the lightbox SHALL NOT scroll while it is open. The lightbox SHALL need no library of the old site and SHALL NOT request any address of an external host. A photo SHALL be loaded when it is shown, and the next photo MAY be loaded in advance. Without the script a thumbnail and a single photo SHALL be plain links to the original photo, every photo of the gallery SHALL be shown as a tile and no "+N" tile SHALL be shown, so that every photo remains reachable.

#### Scenario: Opening a gallery photo
- **WHEN** a guest activates the third thumbnail of a gallery of twelve photos
- **THEN** a lightbox opens with the whole third photo, the counter "3 / 12", the title of the record as the caption, and previous and next buttons

#### Scenario: Changing the photo
- **WHEN** the lightbox is open on photo 3 and the guest presses the right arrow key or swipes left
- **THEN** photo 4 is shown and the counter reads "4 / 12"

#### Scenario: Wrapping around
- **WHEN** the lightbox is open on the last photo and the guest activates the next button
- **THEN** the first photo is shown

#### Scenario: Photos behind "+N"
- **WHEN** a gallery has twelve photos and the guest opens the lightbox from the "+N" tile and moves through the group
- **THEN** the eighth photo is shown first, then photos 9 to 12 one by one, so no photo of the gallery is unreachable

#### Scenario: Cover does not open the lightbox
- **WHEN** a guest activates the cover of a news record
- **THEN** nothing opens: the cover is a plain picture and not a link

#### Scenario: Closing
- **WHEN** the lightbox is open and the guest activates the close button, presses Escape or clicks the dimmed background
- **THEN** the lightbox closes, the page scrolls again, and focus returns to the thumbnail that opened it

#### Scenario: Page does not scroll behind
- **WHEN** the lightbox is open
- **THEN** scrolling does not move the page behind it

#### Scenario: Without the script
- **WHEN** a guest opens a news record with scripts disabled and activates a thumbnail
- **THEN** the browser opens the original photo; and for a gallery of twelve photos all twelve tiles are shown, none with "+N"

#### Scenario: Touch screen
- **WHEN** a guest taps a thumbnail on a touch screen
- **THEN** the lightbox opens, with no hover step required
