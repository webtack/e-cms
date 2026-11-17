## MODIFIED Requirements

### Requirement: Theme static files
A theme MAY keep static files (fonts, images) in `themes/<name>/assets/fonts/` and `themes/<name>/assets/images/`. Building the theme (and starting its dev server) SHALL copy these directories, with their structure, into the theme's static directory `public/app/<name>/`, so templates and the theme's CSS reference the files as `/app/<name>/fonts/<file>` and `/app/<name>/images/<file>`. The files SHALL still be there after a rebuild of the theme. Copying SHALL NOT touch anything outside `public/app/<name>/`. A theme without these directories SHALL build as before. While the theme's dev server runs, a request to the dev server for `/app/<name>/…` SHALL return the same file as the application does, so URLs in the theme's CSS resolve whether the stylesheet is loaded from the application or from the dev server.

#### Scenario: Files are copied on build
- **WHEN** `themes/dcculture/assets/images/logo1.png` and `themes/dcculture/assets/fonts/nunito.woff2` exist and the theme is built
- **THEN** `public/app/dcculture/images/logo1.png` and `public/app/dcculture/fonts/nunito.woff2` exist and are served at `/app/dcculture/images/logo1.png` and `/app/dcculture/fonts/nunito.woff2`

#### Scenario: Rebuild keeps the files
- **WHEN** the theme is built a second time
- **THEN** the copied files exist again after the build, and the built CSS is still available

#### Scenario: Theme without static files
- **WHEN** a theme has neither `assets/fonts/` nor `assets/images/` and is built
- **THEN** the build succeeds and no `public/app/<name>/` directory is created

#### Scenario: Static files through the dev server
- **WHEN** the theme's dev server runs and a stylesheet loaded from it contains `url(/app/dcculture/images/bg_symphony.png)`
- **THEN** the browser's request to the dev server for `/app/dcculture/images/bg_symphony.png` returns the image, the same as the application's `/app/dcculture/images/bg_symphony.png`

#### Scenario: Other dev server requests unaffected
- **WHEN** the theme's dev server runs and the browser requests a theme entry or `/@vite/client` from it
- **THEN** the dev server answers them itself and does not forward them to the application
