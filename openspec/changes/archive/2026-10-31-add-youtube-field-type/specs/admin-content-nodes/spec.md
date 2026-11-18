## ADDED Requirements

### Requirement: Елемент введення поля «відео YouTube»
The create and edit form of a record SHALL, for a field of the type `youtube`, show the list of added videos and the button «Додати відео» (for a non-multiple field the button disappears when a video is already there). Each video SHALL be a card with a screenshot (a preview), a title and a description, and the buttons «Редагувати» and «Видалити». The title SHALL be a direct link to the video (the `url` of the value), which opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`); if there is no title, the text of the link SHALL be the address itself. Adding and editing SHALL happen in a modal window with the field of the link, the button «Отримати дані», the editable fields of the title and the description, and a block with the screenshot. Changes from the modal window SHALL reach the form of the record only after confirmation («Готово»); «Скасувати» or closing the window SHALL discard the edits. The button «Отримати дані» SHALL be inactive while the link is empty. After successful data retrieval the window SHALL fill in the title, the description and the screenshot, overwriting the current ones, and show the preview of the screenshot. The user SHALL be able to change the title and the description, replace the screenshot with another image of the profile `image`, or remove it. A change of the link itself SHALL NOT overwrite the already entered title, description and screenshot until the user presses «Отримати дані» again. For a multiple field the list SHALL contain several cards by the general rules of the form, each with its own link and data. Server errors SHALL be shown in the modal window next to the field, and the entered data SHALL be kept. The saving of the record itself SHALL work as before.

#### Scenario: Card in the list
- **WHEN** the form has a video with a screenshot, a title and a description
- **THEN** the list shows a card with the screenshot, the title-link and the description

#### Scenario: The title opens the video
- **WHEN** the user presses the title of a card
- **THEN** the link to the video opens in a new tab, and the form of the record stays open

#### Scenario: Modal window of adding
- **WHEN** the user presses «Додати відео»
- **THEN** a modal window opens with an empty form of the video, and after «Готово» a new card appears in the list

#### Scenario: Modal window of editing
- **WHEN** the user presses «Редагувати» on a card
- **THEN** the window opens with the data of this video, and after «Готово» the card shows the changed data

#### Scenario: Cancellation in the modal window
- **WHEN** the user changes the data in the window and presses «Скасувати» or closes the window
- **THEN** the card and the value of the form remain as they were

#### Scenario: Getting the data
- **WHEN** the user enters the link of an available video in the window and presses «Отримати дані»
- **THEN** the title, the description and the screenshot are filled into the window, the preview of the screenshot is visible, and the record is not saved yet

#### Scenario: Editing the received data
- **WHEN** the user changes the title and the description, presses «Готово» and saves the record
- **THEN** the changed title and description are saved, and after reloading the page they are shown on the card

#### Scenario: Replacing the screenshot
- **WHEN** the user uploads their own image in the window instead of the screenshot, presses «Готово» and saves the record
- **THEN** after reloading the card shows the new image

#### Scenario: Removing the screenshot
- **WHEN** the user removes the screenshot in the window, presses «Готово» and saves the record
- **THEN** the value is saved without a screenshot, and the title and the description remain

#### Scenario: Removing a video from the list
- **WHEN** the user presses «Видалити» on a card and saves the record
- **THEN** the video is absent from the saved value

#### Scenario: Button without a link
- **WHEN** the field of the link in the window is empty
- **THEN** the button «Отримати дані» is inactive

#### Scenario: Changing the link does not erase edits
- **WHEN** after manual edits the user changes the link without pressing «Отримати дані»
- **THEN** the title, the description and the screenshot remain as they were

### Requirement: Отримання даних відео за посиланням
The admin SHALL provide to an authenticated user with a confirmed email a request that, by a link to a YouTube video, reads from the YouTube Data API the title, the description and the largest available screenshot, stores the screenshot in the media library as a file of the profile `image`, and responds with the links to the video (as entered, and for Shorts — the ordinary one), the title, the description and the screenshot. A link to Shorts SHALL be brought to the ordinary link of the video, and the data SHALL be read by it. The request SHALL NOT change any record or values of fields. The API key SHALL be taken from the configuration of the application and SHALL NOT get into the response or to the client. If the video is unavailable, the request SHALL NOT create a file and SHALL NOT change the state of the form, and the response SHALL contain a message that distinguishes the reasons: the link does not look like a YouTube video; the video was not found or it is private; the API key is not configured; the YouTube service did not respond or the quota is exhausted. A failure of the screenshot download SHALL be treated as an unavailable video, without a partial result.

#### Scenario: Successful retrieval
- **WHEN** the user sends the link of a public video
- **THEN** the response contains the entered link, the title, the description and the screenshot with the link to the stored file, and this file appeared in the media library

#### Scenario: Link to Shorts
- **WHEN** the user sends `https://www.youtube.com/shorts/{id}` of a public video
- **THEN** the response contains the ordinary link `https://www.youtube.com/watch?v={id}` and the data of this video

#### Scenario: Link that is not to a video
- **WHEN** a text that does not look like a YouTube link is sent
- **THEN** the request is rejected with a message about an invalid link, the YouTube was not contacted, no files are created

#### Scenario: Video not found
- **WHEN** the link is correct, but YouTube does not return a video (deleted or private)
- **THEN** the response contains a message that the video is unavailable, no files are created, the data in the form did not change

#### Scenario: Key not configured
- **WHEN** the API key is absent in the configuration
- **THEN** the response contains a message that the integration with YouTube is not configured, the YouTube was not contacted

#### Scenario: YouTube service unavailable
- **WHEN** YouTube does not respond or responds with a quota or access error
- **THEN** the response contains a message about the temporary unavailability of the service, no files are created

#### Scenario: Access without login
- **WHEN** an unauthenticated user sends the request
- **THEN** the request is not executed, as with the other requests of the admin

#### Scenario: Key is not on the client
- **WHEN** any request of getting the data is executed
- **THEN** the API key is absent from the response and from the data of the page

### Requirement: Показ поля «відео YouTube» у таблиці записів
The table of records SHALL show, for a field of the type `youtube`, the title of the video, and if there is none — the link to the video. For a multiple field the values SHALL be shown separated by commas, as for the other types. The column SHALL NOT be sortable.

#### Scenario: Display of the title
- **WHEN** the column of the field `youtube` with the title of the video is visible in the table of records
- **THEN** the cell shows the text of the title, not a link and not JSON

#### Scenario: Display without a title
- **WHEN** the video has no title
- **THEN** the cell shows the link to the video
