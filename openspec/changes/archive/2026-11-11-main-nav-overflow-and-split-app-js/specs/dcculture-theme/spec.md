## MODIFIED Requirements

### Requirement: Main navigation
The main navigation SHALL be built from the visible items of the menu `main` and SHALL show a home-icon link (always, linking to `/`) followed by the top-level items of that menu in their order; an item that has visible sub-items SHALL open a dropdown of three columns, closed by a brand stripe, that lists all of them in menu order, flowing down the first column, then the second, then the third. The sub-items SHALL be balanced over the three columns for any number of sub-items; the number of columns SHALL NOT change with it. Below 1024 px the dropdown SHALL be a single column. Only two levels of the menu SHALL be shown: items deeper than the second level are not rendered. An item without an address SHALL still be shown (a parent item as a dropdown trigger, a leaf as plain text), and an item with `target` `_blank` or an external address SHALL open safely in a new tab or be marked as external in a dropdown. An item's own CSS class SHALL be kept on its list element. If the menu `main` does not exist or has no visible items, the navigation bar SHALL NOT be printed. On screens 1024 px wide and wider the dropdown SHALL open on pointer hover and on keyboard focus within the item, without requiring JavaScript. On screens 1024 px wide and wider the items SHALL NOT extend past the content container: without JavaScript the items SHALL wrap onto further rows of the bar, and with JavaScript the top-level items that do not fit in one row SHALL be hidden from the bar and listed in a trailing dropdown item "Ще", which opens like any other dropdown (on hover and on keyboard focus within the item) and keeps the three columns of the dropdown. A hidden item without sub-items SHALL appear in the "Ще" dropdown as its own link; a hidden item with sub-items SHALL appear there as a group: its own link as a heading followed by its sub-links, and a group SHALL NOT be split between columns. The hidden items SHALL keep their order and SHALL be the last items of the menu; "Ще" SHALL be shown only when at least one item is hidden, and SHALL be recomputed when the width available to the bar changes (a window resize, the loading of the fonts). Links in "Ще" SHALL keep the attributes of the original links (new tab, `rel`, the external mark, `aria-current`, the item's CSS class). On screens narrower than 1024 px no navigation bar SHALL be shown, no item SHALL be hidden and no "Ще" item SHALL be shown; the menu button in the top bar SHALL open the navigation in a full-screen modal and SHALL expose its state to assistive technology; the modal SHALL contain the home link, every top-level item and a close button; inside it each item that has sub-items SHALL expand and collapse its sub-links on activation; the modal SHALL close with the close button and on the Escape key, and the page behind it SHALL NOT scroll while it is open.

#### Scenario: Items come from the menu
- **WHEN** the menu `main` has the visible items "Новини" and "Контакти" and a visitor opens any public page
- **THEN** the navigation shows the home-icon link, "Новини" and "Контакти" in that order, and no other items

#### Scenario: Hidden items are not shown
- **WHEN** an item of the menu `main` is disabled or outside its publication period
- **THEN** it and its sub-items are not shown in the navigation

#### Scenario: Dropdown on hover
- **WHEN** a visitor on a 1440 px wide screen moves the pointer over a top-level item that has visible sub-items
- **THEN** its dropdown with the sub-item links in three columns becomes visible

#### Scenario: Any number of sub-items
- **WHEN** a top-level item has one, or eight, visible sub-items on a 1440 px wide screen
- **THEN** the dropdown shows all of them in the stored order, flowing over three columns (one item sits in the first column), and stays within the screen width

#### Scenario: Dropdown on keyboard focus
- **WHEN** a visitor on a 1440 px wide screen tabs onto a link inside an item with a dropdown
- **THEN** the dropdown stays visible while focus is inside the item

#### Scenario: Item without sub-items
- **WHEN** a top-level item has no visible sub-items
- **THEN** it is a plain link to its address and has no dropdown

#### Scenario: Unsafe address
- **WHEN** an item's stored address is not an allowed address (for example `javascript:…`)
- **THEN** the item is shown without a link and the address is not printed

#### Scenario: External sub-item
- **WHEN** a sub-item is external or opens in a new tab
- **THEN** its link opens with `rel="noopener noreferrer"` and, in a dropdown, shows the external-link arrow

#### Scenario: Third level is not shown
- **WHEN** a sub-item itself has children
- **THEN** the sub-item is shown as a link in the dropdown and its children are not rendered

#### Scenario: Menu missing or empty
- **WHEN** the menu `main` does not exist or has no visible items
- **THEN** no navigation bar is printed and the page renders without errors

#### Scenario: Everything fits
- **WHEN** a visitor on a 1440 px wide screen opens a page and all top-level items fit in one row
- **THEN** every item is in the bar in one row and no "Ще" item is shown

#### Scenario: Items overflow with the script
- **WHEN** the top-level items do not fit in one row of the bar on a 1024 px wide screen
- **THEN** the bar is one row inside the content container, the last items that do not fit are not in the bar, and the last item of the bar is "Ще"

#### Scenario: The Ще dropdown
- **WHEN** a visitor moves the pointer over "Ще" (or tabs onto a link inside it) on a 1024 px wide screen
- **THEN** a three-column dropdown opens with the hidden items in the menu order, and it stays within the screen width

#### Scenario: Hidden item with sub-items is a group
- **WHEN** a hidden top-level item has visible sub-items
- **THEN** the "Ще" dropdown shows its link as a heading with its sub-links under it, and the group is not split between columns

#### Scenario: Overflow follows the width
- **WHEN** the window is resized so that more or fewer items fit
- **THEN** the number of hidden items and the content of "Ще" change to match, and "Ще" disappears when everything fits

#### Scenario: Active item in Ще
- **WHEN** the active item, or the item that holds the active sub-item, is hidden in "Ще"
- **THEN** "Ще" shows the trail state and the active link in the dropdown carries `aria-current="page"`

#### Scenario: Overflow without the script
- **WHEN** a visitor on a 1024 px wide screen has scripts disabled and the items do not fit in one row
- **THEN** the items wrap onto a second row of the bar, every link is reachable, and the page has no horizontal scrolling

#### Scenario: No Ще below 1024 px
- **WHEN** a visitor resizes the window from 1024 px or wider to below 1024 px and opens the menu
- **THEN** the modal lists the home link and every top-level item, and there is no "Ще" item

#### Scenario: Collapsed navigation on a phone
- **WHEN** a visitor opens any public page on a 375 px wide screen
- **THEN** no navigation bar is shown under the header, and the menu button is shown in the top bar

#### Scenario: Opening the menu
- **WHEN** a visitor on a 375 px wide screen activates the menu button in the top bar
- **THEN** a full-screen modal opens with the home link, every top-level item and a close button, the button reports it is expanded, and the page behind does not scroll

#### Scenario: Expanding a sub-menu
- **WHEN** the menu modal is open on a 375 px wide screen and the visitor activates a top-level item that has sub-items
- **THEN** its sub-links become visible and the item reports it is expanded, and the item's link does not navigate

#### Scenario: Closing the menu
- **WHEN** the menu modal is open and the visitor activates the close button or presses Escape
- **THEN** the modal closes, the page scrolls again, and focus returns to the menu button

#### Scenario: Menu without the script
- **WHEN** a visitor on a 375 px wide screen has scripts disabled
- **THEN** the page content is readable and the menu is not available; the menu requires the script below 1024 px
