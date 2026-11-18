# Spec Delta

## Purpose

Defines the set of general settings of the site (the name, logos, contacts, social networks, working hours, indexing), the format of their storage with readiness for languages, and the way the templates read them. It describes the data, not the page of the admin.

## ADDED Requirements

### Requirement: Набір налаштувань сайту
The system SHALL store the general settings of the site in the storage of the application settings under keys with the prefix `site.`: `site.name`, `site.short_name`, `site.description`, `site.logo_large`, `site.logo_small`, `site.emails`, `site.phones`, `site.socials`, `site.schedule` and `site.indexable`. All settings except `site.indexable` SHALL be optional.

#### Scenario: Fresh setup
- **WHEN** no setting of the site has been saved yet
- **THEN** reading returns empty values for all settings, and indexing is disabled

#### Scenario: Partial filling
- **WHEN** only the name of the site is saved
- **THEN** the other settings are read as empty, without errors

### Requirement: Перекладні значення з ключем default
The system SHALL store the translatable settings (`site.name`, `site.short_name`, `site.description`, `site.schedule`, `site.logo_large`, `site.logo_small`, and also the captions `label` in `site.emails`, `site.phones` and `site.socials`) as an object, where the key is the language code, and the base language has the key `default`. The values shared by all languages (`value` of emails and phones, `network` and `url` of social networks, `site.indexable`) SHALL be stored without a language key. An empty caption SHALL be stored as an absent key `label`, not as an empty string.

#### Scenario: Saving the name
- **WHEN** the name of the site «Мій сайт» («My site») is saved
- **THEN** `site.name` contains `{ "default": "Мій сайт" }`

#### Scenario: Empty name
- **WHEN** the name of the site is cleared
- **THEN** the setting `site.name` does not contain the value of the language `default` and is read as empty

#### Scenario: Empty caption
- **WHEN** an email is saved with an empty caption
- **THEN** the record of the email contains only `value`, without the key `label`

#### Scenario: Shared values
- **WHEN** a social network with the network `facebook` and a link is saved
- **THEN** `network` and `url` are stored as ordinary strings without a language key

### Requirement: Читання з фолбеком на default
The system SHALL provide the templates with a reader of the site settings, which returns the translatable values for the requested language, and if there is no value of this language, returns the value of the language `default`. The reader SHALL return the lists (`emails`, `phones`, `socials`, `schedule`) in the saved order, and the caption of an element — in the language of the request with a fallback to `default`.

#### Scenario: Value of the requested language
- **WHEN** `site.name` is saved with the values `default` and `en`, and the reader is asked with the language `en`
- **THEN** the value `en` is returned

#### Scenario: Fallback to default
- **WHEN** only the value `default` is saved, and the reader is asked with the language `en`
- **THEN** the value `default` is returned

#### Scenario: Order of a list
- **WHEN** three phones are saved in a certain order
- **THEN** the reader returns them in the same order

### Requirement: Індексація за замовчуванням вимкнена
The setting `site.indexable` SHALL be a boolean, and in the absence of a saved value it SHALL be read as `false` (the site is not indexed). The setting SHALL be available to the templates through the reader; the output of the indexing attributes itself is not part of this capability.

#### Scenario: Value not set
- **WHEN** `site.indexable` has never been saved
- **THEN** the reader returns `false`

#### Scenario: Indexing enabled
- **WHEN** `site.indexable` is saved with the value `true`
- **THEN** the reader returns `true`

### Requirement: Довідник соцмереж
The system SHALL have a fixed directory of known social networks, which is set in the configuration and is not changed from the admin. A saved social network SHALL refer to a network of the directory by its key; a key that is absent from the directory SHALL be rejected. The same network SHALL be allowed several times.

#### Scenario: Known network
- **WHEN** a social network with a network key that is in the directory is saved
- **THEN** the record is accepted

#### Scenario: Unknown network
- **WHEN** a social network with a key that is absent from the directory is saved
- **THEN** the record is rejected with a validation error of the field of the network

#### Scenario: Two pages of the same network
- **WHEN** two records with the network `instagram` and different links are saved
- **THEN** both records are saved in the set order

### Requirement: Логотипи як файли профілю image
The system SHALL store the large and the small logo as links to a file of the profile `image` (the value `{uuid}.{ext}` under the language key), and SHALL reject a file of another profile or a non-existent file. Each logo SHALL be optional and independent of the other.

#### Scenario: Saving a logo
- **WHEN** an uploaded image is chosen as the large logo
- **THEN** `site.logo_large` contains `{ "default": "{uuid}.{ext}" }`

#### Scenario: Only the small logo
- **WHEN** the small logo is set, and the large one is empty
- **THEN** the small logo is read, and the large one is empty

#### Scenario: File is not an image
- **WHEN** a file of the profile `document` is chosen as a logo
- **THEN** the record is rejected with a validation error of the logo field

#### Scenario: Clearing a logo
- **WHEN** a saved logo is cleared
- **THEN** the setting is read as empty, and the file remains in the storage

### Requirement: Облік використання логотипів
The system SHALL record the use of the logo files in the links of the media library: the owner is the site settings, the role is the key of the logo, the language is the language of the value. The links SHALL be updated in the same transaction as the record of the settings, so that after the record the set of links matches the saved logos. A file used as a logo SHALL NOT be allowed to be deleted from the media library.

#### Scenario: Logo becomes used
- **WHEN** a file is chosen as the large logo and the settings are saved
- **THEN** a link of this file to the site settings exists, and deleting the file from the media library is rejected

#### Scenario: Replacing a logo
- **WHEN** the large logo is replaced by another file
- **THEN** the link to the previous file is removed, a link to the new one is created, and the previous file can be deleted

#### Scenario: Clearing a logo
- **WHEN** a logo is cleared and the settings are saved
- **THEN** the link is removed, and the file remains in the storage

#### Scenario: Write error
- **WHEN** the writing of the settings ends with an error
- **THEN** neither the values nor the links of the logos are changed
