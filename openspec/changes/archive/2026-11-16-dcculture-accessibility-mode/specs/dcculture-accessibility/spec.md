# Spec Delta

## Purpose

The accessibility ("version for visually impaired") panel of the public theme `dcculture`: a dropdown in the top bar that lets a visitor enlarge the text and switch to a grayscale, high-contrast or negative-contrast view, remembered in the visitor's browser.

## ADDED Requirements

### Requirement: Accessibility dropdown in the top bar
The top bar of every public page SHALL show an accessibility button that opens a dropdown titled "Для людей з порушенням зору" (in the active interface language) with six items in this order: increase text size, decrease text size, grayscale, high contrast, negative contrast, reset. On screens 768 px wide and wider the button SHALL show the text "Версія для слабозорих" (in the active interface language) next to its icon; on narrower screens it SHALL show only the icon and keep the same accessible name. The button SHALL expose whether the dropdown is open to assistive technology. Activating an item SHALL NOT navigate or scroll the page and SHALL NOT close the dropdown, so several items can be used in a row. The dropdown SHALL close with Escape (returning focus to the button), with a click outside it and when focus leaves it. The dropdown SHALL stay inside the viewport on a 360 px wide screen. The text, labels and the title SHALL be available in Ukrainian and in English.

#### Scenario: Button text on a desktop
- **WHEN** a visitor opens any public page on a 1440 px wide screen
- **THEN** the top bar shows the accessibility button with the text "Версія для слабозорих" next to its icon

#### Scenario: Icon only on a phone
- **WHEN** a visitor opens any public page on a 375 px wide screen
- **THEN** the accessibility button shows only its icon, and its accessible name is still "Версія для слабозорих"

#### Scenario: Opening the dropdown
- **WHEN** a visitor activates the accessibility button
- **THEN** the dropdown with the six items is shown and the button reports that it is expanded

#### Scenario: Several items in a row
- **WHEN** a visitor activates "Збільшити розмір тексту" in the open dropdown
- **THEN** the text becomes larger, the page does not scroll or navigate, and the dropdown stays open

#### Scenario: Closing with Escape
- **WHEN** the dropdown is open and the visitor presses Escape
- **THEN** the dropdown closes and the focus returns to the accessibility button

#### Scenario: Closing by a click outside
- **WHEN** the dropdown is open and the visitor clicks on the page outside it
- **THEN** the dropdown closes

#### Scenario: Dropdown fits a narrow screen
- **WHEN** the dropdown is open on a 360 px wide screen
- **THEN** no part of it is outside the viewport and no horizontal page scrolling appears

### Requirement: Text size steps
The visitor SHALL be able to change the size of all text of the public page in four steps, 16 px, 18 px, 20 px and 22 px, where 16 px is the default. "Збільшити розмір тексту" SHALL move one step up and "Зменшити розмір тексту" one step down. At the smallest step the decrease item SHALL be disabled and at the largest step the increase item SHALL be disabled; a disabled item SHALL be marked as disabled for assistive technology and SHALL NOT change the size. Every font size of the theme SHALL scale with the step, so that all text, including the small text of the footer, cards and the menu, changes size proportionally; at the default step the page SHALL look as before this capability. The main navigation SHALL keep fitting its row (with the "Ще" item) after the size changes, with no horizontal page scrolling.

#### Scenario: Increase by one step
- **WHEN** the text size is at the default step and the visitor activates "Збільшити розмір тексту"
- **THEN** the text size becomes 18 px, and the "Зменшити розмір тексту" item becomes enabled

#### Scenario: Smallest step
- **WHEN** the text size is at the default step
- **THEN** the "Зменшити розмір тексту" item is disabled and "Збільшити розмір тексту" is enabled

#### Scenario: Largest step
- **WHEN** the visitor has increased the text size to 22 px
- **THEN** the "Збільшити розмір тексту" item is disabled, and activating it changes nothing

#### Scenario: Small text scales too
- **WHEN** the text size is 22 px
- **THEN** the text of the footer, the news cards and the main navigation is larger than at 16 px, in the same proportion as the body text

#### Scenario: Navigation still fits
- **WHEN** the text size is 22 px on a 1440 px wide screen
- **THEN** the main navigation stays in one row, items that do not fit are listed under "Ще", and the page has no horizontal scrollbar

#### Scenario: Default look unchanged
- **WHEN** no accessibility option is chosen
- **THEN** the page looks as it did before this capability

### Requirement: Colour modes
The visitor SHALL be able to choose one of three colour modes: grayscale ("Відтінки сірого"), high contrast ("Високий контраст") and negative contrast ("Негативний контраст"). Only one mode SHALL be active at a time; choosing another replaces the active one, and the item of the active mode SHALL be disabled and marked as the current one. In every mode every image and every video poster of the page SHALL be shown in grayscale. Grayscale SHALL replace the brand colours with grays and keep the light page. High contrast SHALL show a dark page with light text and one blue accent colour. Negative contrast SHALL show a dark page with light text and one yellow accent colour. In the two contrast modes text and the background behind it SHALL have a contrast ratio of at least 7:1, and links, buttons, form fields and the focused element SHALL stay distinguishable. The decorative backgrounds of the theme (ornaments, patterns, the hero background) SHALL not bring colour into a mode and SHALL NOT make text hard to read. The mode SHALL apply to every public page of the theme.

#### Scenario: Grayscale mode
- **WHEN** the visitor activates "Відтінки сірого"
- **THEN** the brand colours of the page become grays, the page stays light, every image and video poster is shown in grayscale, and the "Відтінки сірого" item is disabled

#### Scenario: High contrast mode
- **WHEN** the visitor activates "Високий контраст"
- **THEN** the page becomes dark with light text and a blue accent, and every image and video poster is shown in grayscale

#### Scenario: Negative contrast mode
- **WHEN** the visitor activates "Негативний контраст"
- **THEN** the page becomes dark with light text and a yellow accent, and every image and video poster is shown in grayscale

#### Scenario: Switching between modes
- **WHEN** the "Високий контраст" mode is active and the visitor activates "Негативний контраст"
- **THEN** only the negative contrast mode is active, and the "Високий контраст" item is enabled again

#### Scenario: Text stays readable
- **WHEN** a contrast mode is active and a visitor opens the home page, a news page and the footer
- **THEN** all text is readable against its background and no text is dark on a dark background

#### Scenario: Modes work with any text size
- **WHEN** the text size is 20 px and the visitor activates a colour mode
- **THEN** both the larger text and the colour mode apply together

### Requirement: Reset
The "Скинути" item SHALL remove the active colour mode and return the text size to the default step in one action, so the page looks as it does for a visitor who has never used the panel, and SHALL remove the stored choice. The dropdown SHALL stay open after it.

#### Scenario: Reset restores the default view
- **WHEN** the text size is 20 px, a colour mode is active and the visitor activates "Скинути"
- **THEN** the text size is 16 px, no colour mode is active, the "Зменшити розмір тексту" item is disabled and every colour mode item is enabled

#### Scenario: Reset forgets the choice
- **WHEN** the visitor activates "Скинути" and reloads the page
- **THEN** the page is shown in the default view

### Requirement: The view is remembered in the browser
The chosen text size and colour mode SHALL be stored in the visitor's browser only, SHALL be applied on every public page of the theme and on later visits in the same browser, and SHALL NOT be sent to the server. The saved view SHALL be applied before the page is first painted, so the page is never shown in the default view first. A stored value that is not one of the allowed steps or modes SHALL be ignored and the default view used. If the browser does not allow storing, the options SHALL still work for the current page and the page SHALL not report an error.

#### Scenario: Choice survives navigation
- **WHEN** the visitor chooses 20 px and "Високий контраст" and opens another page of the site
- **THEN** the other page is shown with 20 px text in the high contrast mode

#### Scenario: Choice survives a new visit
- **WHEN** the visitor chooses a mode, closes the browser tab and opens the site again in the same browser
- **THEN** the page is shown in the chosen mode from the first paint

#### Scenario: No flash of the default view
- **WHEN** a page is loaded while a colour mode is stored
- **THEN** the page is never painted in the default colours before the mode is applied

#### Scenario: Invalid stored value
- **WHEN** the stored text size or mode is not an allowed value
- **THEN** the page is shown in the default view and the stored value does not cause an error

#### Scenario: Storing is blocked
- **WHEN** the browser does not allow the site to store data and the visitor activates "Збільшити розмір тексту"
- **THEN** the text becomes larger on the current page and no error is shown

### Requirement: Accessibility of the panel itself
The accessibility button and the dropdown SHALL be operable with the keyboard alone: the button SHALL be reachable with Tab and opened with Enter or Space, the items SHALL be reachable in a logical order with Tab and activated with Enter or Space, and a visible focus indicator SHALL be shown on each of them in every mode. The active colour mode item SHALL be marked as current for assistive technology. The panel SHALL stay readable and usable in every colour mode and at every text size. Without JavaScript the accessibility button SHALL NOT be shown as a working control that does nothing: the page SHALL remain readable and the rest of the frame SHALL work as before.

#### Scenario: Keyboard use
- **WHEN** a visitor focuses the accessibility button with Tab, opens it with Enter, and uses Tab and Enter on "Високий контраст"
- **THEN** the high contrast mode is applied and the focus stays visible on the item

#### Scenario: Current mode is announced
- **WHEN** a colour mode is active
- **THEN** its item is marked as the current one for assistive technology

#### Scenario: Panel in a contrast mode
- **WHEN** the negative contrast mode is active and the dropdown is open
- **THEN** the dropdown text, its icons and its focus indicator are readable against its background

#### Scenario: Scripts disabled
- **WHEN** the home page is opened with scripts disabled
- **THEN** the page is fully readable in the default view and the accessibility button is not offered as a working control
