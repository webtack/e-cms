## ADDED Requirements

### Requirement: Home page preloader
The home page of the theme SHALL show a full-screen preloader overlay built from the visible block `block-preloader`: the `p_text` value as the first line, the `title` value as the second line (both uppercase, separated by an animated divider) and the images of `p_img` (at most 10, in order, in the image style `170x170`) as a grid of rotated square tiles above the lines. The overlay SHALL be printed on the home page only (`/` and its language-prefixed variants) and SHALL NOT be printed on any other page. When the block does not exist, is not visible, or has neither text nor title, the page SHALL NOT print the overlay. A missing `p_img` SHALL omit the grid only. The overlay SHALL cover the viewport above all other content, stay visible for 3000 ms after the script runs, then slide up out of the viewport and be removed from the page 300 ms later. Below 992 px the image grid SHALL be hidden and the lines kept.

#### Scenario: Home page with the block
- **WHEN** a guest opens `/` and `block-preloader` is visible with text, title and 10 images
- **THEN** the page contains one element `#preloader` with the 10 image tiles, the first line, the divider and the second line

#### Scenario: Other pages
- **WHEN** a guest opens any page other than the home page (a node, a list, the search, the 404)
- **THEN** the page contains no `#preloader`

#### Scenario: Language-prefixed home
- **WHEN** a guest opens the home page under a language prefix
- **THEN** the page contains the preloader with the values of the block

#### Scenario: Block hidden or missing
- **WHEN** `block-preloader` is disabled, scheduled, expired or deleted
- **THEN** the home page prints no preloader and renders as before

#### Scenario: Block without images
- **WHEN** the block has text and title but no images
- **THEN** the preloader shows the two lines and the divider without the image grid

#### Scenario: Exit
- **WHEN** the home page has loaded with the script running
- **THEN** after 3000 ms the overlay moves up by its own height, and after 3300 ms it is removed from the document

#### Scenario: Mobile width
- **WHEN** the viewport is narrower than 992 px
- **THEN** the image grid is not shown and the lines stay centered

### Requirement: Preloader in accessibility modes
The preloader SHALL follow the accessibility modes of the theme. In the mode `gray` the whole overlay SHALL be grayscale. In the modes `contrast` and `negative` the overlay SHALL use the dark pattern background `bg_symphony-dark.png`, its images SHALL be grayscale, and the colours of the lines and the divider SHALL be taken from the accent of the mode. The colours SHALL be theme tokens, not hard-coded in the component.

#### Scenario: Gray mode
- **WHEN** the mode `gray` is on and the home page is opened
- **THEN** the preloader is shown in grayscale

#### Scenario: Dark modes
- **WHEN** the mode `contrast` or `negative` is on and the home page is opened
- **THEN** the preloader has the dark pattern background and grayscale images, and the lines and the divider use the accent colour of that mode
