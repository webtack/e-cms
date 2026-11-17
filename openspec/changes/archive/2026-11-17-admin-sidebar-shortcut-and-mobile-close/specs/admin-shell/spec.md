## ADDED Requirements

### Requirement: No keyboard shortcut for the sidebar
The sidebar SHALL NOT be opened, closed, expanded or collapsed by any keyboard shortcut. Key combinations such as Ctrl/Cmd+B SHALL NOT be intercepted by the shell, so they remain available to the page content (for example, bold text in the rich text editor).

#### Scenario: Bold shortcut in the editor
- **WHEN** the user presses Ctrl/Cmd+B while editing a rich text field
- **THEN** the bold formatting is applied and the sidebar state does not change

#### Scenario: Shortcut outside the editor
- **WHEN** the user presses Ctrl/Cmd+B on any admin page
- **THEN** the sidebar state does not change

## MODIFIED Requirements

### Requirement: Mobile navigation
On narrow screens the sidebar SHALL be hidden by default and SHALL open as a slide-out panel (drawer) by the hamburger button in the header. The drawer SHALL contain a visible close button, and SHALL close automatically after every navigation to another admin page.

#### Scenario: Opening the drawer
- **WHEN** on a mobile screen the user clicks the hamburger button
- **THEN** the navigation panel with the same items opens

#### Scenario: Closing the drawer with the button
- **WHEN** the drawer is open on a mobile screen and the user clicks its close button
- **THEN** the drawer closes and the current page stays the same

#### Scenario: Closing the drawer after navigation
- **WHEN** the drawer is open on a mobile screen and the user clicks a link in it
- **THEN** the admin page changes and the drawer is closed
