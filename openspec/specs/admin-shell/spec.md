# admin-shell Specification

## Purpose

Provides the shared admin shell (side navigation, header, theme, notifications) and the start page, on which the next CMS sections will be built.

## Requirements

### Requirement: Admin start page
The system SHALL display the page `/admin/dashboard` in the admin shell for an authenticated user.

#### Scenario: Opening the dashboard
- **WHEN** an authenticated user with a verified email opens `/admin/dashboard`
- **THEN** the response is successful, and the shell (sidebar and header) and the content of the dashboard page are displayed

### Requirement: Shell is kept between navigations
The admin shell SHALL remain mounted when navigating between admin pages, without a full page reload, and the state of the sidebar (expanded/collapsed) SHALL be kept during such navigations.

#### Scenario: Navigation between pages
- **WHEN** the user collapses the sidebar and navigates to another admin page through a link
- **THEN** the sidebar remains collapsed, and no full page reload occurs

### Requirement: Side navigation
The shell SHALL contain a side panel with the Dashboard item, the "Favorites" block (the user's personal types), the "Navigation" block (the admin menu: links to types and groups configured by the administrator), the Media Library, and two sections at the root: "Structure" (Content Types, Page Types, Block Types, Taxonomy, Menus) and "Access" (Users, Roles, Site), and SHALL highlight the item that matches the current address. There SHALL be no Pages item and no collapsible Settings item. Each item of the sections SHALL be shown by the same right as before (types — by the `types` right or the level `own`/`any` on a type of their kind; Taxonomy, Menus, Users, Roles, Site — by the corresponding system rights); a section without visible items SHALL be hidden. At the bottom of the sidebar there SHALL be a link to the admin menu settings (`/admin/settings/navigation`), visible to users with the `settings` right. The spacing between groups SHALL be compact. The item titles SHALL be displayed in the interface language of the current user. Content Types SHALL open the page of the list of content types, Page Types — of page types, Block Types — of block types, Taxonomy — the list of vocabularies, Menus — the list of menus, Users — users, Roles — roles, Site — the site settings page `/admin/settings`, and Media Library — the media library. The "Favorites" block and the admin menu are described in the requirements of `admin-favorites` and `admin-navigation-menu`.

#### Scenario: Highlighting the active item
- **WHEN** the user is on `/admin/dashboard`
- **THEN** the Dashboard item is marked as active, and the other items are not

#### Scenario: Section item is active
- **WHEN** the user opens `/admin/taxonomy`
- **THEN** the Taxonomy item of the "Structure" section is highlighted, and the breadcrumbs show its name

#### Scenario: Working item Content Types
- **WHEN** the user navigates through the Content Types sub-item
- **THEN** `/admin/content-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Page Types
- **WHEN** the user navigates through the Page Types sub-item
- **THEN** `/admin/page-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Block Types
- **WHEN** the user navigates through the Block Types sub-item
- **THEN** `/admin/block-types` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Taxonomy
- **WHEN** the user navigates through the Taxonomy sub-item
- **THEN** `/admin/taxonomy` opens, the item is marked as active (also on the term pages and the section forms), and the breadcrumbs show its name

#### Scenario: Working item Menus
- **WHEN** the user navigates through the Menus sub-item
- **THEN** `/admin/menus` opens, the item is marked as active (also on the menu item pages and the section forms), and the breadcrumbs show its name

#### Scenario: Working sub-items Users and Roles
- **WHEN** a user with the corresponding right navigates through the Users or Roles sub-item
- **THEN** `/admin/users` or `/admin/roles` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Media Library
- **WHEN** the user navigates through the Media Library item
- **THEN** `/admin/media` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Working item Settings
- **WHEN** a user with the right to site settings navigates through the Site item of the "Access" section
- **THEN** `/admin/settings` opens, the item is marked as active, and the breadcrumbs show its name

#### Scenario: Items by rights
- **WHEN** the user has only the `taxonomy` right
- **THEN** the sidebar contains the "Structure" section with one item Taxonomy, and there is no "Access" section

#### Scenario: Sections without available items
- **WHEN** the user has no right that opens the items of the sections
- **THEN** neither the "Structure" nor the "Access" section is displayed in the sidebar

#### Scenario: Placeholder items
- **WHEN** the user opens the admin panel
- **THEN** there is no Pages item in the sidebar

#### Scenario: Link to the menu settings
- **WHEN** a user with the `settings` right looks at the bottom of the sidebar
- **THEN** there is a link to `/admin/settings/navigation`; without the `settings` right it is absent

#### Scenario: Item titles in Ukrainian
- **WHEN** a user with the language `uk` opens `/admin/dashboard`
- **THEN** the sidebar item titles are displayed in Ukrainian translations, and the highlighting of the active item does not change

### Requirement: Collapsing the sidebar on desktop
On wide screens the sidebar SHALL have two states: expanded (icons and labels) and collapsed to icons, and the user SHALL be able to switch between them.

#### Scenario: Collapsing to icons
- **WHEN** on a desktop the user clicks the sidebar switch in the expanded state
- **THEN** the sidebar shows only icons, and the page content takes the freed space

### Requirement: No keyboard shortcut for the sidebar
The sidebar SHALL NOT be opened, closed, expanded or collapsed by any keyboard shortcut. Key combinations such as Ctrl/Cmd+B SHALL NOT be intercepted by the shell, so they remain available to the page content (for example, bold text in the rich text editor).

#### Scenario: Bold shortcut in the editor
- **WHEN** the user presses Ctrl/Cmd+B while editing a rich text field
- **THEN** the bold formatting is applied and the sidebar state does not change

#### Scenario: Shortcut outside the editor
- **WHEN** the user presses Ctrl/Cmd+B on any admin page
- **THEN** the sidebar state does not change

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

### Requirement: Breadcrumbs
The header SHALL display breadcrumbs that correspond to the current admin page.

#### Scenario: Breadcrumbs on the dashboard
- **WHEN** the user opens `/admin/dashboard`
- **THEN** the header shows a path that ends with the name of the Dashboard page

### Requirement: Theme switch
The header SHALL provide switching between the light and the dark theme; the chosen option SHALL be kept between visits, and without a choice SHALL match the system theme.

#### Scenario: Switching to the dark theme
- **WHEN** the user enables the dark theme
- **THEN** the interface is displayed in the dark theme, and after reloading the page the theme is kept

#### Scenario: Initial theme
- **WHEN** the user opens the admin panel for the first time without a saved choice
- **THEN** the system theme of the browser applies

### Requirement: User menu
The header SHALL contain the user menu with the name (and an avatar or initials), a link to the profile, and logout actions.

#### Scenario: Contents of the menu
- **WHEN** the user opens the user menu
- **THEN** they see their name, the link to the profile, and the logout action

#### Scenario: Navigation to the profile
- **WHEN** the user selects the link to the profile
- **THEN** the profile page opens in the admin shell

### Requirement: Notifications about the result of actions
The shell SHALL show toast notifications for the flash messages `success` and `error` passed by the server after a user action. Every new flash message SHALL produce a toast, including a message whose text equals the previous one. Toasts SHALL appear in the top-right corner of the viewport and MAY overlap the header. Each toast SHALL be visually distinguished by its status (success, error, warning, info) with a status color in both the light and the dark theme, SHALL be sized for easy reading, and SHALL have a close button. A success toast SHALL stay visible for 4 seconds and an error toast for 8 seconds unless the user closes it earlier.

#### Scenario: Success message
- **WHEN** the server returns the next page with the flash message `success`
- **THEN** the user sees a success-styled toast with this text in the top-right corner

#### Scenario: Error message
- **WHEN** the server returns the next page with the flash message `error`
- **THEN** the user sees an error-styled toast with this text in the top-right corner

#### Scenario: Message is not repeated
- **WHEN** the user navigates to another page after a toast was shown
- **THEN** the same toast is not shown again

#### Scenario: Same message twice in a row
- **WHEN** the user stays on the same page and two consecutive responses carry a flash message with the same text, for example saving an edit form twice
- **THEN** the user sees a toast for each response

#### Scenario: Toast does not cover form actions
- **WHEN** a toast is shown after saving an edit form
- **THEN** the Save button at the bottom of the form is not covered by the toast

#### Scenario: Toast is closed manually
- **WHEN** the user selects the close button of a toast
- **THEN** the toast disappears before its duration ends

#### Scenario: Toast duration depends on status
- **WHEN** a success toast and an error toast are shown
- **THEN** the success toast disappears after about 4 seconds and the error toast after about 8 seconds

### Requirement: Shared data for pages
Each admin page SHALL have access to the data of the current user (id, name, email) and to flash messages; sensitive user fields (password, tokens) SHALL NOT be passed to the client.

#### Scenario: User data on a page
- **WHEN** an authenticated user opens any admin page
- **THEN** the shell displays their name, and the response does not contain the password hash or `remember_token`

### Requirement: Sticky form action bar
Each create and edit form in the admin panel (a content type, a type field, a node) SHALL have an action bar that stays pinned to the bottom edge of the window when scrolling, at all screen sizes. The bar SHALL contain the "Save" button and a "Cancel" link that returns to the list without changes; the buttons SHALL NOT be placed in the flow of the form. The bar SHALL have an opaque background and a top border, take into account the safe bottom area of the device, and not overlap the last fields of the form (the end of the form can be scrolled above the bar). There SHALL be a small margin between the content of the form and the bar, so that the form does not stick to the bar. During submission the "Save" button SHALL be disabled.

#### Scenario: Long form
- **WHEN** the user opens a form with long content and does not scroll it to the end
- **THEN** the "Cancel" and "Save" buttons are visible at the bottom of the window

#### Scenario: Cancel
- **WHEN** the user clicks "Cancel" in the bar
- **THEN** they return to the list without saved changes

#### Scenario: Saving from the bar
- **WHEN** the user clicks "Save" in the bar
- **THEN** the form is submitted as before

#### Scenario: All admin forms
- **WHEN** the user opens the form for creating a type, a field, or a node
- **THEN** each has a sticky action bar

#### Scenario: The end of the form is not covered
- **WHEN** the user scrolls the form to the end
- **THEN** the last field is fully visible above the bar

#### Scenario: Margin from the bar
- **WHEN** the user scrolls the form to the end
- **THEN** there is a visible gap between the last element of the form and the action bar

### Requirement: Frame of the forms of a type and of a field
The content of the create and edit forms of a content type and of a type field SHALL be in a container with a rounded frame (`rounded-md border`). The action bar SHALL remain outside the frame, full width.

#### Scenario: Type form in a frame
- **WHEN** the user opens the create or edit form of a content type
- **THEN** the form fields are surrounded by a rounded frame, and the action bar is outside it

#### Scenario: Field form in a frame
- **WHEN** the user opens the create or edit form of a type field
- **THEN** the form fields are surrounded by a rounded frame, and the action bar is outside it

### Requirement: Sidebar by rights
The sidebar SHALL show only those sections to which the current user's role has access, and SHALL contain the items "Users" and "Roles" for users with the corresponding rights. The content type items are shown if there is a level `own` or `any` on at least one type of the corresponding kind.

#### Scenario: Role without access to the menu
- **WHEN** a user without the right to the menu opens the admin panel
- **THEN** there is no "Menus" item in the sidebar

#### Scenario: Administrator
- **WHEN** a user of the role `admin` opens the admin panel
- **THEN** the sidebar contains all items, including "Users" and "Roles"

### Requirement: Link to the site in the header
The header of the admin shell SHALL contain an icon button "Open site" with a label for screen readers and a hint, which leads to the home page of the public site (`/`) and opens in a new browser tab. The button SHALL be available to every admin user on all admin pages, and SHALL NOT reload or close the current admin page.

#### Scenario: Button in the header
- **WHEN** an authenticated user opens any admin page
- **THEN** the header has an icon button "Open site" with the link `/`

#### Scenario: Opening in a new tab
- **WHEN** the user clicks the "Open site" button
- **THEN** the home page of the site opens in a new tab, and the admin panel remains open in the current one

### Requirement: Saving an edit form keeps the user on the page
A successful save of changes in an edit form in the admin panel (a content type, a page type, a block type, a type field, a node, a menu, a menu item, a vocabulary, a term, a role, a user) SHALL keep the user on the same edit page and show a toast with a success message; the form SHALL show the saved values. Creation and deletion are not changed by this. The settings tabs of a type and the site settings SHALL likewise remain on their page.

#### Scenario: Saving changes
- **WHEN** the user changes values in an edit form and clicks "Save"
- **THEN** the page address does not change, a success toast is shown, and the form contains the saved values

#### Scenario: Validation error
- **WHEN** saving is rejected by validation
- **THEN** the user stays on the form, the errors are shown next to the fields, and nothing is saved

#### Scenario: Creation
- **WHEN** the user creates a new object
- **THEN** the behavior after creation does not change

### Requirement: Sidebar branding
The top of the expanded sidebar SHALL show the admin logo image (its white variant in the dark theme), and under it the site name taken from the site settings (`/admin/settings`) in the current interface language. When the site has no name, the application name SHALL be shown instead. When the sidebar is collapsed to icons, this header block SHALL be hidden. The text "E-CMS" SHALL NOT be shown in the sidebar.

#### Scenario: Site name under the logo
- **WHEN** the site name in the settings is "My site" and an authenticated user opens an admin page
- **THEN** the sidebar header shows the logo and, under it, the text "My site"

#### Scenario: Name changed in the settings
- **WHEN** an administrator saves a new site name on `/admin/settings` and the page reloads
- **THEN** the sidebar header shows the new name

#### Scenario: Dark theme
- **WHEN** the dark theme is active
- **THEN** the sidebar header shows the white variant of the logo

#### Scenario: Collapsed sidebar
- **WHEN** the user collapses the sidebar to icons
- **THEN** the logo and the site name are not shown

### Requirement: Avatar in the user menu
The user menu in the header SHALL show the signed-in user's avatar instead of their initials when they have one.

#### Scenario: User with an avatar
- **WHEN** a user who has an avatar opens any admin page
- **THEN** the header user menu shows their picture

#### Scenario: User without an avatar
- **WHEN** a user without an avatar opens any admin page
- **THEN** the header user menu shows their initials
