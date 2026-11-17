# admin-i18n Specification

## Purpose

Defines how the admin panel selects, stores, and switches the interface language, and how translations reach the server responses and the React pages.

## Requirements

### Requirement: Available languages
The system SHALL support the interface languages Ukrainian (`uk`) and English (`en`); the list of available languages SHALL be set by the application configuration, and the language `en` SHALL be the fallback when a translation is missing.

#### Scenario: List of languages for the client
- **WHEN** a user opens any page of the admin panel or the login page
- **THEN** the client receives the current language and the list of available languages (`uk`, `en`) with their own names

#### Scenario: Missing translation
- **WHEN** there is no entry for a key in the translations of the current language
- **THEN** the English text of the key is displayed, and no error occurs

### Requirement: Determining the language of a request
The system SHALL determine the language of each web request in the following priority order: the saved language of the authenticated user; the language chosen by a guest (cookie); the best match with the `Accept-Language` header; the value `APP_LOCALE`. Values that are not in the list of available languages SHALL be ignored.

#### Scenario: Language of a saved user
- **WHEN** an authenticated user with `locale = uk` opens an admin page with the header `Accept-Language: en`
- **THEN** the page is displayed in Ukrainian, and the `lang` attribute of the root element equals `uk`

#### Scenario: Guest without a choice
- **WHEN** a guest without a language cookie opens `/login` with the header `Accept-Language: uk-UA,uk;q=0.9`
- **THEN** the login page is displayed in Ukrainian

#### Scenario: Unsupported language
- **WHEN** a guest without a language cookie sends `Accept-Language: de`
- **THEN** the default language from `APP_LOCALE` applies

### Requirement: Switching the language
The system SHALL provide `POST /locale` with the field `locale`, which accepts only available languages. For an authenticated user the choice SHALL be saved in their profile (`users.locale`); for a guest — in a cookie; in both cases the choice SHALL be kept between requests and sessions.

#### Scenario: An authenticated user chooses a language
- **WHEN** an authenticated user sends `POST /locale` with `locale = uk`
- **THEN** `users.locale` equals `uk`, they are redirected back, and the next pages are displayed in Ukrainian

#### Scenario: A guest chooses a language
- **WHEN** a guest sends `POST /locale` with `locale = uk`
- **THEN** the language cookie is set, and the login page after the redirect is displayed in Ukrainian

#### Scenario: The language is kept after logout
- **WHEN** a user who chose `uk` logs out
- **THEN** the login page is displayed in Ukrainian

#### Scenario: Invalid language
- **WHEN** `POST /locale` is sent with `locale = xx`
- **THEN** the request is rejected with a validation error, and the saved language does not change

### Requirement: Language switcher in the interface
The admin header and the login page SHALL contain a language switcher that shows all available languages by their own names and marks the current one.

#### Scenario: Switching in the admin panel
- **WHEN** an authenticated user selects "Українська" in the header switcher
- **THEN** the menu, the breadcrumbs, the button labels, and the page headings are displayed in Ukrainian without losing the sidebar state

#### Scenario: Switching on the login page
- **WHEN** a guest selects "Українська" on `/login`
- **THEN** the labels of the login form are displayed in Ukrainian

### Requirement: Translations for the React interface
All user-visible strings of the existing admin pages and components (including `aria-label` and tab headings) SHALL be displayed through the translations of the current language. The files `lang/en.json` and `lang/uk.json` SHALL contain the same set of keys, and placeholders of the form `:name` SHALL be substituted with values.

#### Scenario: Translation of the login page
- **WHEN** a guest opens `/login` in Ukrainian
- **THEN** the heading, the field labels, and the button are displayed in Ukrainian translations, not in English

#### Scenario: Placeholder substitution
- **WHEN** a string contains the placeholder `:seconds` and is called with `{ seconds: 30 }`
- **THEN** the result has `30` in place of the placeholder

#### Scenario: Key parity
- **WHEN** the automatic tests are run
- **THEN** the sets of keys of `lang/en.json` and `lang/uk.json` match; otherwise the test fails

### Requirement: Localization of server messages
Messages that the server generates for the admin panel (a login error, the attempt limit, form validation rules) SHALL be displayed in the language of the request.

#### Scenario: Login error in Ukrainian
- **WHEN** a user with the language `uk` submits a wrong password
- **THEN** the error message next to the email field is in Ukrainian
