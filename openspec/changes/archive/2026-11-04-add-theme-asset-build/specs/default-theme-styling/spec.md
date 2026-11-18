# Spec Delta

## MODIFIED Requirements

### Requirement: Стилі теми за замовчуванням підключаються через збірку ресурсів
The theme `default` SHALL have a stylesheet in `assets/app.css`, which is connected by the template `html` through the build of the resources of the theme. The stylesheet of `default` SHALL remain ordinary CSS (without a preprocessor), because it is built by Tailwind. The pages of the public site with the theme `default` SHALL be served with this stylesheet, while the page of the admin SHALL NOT receive the styles of the public theme.

#### Scenario: The public page has the styles of the theme
- **WHEN** a guest opens the home page with `APP_THEME=default` and the built resources of the theme
- **THEN** the built stylesheet `app` of the theme `default` is connected in `<head>`, and the header, the main zone and the footer have their own indents and font, not the browser default values

#### Scenario: The admin does not change its look
- **WHEN** the administrator opens any page of `/admin`
- **THEN** the styles of the default theme are not applied, and the look of the admin is the same as before this change
