# Spec Delta

## MODIFIED Requirements

### Requirement: Поле «форматований текст» у формі та списку записів
For a field of the type `richtext` the record form SHALL show a formatted text editor instead of a plain text field: a toolbar with headings, bold, italic, underline, strikethrough, alignment (a drop-down list: left, centre, right, justify), text colour, background colour, bulleted and numbered lists, a link, the image button (inserting a file, and for a selected image — its parameters), inserting a YouTube video, inserting a frame, choosing emoji, the button to clear the formatting, the button to view the raw HTML («Джерело»), the button to expand to full screen; the editing area SHALL show the formatting while typing. Inserting files other than images in the editor SHALL be unavailable. The edit form SHALL be filled with the saved value with its formatting. The editor SHALL have an accessible name with the label of the field, a server error mark next to the field, and in a multiple field a separate editor in each row. Saving the record SHALL send the HTML of the value; an empty editor SHALL send an empty value. In the table of records the value of `richtext` SHALL be shown as plain text without tags, shortened to a reasonable length, rather than as raw HTML.

#### Scenario: Formatting in the form
- **WHEN** the user in the editor of the field `richtext` types a text, makes it bold, adds a bulleted list and saves the record
- **THEN** the record is saved, and after reopening the edit form the bold text and the list are visible in the editor

#### Scenario: Link
- **WHEN** the user selects a text, adds the link `https://example.com` and saves the record
- **THEN** after reopening the form the text is a link to this address

#### Scenario: No files other than images
- **WHEN** the user opens the toolbar of the editor
- **THEN** it has only the action of inserting an image, and there are no actions for inserting other files (documents)

#### Scenario: Empty value
- **WHEN** the user leaves the editor of an optional field `richtext` empty and saves the record
- **THEN** the record is saved without a value in this field

#### Scenario: Field error
- **WHEN** the server rejects the value of `richtext`
- **THEN** the error is shown next to the editor, and the entered content is kept in the form

#### Scenario: Value in the table of records
- **WHEN** a field `richtext` with the value `<p>Привіт, <strong>світе</strong></p>` is visible in the list
- **THEN** the cell shows «Привіт, світе» without tags

## ADDED Requirements

### Requirement: Вставка зображення в редактор форматованого тексту
The editor `richtext` SHALL have a button «Зображення» with an accessible name. When an image is selected in the text, the button SHALL open a dialog of the parameters of the image; otherwise it SHALL open a file selection. The selected image SHALL have a noticeable visual selection (an outline), so that it is visible that it is chosen. Choosing a file SHALL upload it at once, as in the widget of the field `image` (by the rules of the profile `image`), and insert the image at the cursor position. The address of the image in the text SHALL be relative, without the protocol and the domain (`/storage/{uuid}.{extension}`). The file SHALL appear in the media library right after the upload. During the upload the button SHALL be disabled; an upload error (an unsupported type, a file that is too large, a server error) SHALL be shown next to the editor, and the content SHALL remain unchanged. In the «Джерело» mode the button SHALL be disabled together with the other formatting buttons. A saved image SHALL be shown in the editor after reopening the form. An image SHALL be selected only by a user action (a click, insertion, a double click): when the form is opened and after the value is updated, no image is selected, even if the text starts with an image.

#### Scenario: Inserting an image
- **WHEN** the user puts the cursor in the text, presses «Зображення» and chooses a PNG
- **THEN** the file is uploaded, an image appears at the cursor position, and in «Джерело» its `src` has the form `/storage/{uuid}.png` without `http` and the domain

#### Scenario: Saving and reopening
- **WHEN** the user inserts an image and saves the record, then opens the edit form
- **THEN** the image is visible in the editor

#### Scenario: File in the media library
- **WHEN** the user inserts an image and saves the record
- **THEN** this file is in the media library with one usage, and the details of the file state the record, the field and the language

#### Scenario: Unsupported file
- **WHEN** the user chooses a file whose type is not allowed by the profile `image`
- **THEN** an error is shown next to the editor, the image is not inserted, and the content is not changed

#### Scenario: Source mode
- **WHEN** the user turns on «Джерело»
- **THEN** the button «Зображення» is disabled

#### Scenario: Selecting an image
- **WHEN** the user clicks on an image in the editor
- **THEN** the image has a visible selection outline, and after a click elsewhere from the image there is no outline

#### Scenario: Image is not selected after opening
- **WHEN** the user opens the form of a record whose text starts with an image
- **THEN** the image has no selection outline, the dialog of the parameters is not open, and the button «Зображення» opens the file selection

#### Scenario: Button with a selected image
- **WHEN** an image is selected and the user presses «Зображення»
- **THEN** the dialog «Параметри зображення» opens, and the file selection does not open

#### Scenario: Button without a selected image
- **WHEN** no image is selected and the user presses «Зображення»
- **THEN** the file selection opens

### Requirement: Параметри зображення в редакторі форматованого тексту
A double click on an image in the editor `richtext` SHALL open the dialog «Параметри зображення» (in the same way as the button «Зображення» with an image selected); there is no separate action in the toolbar. The dialog SHALL be modal, with the accessible name «Параметри зображення», with the fields «Ширина (px)», «Альтернативний текст» and «Підпис» and the buttons «Готово» and «Початковий розмір»; the current values of the selected image SHALL be in the fields. «Готово» SHALL apply the values to the image. The width SHALL be an integer number of pixels from 1 to 4000; another value SHALL give an error next to the field without applying. An empty width or «Початковий розмір» SHALL remove the width (the image is shown at its natural size, the height is proportional). The default alternative text SHALL equal the name of the uploaded file. A non-empty caption SHALL be shown under the image in the editor and stored as a visible caption; an empty caption SHALL remove it. The saved width, alternative text and caption SHALL be restored after reopening the form.

#### Scenario: Changing the width
- **WHEN** the user double-clicks an inserted image, enters the width 200 in the dialog «Параметри зображення» and presses «Готово», and saves the record
- **THEN** the image in the editor has a width of 200 px, and after reopening the form the width remains 200 (in «Джерело» `width="200"`)

#### Scenario: Invalid width
- **WHEN** the user enters the width `0`, `5000` or `abc`
- **THEN** an error is shown next to the field, and the width of the image does not change

#### Scenario: Natural size
- **WHEN** the user double-clicks an image with the width 200 and presses «Початковий розмір»
- **THEN** the width is removed, and in «Джерело» there is no `width`

#### Scenario: Caption
- **WHEN** the user enters the caption «Наш офіс», presses «Готово» and saves the record
- **THEN** «Наш офіс» is visible under the image, in «Джерело» the image is in a `figure` with a `figcaption`, and after reopening the form the caption is in place

#### Scenario: Alternative text
- **WHEN** the user inserts an image from the file `logo.png`, then changes the alternative text to «Логотип»
- **THEN** at first `alt` equals `logo.png`, and after «Готово» — «Логотип»

#### Scenario: Clearing the caption
- **WHEN** the user clears the caption of the image and presses «Готово»
- **THEN** the image remains without `figure` and `figcaption`

#### Scenario: Double click
- **WHEN** the user double-clicks an image with the width 200 and the caption «Наш офіс»
- **THEN** the dialog «Параметри зображення» opens, the fields contain 200, the current `alt` and «Наш офіс»

#### Scenario: No separate action in the toolbar
- **WHEN** the user looks at the toolbar
- **THEN** there is no separate action «Параметри зображення»

### Requirement: Вирівнювання зображення в редакторі форматованого тексту
When an image is selected in the editor `richtext`, the existing alignment menu SHALL apply the choice to the image: «Ліворуч», «По центру» and «Праворуч»; «По ширині» SHALL be disabled. The caption SHALL always be centred right under the image (and not centred in the editing area), regardless of the alignment of the image: `figure` SHALL have the width of the image (`width: {width}px; max-width: 100%`, and without a set width — `width: fit-content`), so that a long caption wraps within the width of the image and does not shrink it, be aligned by margins (`margin-left`/`margin-right: auto`) and centre the caption inside (`text-align: center`). The alignment SHALL be stored as standard HTML: an inline style on `figure` (centre — `margin-left: auto; margin-right: auto`, right — `margin-left: auto`, left — no margins), and «Ліворуч» without a caption SHALL return the image to a bare `img`; a previously saved `figure` with only `text-align: center|right` SHALL be read as centre/right. Without a selected image the menu SHALL work as before. The saved alignment SHALL be restored after reopening the form.

#### Scenario: Center («По центру» — "Center")
- **WHEN** the user selects an image, chooses «По центру» and saves the record
- **THEN** the image in the editor is centred, in «Джерело» it is in a `figure` with `margin-left: auto; margin-right: auto`, and after reopening the form it remains centred

#### Scenario: Image with a caption
- **WHEN** an image with the caption «Наш офіс» is selected and «По центру» is chosen
- **THEN** both the image and the caption are centred

#### Scenario: The caption does not follow the image
- **WHEN** an image with a caption is selected and «Праворуч» or «Ліворуч» is chosen
- **THEN** the image is on the right (left), and the caption is centred right under the image (the centre of the caption coincides with the centre of the image)

#### Scenario: Set width with a long caption
- **WHEN** an image has the width 500 and a caption with a long word or a long sentence
- **THEN** the width of the image remains 500 (it does not shrink to the longest word), and the caption wraps over lines within this width

#### Scenario: Right («Праворуч» — "Right")
- **WHEN** an image is selected and «Праворуч» is chosen
- **THEN** the image is on the right, and in «Джерело» there is a `figure` with `margin-left: auto`

#### Scenario: Return to the left
- **WHEN** a centred image without a caption is selected and «Ліворуч» is chosen
- **THEN** in «Джерело» the image is again a bare `img` without `figure` and `style`

#### Scenario: Justify is unavailable («По ширині» — "Justify")
- **WHEN** an image is selected and the alignment menu is opened
- **THEN** the item «По ширині» is disabled

#### Scenario: The image itself is aligned
- **WHEN** an image without a caption is selected and «По центру» is chosen (then «Праворуч»)
- **THEN** the image itself (not only the caption) is visually on the centre (on the right) of the editing area

### Requirement: Ширина форми запису ноди
The create and edit form of a record of a node SHALL have a maximum width `max-w-5xl` (1024px), so that the editor `richtext` and the fields are not cramped.

#### Scenario: Wide screen
- **WHEN** the user opens the form of a record on a screen wider than 1100px
- **THEN** the form takes a width of more than 672px, but not more than 1024px

#### Scenario: Long caption
- **WHEN** an image has a caption longer than the width of the image
- **THEN** the caption wraps over lines within the width of the image, `figure` does not become wider than the image, and the centre of the caption coincides with the centre of the image

#### Scenario: Default width
- **WHEN** the user inserts an image
- **THEN** it is inserted with the width of 500 px (`width="500"` in «Джерело»), and in the dialog of the parameters the field «Ширина» shows 500

#### Scenario: Natural size after insertion
- **WHEN** the user presses «Початковий розмір» for a just inserted image
- **THEN** `width` is removed, and the image has its natural size
