## ADDED Requirements

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
