# site-settings Specification

## Purpose
Defines the set of general site settings (name, logos, contacts, social networks, working hours, indexing), the format of their storage with readiness for languages, and the way templates read them. It describes the data, not the admin page.

## Requirements

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

### Requirement: Reading with fallback to default
The system SHALL provide templates with a site settings reader, which returns the translatable values for the requested language, and, if there is no value for that language, returns the value of the language `default`. The reader SHALL return lists (`emails`, `phones`, `socials`, `schedule`) in the saved order, and the label of an element in the language of the request with fallback to `default`.

#### Scenario: Value of the requested language
- **WHEN** `site.name` is saved with the values `default` and `en`, and the reader is requested with the language `en`
- **THEN** the value `en` is returned

#### Scenario: Fallback to default
- **WHEN** only the value `default` is saved, and the reader is requested with the language `en`
- **THEN** the value `default` is returned

#### Scenario: Order of a list
- **WHEN** three phones are saved in a certain order
- **THEN** the reader returns them in the same order

### Requirement: Indexing is disabled by default
The setting `site.indexable` SHALL be boolean, and if no value is saved it SHALL be read as `false` (the site is not indexed). The setting SHALL be available to templates through the reader; the output of the indexing attributes itself is not part of this capability.

#### Scenario: Value not set
- **WHEN** `site.indexable` has never been saved
- **THEN** the reader returns `false`

#### Scenario: Indexing enabled
- **WHEN** `site.indexable` is saved with the value `true`
- **THEN** the reader returns `true`

### Requirement: Social networks directory
The system SHALL have a fixed directory of known social networks, which is set in the configuration and is not changed from the admin panel. A saved social network SHALL refer to a network of the directory by its key; a key that is not in the directory SHALL be rejected. The same network SHALL be allowed several times.

#### Scenario: Known network
- **WHEN** a social network with a network key that is in the directory is saved
- **THEN** the record is accepted

#### Scenario: Unknown network
- **WHEN** a social network with a key that is not in the directory is saved
- **THEN** the record is rejected with a validation error of the network field

#### Scenario: Two pages of one network
- **WHEN** two records with the network `instagram` and different links are saved
- **THEN** both records are saved in the specified order

### Requirement: Logos as files of the image profile
The system SHALL store the large and the small logo as references to a file of the `image` profile (the value `{uuid}.{ext}` under a language key), and SHALL reject a file of another profile or a non-existent file. Each logo SHALL be optional and independent of the other.

#### Scenario: Saving a logo
- **WHEN** an uploaded image is selected as the large logo
- **THEN** `site.logo_large` contains `{ "default": "{uuid}.{ext}" }`

#### Scenario: Only the small logo
- **WHEN** the small logo is set, and the large one is empty
- **THEN** the small logo is read, and the large one is empty

#### Scenario: A file is not an image
- **WHEN** a file of the `document` profile is selected as a logo
- **THEN** the record is rejected with a validation error of the logo field

#### Scenario: Clearing a logo
- **WHEN** the saved logo is cleared
- **THEN** the setting is read as empty, and the file remains in the storage

### Requirement: Tracking the use of logos
The system SHALL record the use of logo files in the media library relations: the owner is the site settings, the role is the logo key, the language is the language of the value. The relations SHALL be updated in the same transaction as the write of the settings, so that after the write the set of relations matches the saved logos. A file used as a logo SHALL NOT be possible to delete from the media library.

#### Scenario: A logo becomes used
- **WHEN** a file is selected as the large logo and the settings are saved
- **THEN** a relation of this file with the site settings exists, and deleting the file from the media library is rejected

#### Scenario: Replacing a logo
- **WHEN** the large logo is replaced with another file
- **THEN** the relation with the previous file is removed, a relation with the new file is created, and the previous file can be deleted

#### Scenario: Clearing a logo
- **WHEN** the logo is cleared and the settings are saved
- **THEN** the relation is removed, and the file remains in the storage

#### Scenario: Write error
- **WHEN** the write of the settings fails with an error
- **THEN** neither the values nor the logo relations are changed

### Requirement: Saving one language of the settings
The system SHALL provide an operation that saves the translatable settings in one additional language and clears them. It SHALL accept only the translatable settings (the name, short name, description, address, schedule, and the labels of emails, phones and socials), SHALL identify a list item by its non-translatable key (the `value` of an email or a phone; the network together with the link of a social network) and SHALL ignore labels of items that do not exist. It SHALL NOT store a value equal to the `default` value of the same setting, SHALL remove a value submitted empty, SHALL remove a setting's language key when nothing remains, and SHALL leave every other language, the `default` values, the logos, the indexing flag and the non-translatable data of the lists unchanged. A language SHALL be accepted only under the content language rules. Reading a setting SHALL keep falling back to `default`. A language in which any translatable setting is stored SHALL NOT be deletable.

#### Scenario: Equal to the base
- **WHEN** the English name equal to the base name is saved
- **THEN** no English name is stored

#### Scenario: Label of a missing item
- **WHEN** a label is submitted for a phone number that is not in the list
- **THEN** it is ignored

#### Scenario: Other languages
- **WHEN** the English translation is saved on a site that has Polish translations
- **THEN** the Polish values and the `default` values are unchanged

#### Scenario: Clearing
- **WHEN** the English translation of the settings is cleared
- **THEN** no English value remains and every reader returns the `default` values for English

#### Scenario: Deleting a language
- **WHEN** a language with a stored translated setting is deleted
- **THEN** the deletion is rejected
