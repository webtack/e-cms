# Spec Delta

## Purpose

Describes how the package registers itself in a Laravel host application without manual edits to the host's bootstrap, routing or provider files.

## ADDED Requirements

### Requirement: Automatic registration
The package SHALL register itself in the host through Laravel package discovery. After installation the host SHALL NOT need to edit its `bootstrap/app.php`, `routes/web.php`, service providers or middleware lists for the CMS to work.

#### Scenario: Fresh host
- **WHEN** the package is installed in a Laravel application that has no CMS code
- **THEN** the admin routes, services, Gate abilities and Blade directives of the CMS are available

### Requirement: Middleware of the web group
The package SHALL append the middleware that blocks deactivated users, sets the request locale, shares the Inertia data and ends the other sessions of a user whose password changed (`AuthenticateSession`) to the host's `web` middleware group, once, regardless of how many times the application is bootstrapped.

#### Scenario: Deactivated user
- **WHEN** a signed-in user is deactivated and makes another web request
- **THEN** the session ends and the user is sent to the login page

#### Scenario: Password changed
- **WHEN** a user changes the password in one session
- **THEN** every other session and remember-me login of that user ends, and the current session stays signed in

### Requirement: Service routes
The package SHALL register the service routes (interface language, login, logout, email verification notice, the admin panel under `/admin`, the image-style route and the rate-limited upload, lookup and YouTube endpoints) with the middleware they had in the application. The admin prefix SHALL be `admin`.

#### Scenario: Guest opens the admin
- **WHEN** a guest requests `/admin/dashboard`
- **THEN** the response redirects to `/login`

#### Scenario: Signed-in user opens the login page
- **WHEN** a signed-in user requests `/login`
- **THEN** the response redirects to `/admin/dashboard`

### Requirement: Optional authentication routes
When the setting `ecms.auth_routes` is false, the package SHALL NOT register `/login`, `/logout` and the email verification notice, so a host can provide its own authentication.

#### Scenario: Host with its own login
- **WHEN** `ecms.auth_routes` is false
- **THEN** the package registers no route named `login` and the admin continues to require an authenticated user

### Requirement: Optional public site
When `ecms.public_site` is true (the default), the package SHALL register the public routes `/` and `/{path}`, and SHALL register them after all other routes, including the host's. When it is false, the package SHALL register neither route and SHALL NOT replace the 404 page.

#### Scenario: Host route wins over the catch-all
- **WHEN** the host defines `GET /contact` and the public site is on
- **THEN** a request to `/contact` reaches the host's route, not the CMS page lookup

#### Scenario: Public site off
- **WHEN** `ecms.public_site` is false and an unknown URL is requested
- **THEN** the standard Laravel 404 is returned

### Requirement: Public 404
When the public site is on, a GET request that matches no route SHALL render the active theme's 404 page; other verbs, JSON requests and the admin keep the standard response.

#### Scenario: Unknown path
- **WHEN** `GET /no/such/page` matches no node
- **THEN** the theme's 404 template is rendered with status 404

#### Scenario: Wrong verb
- **WHEN** `GET /logout` is requested (the route accepts only POST)
- **THEN** the response is 405, not the theme's 404

### Requirement: Reserved paths
The reserved public paths SHALL keep covering `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`, `app`, `themes`, `images`, `search`, `sitemap`, `sitemap.xml` and SHALL also cover the directory the package assets are published to.

#### Scenario: Reserved first segment
- **WHEN** a node is given the slug `admin`
- **THEN** it is rejected as reserved

#### Scenario: Language code taken by a host route
- **WHEN** the host has a route whose first segment is `en` and an administrator adds the content language `en`
- **THEN** the language is refused as conflicting with the host's route

### Requirement: Language prefix of the public site
The package SHALL resolve the language prefix of public addresses before routing only when the public site is enabled, SHALL NOT query its own tables for it while they do not exist, and SHALL treat language codes as reserved first segments of the application, refusing a language whose code is the first segment of a host route.

#### Scenario: Prefixed public address
- **WHEN** the public site is on, the language `en` is active and a visitor requests `/en/about-us`
- **THEN** the page is served as `/about-us` in English

#### Scenario: Host not migrated yet
- **WHEN** the package tables do not exist and the host receives a request such as `/api/x`
- **THEN** the host answers it and no database error occurs

#### Scenario: Public site off
- **WHEN** `public_site` is off
- **THEN** the prefix is not resolved and no request is rewritten

### Requirement: Request limits
The package SHALL register the named rate limiters `search`, `upload`, `lookup` and `youtube` and apply them to the public search, file upload, reference lookup and YouTube lookup routes. The limit per minute SHALL come from `limits.*_per_minute` and SHALL count per user id for a signed-in user and per IP address otherwise.

#### Scenario: Upload limit reached
- **WHEN** a signed-in user exceeds `limits.upload_per_minute` on `POST /admin/files` within a minute
- **THEN** the response is 429 and other users are not affected

#### Scenario: Search limit per IP
- **WHEN** a guest exceeds `limits.search_per_minute` on `/search` from one IP address
- **THEN** the response is 429

### Requirement: Theme and translation lookup
Themes SHALL be looked up first in the application's `themes/` directory and then in the package. The theme `default` SHALL come from the package, and an application theme named `default` SHALL override it. Interface translations SHALL be read from the package, with the application's `lang/` overriding individual keys.

#### Scenario: Application theme overrides a template
- **WHEN** the application theme has `views/layout.blade.php`
- **THEN** that template is used and every other template comes from the package `default`

#### Scenario: Host overrides a translation
- **WHEN** the host's `lang/uk.json` defines a key that the package also defines
- **THEN** the host's value is shown

### Requirement: Interface locales
The package SHALL take the list of admin interface languages from its own configuration key `ecms.locales` (defaulting to English and Ukrainian), SHALL NOT require any key in the host's `config/app.php`, and SHALL decide whether a language has interface translations by looking at both the host's translation files and the package's.

#### Scenario: Stock host configuration
- **WHEN** the host's `config/app.php` has no `available_locales` key and a web request arrives
- **THEN** the request is served and the interface languages are `en` and `uk`

#### Scenario: Host extends the list
- **WHEN** the host sets `ecms.locales` to include `pl` and has `lang/pl.json`
- **THEN** `pl` is offered in the language switcher and accepted by `POST /locale`

#### Scenario: Public language without host files
- **WHEN** the host has no `lang/en.json`, the package ships `en.json` and a visitor opens `/en/...`
- **THEN** the interface locale of that request is `en`

### Requirement: Configuration and publishing
The package SHALL merge its configuration with the host's under unchanged keys (`content.*`, `files.*`, `theme.*`, `social_networks.*`, `limits.*`) and `ecms.*`, so the host overrides only what it sets, and SHALL expose publish tags `ecms-config`, `ecms-lang` and `ecms-views`.

#### Scenario: Partial override
- **WHEN** the host publishes the configuration and changes `theme.active`
- **THEN** all other CMS settings keep their package defaults
