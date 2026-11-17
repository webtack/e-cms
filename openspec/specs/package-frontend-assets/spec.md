# package-frontend-assets Specification

## Purpose

Defines how the admin panel's frontend is delivered by the package to a host application so the host needs no Node tooling and is not affected by the CMS's frontend build.

## Requirements

### Requirement: Pre-built admin assets
The package SHALL include a ready-to-use build of the admin frontend. Installing and running the admin SHALL NOT require Node, npm or a frontend build in the host.

#### Scenario: Host without Node
- **WHEN** the package is installed in a host that has no `node_modules` and the assets are published
- **THEN** `/admin/dashboard` renders with working scripts and styles

### Requirement: Publishing assets
The package SHALL publish the admin build to `public/vendor/ecms/` and the built assets of the shipped `default` theme to `public/themes/default/`, through one publish tag. Publishing SHALL be repeatable and SHALL overwrite older package assets when forced.

#### Scenario: Update after upgrading the package
- **WHEN** the host upgrades the package and re-publishes the assets with force
- **THEN** the files under `public/vendor/ecms/` match the package's current build

### Requirement: Isolation from the host's frontend
The admin SHALL use its own root view and its own asset build location. The host's views, `public/build`, Vite configuration and Vite entry points SHALL NOT be read, changed or required by the admin, and the CMS SHALL NOT change how the host's own Vite assets are rendered.

#### Scenario: Host with its own Vite
- **WHEN** the host renders its own pages with `@vite` while the CMS is installed
- **THEN** the host pages load the host's assets exactly as before

### Requirement: Asset versioning
The admin SHALL report an asset version derived from the package's published manifest, so a browser reloads the page when the bundle changes and not when only the host's assets change.

#### Scenario: Bundle updated
- **WHEN** the published manifest changes
- **THEN** the next Inertia request reports a different version and the client reloads

### Requirement: Missing or stale assets are explained
When the published admin build is missing, the admin SHALL show a readable message that names the publish command, instead of a blank page or a server error.

#### Scenario: Assets not published
- **WHEN** `/admin/dashboard` is opened and `public/vendor/ecms/build/manifest.json` does not exist
- **THEN** the response contains a message with the command that publishes the assets

### Requirement: Committed build matches the sources
A released package SHALL contain a build that was produced from its current frontend sources. The package SHALL offer a check that fails when the committed build differs from a fresh build.

#### Scenario: Stale build detected
- **WHEN** a frontend source changes and the build is not regenerated
- **THEN** the freshness check fails

### Requirement: Development server
A developer SHALL be able to run the frontend dev server from the package while a host serves the pages, with hot reload of the admin.

#### Scenario: Demo with hot reload
- **WHEN** the dev server runs with its hot file in the host's `public/vendor/ecms/`
- **THEN** the admin pages of the host load their assets from the dev server

### Requirement: Reference theme can be published
The package SHALL let the host publish the complete shipped theme `default` (templates, asset sources, hooks and metadata) unchanged into the host's themes directory, and SHALL refresh that copy when it is published again with force.

#### Scenario: Publish the reference theme
- **WHEN** the host publishes the reference theme tag
- **THEN** `themes/default` of the host contains `views/`, `assets/`, `hooks.php` and `theme.json` as in the package

#### Scenario: Refresh after a package update
- **WHEN** the package is updated and the tag is published again with force
- **THEN** the host's `themes/default` matches the package's current theme

### Requirement: Theme build tooling can be published
The package SHALL provide a working Vite configuration and runner script for building and developing a theme, publishable to the host root, and SHALL NOT edit the host's `package.json`.

#### Scenario: Build a theme in the host
- **WHEN** the host publishes the tooling tag, installs the documented dependencies and runs the build for a theme
- **THEN** `public/themes/<name>/manifest.json` and its assets are produced

### Requirement: Static files of the package theme
The package SHALL publish the static files (fonts, images) of the shipped `default` theme to `public/app/default/` when it has any, and publishing SHALL succeed when it has none.

#### Scenario: Theme with static files
- **WHEN** the shipped `default` theme has an image under `assets/images/` and the assets are published
- **THEN** the image is available under `public/app/default/images/`

#### Scenario: Theme without static files
- **WHEN** the shipped `default` theme has no static files and the assets are published
- **THEN** publishing completes without an error
