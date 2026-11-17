# Spec Delta

## MODIFIED Requirements

### Requirement: Service paths have priority
The public routes SHALL NOT override the service ones: `/admin/**`, `/login`, `/logout`, `/locale`, `/email/**`, `/up`, `/storage/**`, `/build/**` SHALL work as before, and not be treated as the slug of a page or a type. The public routes SHALL be registered after every other route of the application, including routes added by the host, so a host route always wins over a CMS page of the same address.

#### Scenario: Login and the admin panel
- **WHEN** a guest opens `/login`, and an authenticated user opens `/admin/dashboard`
- **THEN** the login and admin pages respond, not the site's 404

#### Scenario: A service path is not taken as a page
- **WHEN** the DB (bypassing validation) has a page with the slug `login`
- **THEN** `/login` remains the login page

#### Scenario: Host route wins
- **WHEN** the host application defines `GET /contact` and a CMS page has the slug `contact`
- **THEN** `/contact` is answered by the host route

### Requirement: 404 page
For any public path that does not correspond to a node or a type, and also for invisible nodes, the system SHALL serve the template `errors/404` inside the site shell with status 404, when the public site is enabled (`ecms.public_site`). When the public site is disabled, the standard Laravel 404 SHALL be used. Other HTTP errors of the admin panel do not change.

#### Scenario: Unknown path
- **WHEN** a guest opens `/no/such/page/at/all`
- **THEN** the response has status 404 and the content of the template `errors/404` in `layout`

#### Scenario: Error in the admin panel
- **WHEN** an authenticated user opens a non-existent `/admin/unknown`
- **THEN** the behavior has not changed relative to the previous one (the standard response, not the site template)

#### Scenario: Public site disabled
- **WHEN** `ecms.public_site` is false and an unknown path is opened
- **THEN** the standard Laravel 404 is returned
