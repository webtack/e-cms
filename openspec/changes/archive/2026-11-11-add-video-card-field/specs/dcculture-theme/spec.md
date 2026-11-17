## ADDED Requirements

### Requirement: Video card for a field with the key `youtube`
A field with the key `youtube` (of the type `youtube`) SHALL be shown as a video card: a 16:9 area with the saved screenshot of the video as its picture, a dimmed mask over it, a play icon in its centre and the video title over the lower part of the picture. The title SHALL be the `title` stored in the field value; when it is empty, the card SHALL show the title of the record that holds the field, and when that is empty too, the name of the field. A value without a saved screenshot SHALL be shown with a neutral placeholder background instead of a picture. The card SHALL be a link to the watch page of the video, so that it works without the script. Showing the card SHALL NOT request any address of YouTube or of another external host. A field of the type `youtube` with any other key SHALL keep the inline player of the `default` theme. The card SHALL be defined once, as the template of the field, and SHALL appear wherever such a field is shown (a list, a details page, a page or a block), not only for one content type.

#### Scenario: Card with a screenshot
- **WHEN** a record has a `youtube` field with a saved screenshot and the title "Гімн Полтави"
- **THEN** its card shows the screenshot in a 16:9 area with the mask, the play icon in the centre and the title "Гімн Полтави"

#### Scenario: Title falls back to the record title
- **WHEN** the value of the `youtube` field has no stored title and the record's title is "Гімн ПОЛТАВИ"
- **THEN** the card shows "Гімн ПОЛТАВИ" as its title

#### Scenario: Title falls back to the field name
- **WHEN** the value of the `youtube` field has no stored title and the record has no title
- **THEN** the card shows the name of the field as its title

#### Scenario: Card without a screenshot
- **WHEN** the value of the `youtube` field has no saved screenshot
- **THEN** the card shows the placeholder background with the play icon and the title, and the page requests no picture from YouTube

#### Scenario: No external requests
- **WHEN** a page with video cards is loaded
- **THEN** no request goes to YouTube or any other external host until a visitor opens a video

#### Scenario: Card without the script
- **WHEN** a visitor with scripts disabled activates a video card
- **THEN** the watch page of the video opens in a new tab

#### Scenario: Another key keeps the inline player
- **WHEN** a record has a field of the type `youtube` with the key `promo_video`
- **THEN** that field is shown with the inline player, not as a card

#### Scenario: Reuse in another place
- **WHEN** a block or another content type has a field with the key `youtube`
- **THEN** it is shown as the same video card without a template of its own

### Requirement: Video modal
Activating a video card SHALL open the video in a modal window over a dimmed page: a 16:9 player of the video with autoplay and a close button. The player SHALL be loaded from the privacy-friendly embed address of the video only when the modal opens, and SHALL be removed when it closes, so that the video stops playing. The modal SHALL close with the close button, on the Escape key and on a click on the dimmed area, and focus SHALL return to the card that opened it. One modal SHALL serve every card on the page. The page behind the modal SHALL NOT scroll while it is open. The modal SHALL fit a 360 px wide screen without horizontal scrolling.

#### Scenario: Opening a video
- **WHEN** a visitor activates the card of a video
- **THEN** a modal opens with a player of that video that starts playing, and the page behind does not scroll

#### Scenario: Player is created on demand
- **WHEN** a page with several video cards is loaded and no card is activated
- **THEN** the page contains no YouTube player

#### Scenario: Closing stops the video
- **WHEN** the modal is open and the visitor activates the close button, clicks the dimmed area, or presses Escape
- **THEN** the modal closes, the player is removed so that the sound stops, and focus returns to the card

#### Scenario: Another video after closing
- **WHEN** a visitor closes the modal and activates a different card
- **THEN** the modal opens with the player of the second video only

### Requirement: Video list page
The list of a `video` record type SHALL show the type's public heading and its records as video cards in a grid of two columns from 768 px width and of one column below it, without a frame, a border or a link to a details page around each card. The grid SHALL NOT scroll horizontally from 360 px width. The list SHALL keep the paginator of the type's settings when the type shows one.

#### Scenario: Two columns on a desktop
- **WHEN** a guest opens `/video` on a 1440 px wide screen and the type has six visible records
- **THEN** the six cards are shown in two columns under the heading of the type

#### Scenario: One column on a phone
- **WHEN** a guest opens `/video` on a 375 px wide screen
- **THEN** the cards are shown in one column, and the page does not scroll horizontally

#### Scenario: Card without a frame or a details link
- **WHEN** the list of video records is shown
- **THEN** each record is only its card; the title is not a link to a details page of the record
