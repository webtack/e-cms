## MODIFIED Requirements

### Requirement: Set of site settings
The system SHALL store the general site settings in the application settings store under keys with the prefix `site.`: `site.name`, `site.short_name`, `site.description`, `site.logo_large`, `site.logo_small`, `site.emails`, `site.phones`, `site.address`, `site.socials`, `site.schedule`, and `site.indexable`. All settings except `site.indexable` SHALL be optional.

#### Scenario: Fresh installation
- **WHEN** no site setting has been saved yet
- **THEN** reading returns empty values for all settings, and indexing is disabled

#### Scenario: Partial filling
- **WHEN** only the site name has been saved
- **THEN** the rest of the settings is read as empty, without errors

### Requirement: Translatable values with the key default
The system SHALL store the translatable settings (`site.name`, `site.short_name`, `site.description`, `site.address`, `site.schedule`, `site.logo_large`, `site.logo_small`, as well as the labels `label` in `site.emails`, `site.phones`, and `site.socials`) as an object where the key is the language code, and the base language has the key `default`. Values shared by all languages (the `value` of emails and phones, `network` and `url` of social networks, `site.indexable`) SHALL be stored without a language key. An empty label SHALL be stored as an absent `label` key, not as an empty string.

#### Scenario: Saving the name
- **WHEN** the site name "My site" is saved
- **THEN** `site.name` contains `{ "default": "My site" }`

#### Scenario: Saving the address
- **WHEN** the address "1 Main St\nKyiv" is saved
- **THEN** `site.address` contains `{ "default": "1 Main St\nKyiv" }`, line break included

#### Scenario: Empty address
- **WHEN** the address is cleared
- **THEN** `site.address` does not contain a value of the language `default` and is read as empty

#### Scenario: Empty name
- **WHEN** the site name is cleared
- **THEN** the setting `site.name` does not contain a value of the language `default` and is read as empty

#### Scenario: Empty label
- **WHEN** an email is saved with an empty label
- **THEN** the email record contains only `value`, without the `label` key

#### Scenario: Shared values
- **WHEN** a social network with the network `facebook` and a link is saved
- **THEN** `network` and `url` are stored as ordinary strings without a language key
