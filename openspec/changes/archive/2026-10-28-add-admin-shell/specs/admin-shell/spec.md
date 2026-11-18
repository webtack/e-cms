# Spec Delta

## Purpose

Provides the shared admin shell (sidebar navigation, header, theme, notifications) and the start page, on which the next CMS sections will be built.

## ADDED Requirements

### Requirement: Admin start page
The system SHALL display the page `/admin/dashboard` in the admin shell for an authenticated user.

#### Scenario: Opening the dashboard
- **WHEN** an authenticated user with a confirmed email opens `/admin/dashboard`
- **THEN** the response is successful, and the shell (sidebar and header) and the content of the dashboard page are displayed

### Requirement: Shell persistence across navigation
The admin shell SHALL remain mounted when navigating between admin pages, without a full page reload, and the sidebar state (expanded/collapsed) SHALL be kept during such navigation.

#### Scenario: Navigation between pages
- **WHEN** the user collapses the sidebar and navigates to another admin page through a link
- **THEN** the sidebar stays collapsed, and no full page reload happens

### Requirement: Sidebar navigation
The shell SHALL contain a sidebar with the items Dashboard, Content Types, Pages, Media Library and Settings and SHALL highlight the item that matches the current address.

#### Scenario: Highlighting the active item
- **WHEN** the user is on `/admin/dashboard`
- **THEN** the Dashboard item is marked as active, and the other items are not

#### Scenario: Layout items
- **WHEN** the user sees the items Content Types, Pages, Media Library and Settings
- **THEN** they are displayed as a layout and do not open working features of this stage

### Requirement: Collapsing the sidebar on desktop
On wide screens the sidebar SHALL have two states: expanded (icons and labels) and collapsed to icons, and the user SHALL be able to switch between them.

#### Scenario: Collapsing to icons
- **WHEN** on desktop the user clicks the sidebar toggle in the expanded state
- **THEN** the sidebar shows only icons, and the page content takes the freed space

### Requirement: Mobile navigation
On narrow screens the sidebar SHALL be hidden by default and SHALL open as a slide-in panel (drawer) by the hamburger button in the header.

#### Scenario: Opening the drawer
- **WHEN** on a mobile screen the user taps the hamburger button
- **THEN** the navigation panel opens with the same items

### Requirement: Breadcrumbs
The header SHALL display breadcrumbs that correspond to the current admin page.

#### Scenario: Breadcrumbs on the dashboard
- **WHEN** the user opens `/admin/dashboard`
- **THEN** the header shows a path that ends with the name of the Dashboard page

### Requirement: Theme switcher
The header SHALL provide switching between the light and dark themes; the chosen option SHALL be kept between visits, and without a choice it SHALL follow the system theme.

#### Scenario: Switching to the dark theme
- **WHEN** the user enables the dark theme
- **THEN** the interface is displayed in the dark theme, and after a page reload the theme is kept

#### Scenario: Initial theme
- **WHEN** the user opens the admin for the first time without a saved choice
- **THEN** the browser's system theme is applied

### Requirement: User menu
The header SHALL contain a user menu with the name (and an avatar or initials), a link to the profile and logout actions.

#### Scenario: Menu content
- **WHEN** the user opens the user menu
- **THEN** they see their name, a link to the profile and the logout action

#### Scenario: Navigation to the profile
- **WHEN** the user selects the link to the profile
- **THEN** the profile page (a stub) opens in the admin shell

### Requirement: Result notifications
The shell SHALL display toast notifications for the `success` and `error` flash messages passed by the server after a user action.

#### Scenario: Success message
- **WHEN** the server returns the next page with a `success` flash message
- **THEN** the user sees a toast with this text

#### Scenario: Error message
- **WHEN** the server returns the next page with an `error` flash message
- **THEN** the user sees an error toast with this text

#### Scenario: Message is not repeated
- **WHEN** the user navigates to another page after a toast has been shown
- **THEN** the same toast is not shown again

### Requirement: Shared data for pages
Every admin page SHALL have access to the data of the current user (id, name, email) and to the flash messages; sensitive user fields (password, tokens) SHALL NOT be passed to the client.

#### Scenario: User data on a page
- **WHEN** an authenticated user opens any admin page
- **THEN** the shell displays their name, and the response contains no password hash or `remember_token`
