## MODIFIED Requirements

### Requirement: Where a file is used
The media library page SHALL show, for the selected file, the list of places of its use: the owner (with a link to its edit page, if it exists and the viewer may open it), the role, and the language. A place whose owner the viewer may not open SHALL be shown only as a neutral entry "Used elsewhere" without a title, a link, an owner type, a field, or a language, and SHALL still be counted. The viewer may open: a node when their level on the node's type is not `none` (the link only when they may edit that node); a user with the right to manage users; the site settings with the right to the settings; a term with the right to taxonomy; a menu item with the right to menus; a type with the right to types. An owner of an unknown kind SHALL never be shown. A user of the role `admin` SHALL see every place. The number of usages and the refusal to delete a file that is in use SHALL NOT depend on what the viewer may open.

#### Scenario: Used file
- **WHEN** the user opens the details of a file used in the field `cover` of the node "Hello" of a type where they have the level `any`
- **THEN** the details show this node (with a link to its editing), the field `cover`, and the language

#### Scenario: Unused file
- **WHEN** the user opens the details of a file without relations
- **THEN** the details report that the file is not used

#### Scenario: Node of a type without access
- **WHEN** a user with the media level `own` and the level `none` on the type `secret` opens the details of a file used by a node of the type `secret`
- **THEN** the usage is shown as "Used elsewhere" without the node's title, link, type, field, or language, and the usage count is unchanged

#### Scenario: Node of another author at the level own
- **WHEN** a user with the level `own` on a type opens the details of a file used by another author's node of that type
- **THEN** the node's title is shown without a link to its edit form

#### Scenario: User and settings owners
- **WHEN** a user without the right to manage users opens the details of a file that is another user's avatar, or without the settings right opens a file that is a site logo
- **THEN** each of these usages is shown as "Used elsewhere"

#### Scenario: Administrator
- **WHEN** an administrator opens the details of any used file
- **THEN** every place of use is shown with its title, link, field, and language

#### Scenario: Deletion is not affected
- **WHEN** a user attempts to delete a file whose only usage is hidden from them
- **THEN** the deletion is rejected because the file is in use
