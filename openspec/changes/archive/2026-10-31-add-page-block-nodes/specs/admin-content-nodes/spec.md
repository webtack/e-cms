# Spec Delta

## RENAMED Requirements

- FROM: `### Requirement: Розділ записів лише для виду content`
- TO: `### Requirement: Розділи записів для кожного виду типів`

## MODIFIED Requirements

### Requirement: Сторінка списку записів
The admin SHALL have the list pages of records `/admin/content-types/{key}/nodes`, `/admin/page-types/{key}/nodes` and `/admin/block-types/{key}/nodes`, available only to authenticated users with a confirmed email. Each shows the records of the type with this key in the data table in server mode: the search, sorting, pagination and page size are performed by the server, and the state of the table is stored in the address. The section SHALL accept only types of its own kind (`content`, `page`, `block` respectively). The table SHALL NOT have filters. The page title SHALL name the type, and above the table SHALL be the horizontal section menu of the type with the current item «Записи». For a key that is not among the types of the kind of the section (in particular, a key of a type of another kind), the page SHALL respond with 404. The page SHALL open also for a type without records.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/nodes`
- **THEN** they see the table of records of the type `news`, the title with the name of the type and the section menu where «Записи» is current; there is no filter button in the toolbar of the table

#### Scenario: Records of pages and blocks
- **WHEN** an authenticated user opens `/admin/page-types/home/nodes` for a type of the kind `page` or `/admin/block-types/hero/nodes` for a type of the kind `block`
- **THEN** they see the table of records of this type, the title with the name of the type and the section menu where «Записи» is current

#### Scenario: Only records of this type
- **WHEN** records of the types `news` and `product` exist, and the user opens the list of the type `news`
- **THEN** the table contains only the records of the type `news`

#### Scenario: Empty state
- **WHEN** the type has no records
- **THEN** the page opens with the standard message of the table about the absence of data, and the button «Створити» is visible

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/content-types/home/nodes` for the key of a type of the kind `page`, or `/admin/page-types/hero/nodes` for the key of a type of the kind `block`
- **THEN** the server responds with 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/nodes` or `/admin/page-types/home/nodes`
- **THEN** they are redirected to the login page

### Requirement: Розділи записів для кожного виду типів
The record routes (list, create, store, edit, update, delete) SHALL exist in three sections: `/admin/content-types/{key}/nodes…`, `/admin/page-types/{key}/nodes…` and `/admin/block-types/{key}/nodes…`; each SHALL serve only the types of its own kind, and a record SHALL be addressed by the pair (type key, `uuid`). The behaviour of the form, saving, editing and deleting a record SHALL be the same in all sections, and the redirect after an action SHALL lead to the list of records of the same section. The breadcrumbs of the record pages SHALL lead through the sidebar item of the section: «Content Types», «Page Types» or «Block Types».

#### Scenario: Section of pages
- **WHEN** the user opens `/admin/page-types/home/nodes/create` for a type of the kind `page`
- **THEN** the creation form of a record of this type opens

#### Scenario: Creating a record of a block
- **WHEN** the user saves the creation form at `/admin/block-types/hero/nodes` without a slug
- **THEN** the record is created with a generated slug with the prefix `block-`, and the user is redirected to `/admin/block-types/hero/nodes`

#### Scenario: Slug of a block without the prefix outside the form
- **WHEN** the request to create a record of a block contains an explicit slug without the prefix `block-`
- **THEN** the record is not created, and the error is placed next to the slug field

#### Scenario: Record of a foreign section
- **WHEN** the user opens `/admin/content-types/hero/nodes/{uuid}/edit` for a record of the type `hero` of the kind `block`
- **THEN** the server responds with 404

#### Scenario: Breadcrumbs
- **WHEN** the user opens the creation form of a record at `/admin/page-types/home/nodes/create`
- **THEN** the breadcrumbs: «Admin» → «Page Types» (link) → «Create node»

#### Scenario: Breadcrumbs of content
- **WHEN** the user opens the creation form of a record at `/admin/content-types/news/nodes/create`
- **THEN** the breadcrumbs: «Admin» → «Content Types» (link) → «Create node»

## ADDED Requirements

### Requirement: Посилання на запис у медіатеці для всіх видів
The media library page SHALL show, for a file linked to a record of any kind (`content`, `page`, `block`), the label of the record and a link to its edit form in the section of its kind.

#### Scenario: File in a page record
- **WHEN** a file is used in a record of the type `home` of the kind `page`
- **THEN** the media library shows a link to `/admin/page-types/home/nodes/{uuid}/edit`

#### Scenario: File in a content record
- **WHEN** a file is used in a record of the type `news` of the kind `content`
- **THEN** the link leads to `/admin/content-types/news/nodes/{uuid}/edit`

### Requirement: Префікс slug у формі запису блоку
The create and edit form of a record of a type of the kind `block` SHALL show the prefix `block-` as an unchangeable, visually separated part of the slug field before the input element; the input element SHALL accept only the part of the slug after the prefix. The form SHALL always send the server the full slug with the prefix: `block-` is added to the entered text unless it already starts with it explicitly (an explicitly entered or pasted prefix is not doubled, and the field shows the rest after it). An empty field SHALL be sent empty, and the server generates a slug with the prefix `block-`. In the edit form of a record the field SHALL show the existing slug without the prefix. The hint under the field SHALL explain that an empty field gives a generated slug with the prefix `block-`. For records of the kinds `content` and `page` the slug field SHALL remain without a prefix.

#### Scenario: Prefix is visible
- **WHEN** the user opens the creation form of a record of a block
- **THEN** the prefix `block-` is visible before the slug field, and the field itself is empty

#### Scenario: Only the rest is entered
- **WHEN** the user enters `hero` in the field and saves the form
- **THEN** the record is created with the slug `block-hero`

#### Scenario: Prefix is already entered explicitly
- **WHEN** the user pastes `block-hero` into the field and saves the form
- **THEN** the field shows `hero`, and the record is created with the slug `block-hero` (the prefix is not doubled)

#### Scenario: Empty field
- **WHEN** the user leaves the field empty and saves the form
- **THEN** the record is created with a generated slug of the form `block-xxxxxxxx`

#### Scenario: Editing
- **WHEN** the user opens the edit form of a record with the slug `block-hero`
- **THEN** the prefix `block-` is shown separately, and the field contains `hero`

#### Scenario: Other kinds
- **WHEN** the user opens the form of a record of a type of the kind `content` or `page`
- **THEN** the slug field has no prefix
