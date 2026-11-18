# Spec Delta

## ADDED Requirements

### Requirement: Помилка колізії slug сторінки у формі запису
The forms of creating and editing a record of the kind `page` SHALL reject a slug that matches the slug of a type of the kind `content` or a reserved path of the site, and show an error next to the field slug; the rest of the entered data SHALL remain in the form. For records of the kinds `content` and `block` this check does NOT apply. An automatically created slug of a page SHALL avoid such matches by adding a numeric suffix, so the error occurs only for an explicitly entered slug.

#### Scenario: Slug of a page matches the slug of a type
- **WHEN** the user enters the slug `news` in the form of a page, and the type of the kind `content` has the slug `news`
- **THEN** the page is not saved, an error is shown next to the field slug

#### Scenario: Slug of a page matches a reserved path
- **WHEN** the user enters the slug `login` in the form of editing a page
- **THEN** the change is not saved, an error is shown next to the field slug

#### Scenario: Automatic slug without a match
- **WHEN** the user leaves the slug empty and enters the `title` «Admin»
- **THEN** the page is created with the slug `admin-2`

#### Scenario: Record content with the slug of a type
- **WHEN** the user enters the slug `news` in the form of a record of the type `content`, and the type has the slug `news`
- **THEN** the record is saved
