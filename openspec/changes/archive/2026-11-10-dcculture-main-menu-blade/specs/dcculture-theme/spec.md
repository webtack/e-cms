# Spec Delta

## MODIFIED Requirements

### Requirement: Main navigation
The main navigation SHALL be built from the visible items of the menu `main` and SHALL show a home-icon link (always, linking to `/`) followed by the top-level items of that menu in their order; an item that has visible sub-items SHALL open a dropdown of three columns, closed by a brand stripe, that lists all of them in menu order, flowing down the first column, then the second, then the third. The sub-items SHALL be balanced over the three columns for any number of sub-items; the number of columns SHALL NOT change with it. Below 1024 px the dropdown SHALL be a single column. Only two levels SHALL be shown: items deeper than the second level are not rendered. An item without an address SHALL still be shown (a parent item as a dropdown trigger, a leaf as plain text), and an item with `target` `_blank` or an external address SHALL open safely in a new tab or be marked as external in a dropdown. An item's own CSS class SHALL be kept on its list element. If the menu `main` does not exist or has no visible items, the navigation bar SHALL NOT be printed. On screens 1024 px wide and wider the dropdown SHALL open on pointer hover and on keyboard focus within the item, without requiring JavaScript. On screens narrower than 1024 px no navigation bar SHALL be shown; the menu button in the top bar SHALL open the navigation in a full-screen modal and SHALL expose its state to assistive technology; the modal SHALL contain the home link, every top-level item and a close button; inside it each item that has sub-items SHALL expand and collapse its sub-links on activation; the modal SHALL close with the close button and on the Escape key, and the page behind it SHALL NOT scroll while it is open.

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
