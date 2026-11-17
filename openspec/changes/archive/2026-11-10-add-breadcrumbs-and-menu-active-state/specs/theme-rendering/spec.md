# Spec Delta

## MODIFIED Requirements

### Requirement: Menus in templates
A template SHALL be able to place a menu by its key with an explicit call. The menu SHALL select the template from the list `menus/menu--{key}`, `menus/menu` and receive the tree of visible items in the reading language (external links are marked, nested items keep the hierarchy). A menu that does not exist SHALL output nothing and SHALL NOT break the page.

Each item SHALL carry two flags about the current request: `is_active` for the item that matches the requested path and `is_trail` for every ancestor of that item; every other item has both `false`, and at most one item of a menu is active. The match SHALL be made on the path of the item's stored address and the path of the request, ignoring the query string, the fragment, and a trailing slash. An item matches when its path equals the request path, or is a prefix of it that ends at a segment boundary (`/news` matches `/news/hello` and not `/newsletter`); the address `/` matches only the home page. When several items match, the one with the longest path SHALL win, and for equal paths the first in menu order. An item without an address, an external address, `mailto:`, `tel:`, and an address that starts with `#` or `?` SHALL never match.

An item whose stored address starts with `/` (but not `//`) SHALL be given to the template as an absolute URL (scheme and host of the request). Other addresses SHALL be given unchanged. The `is_external` flag and the match SHALL be decided from the stored address, so a relative item stays internal.

#### Scenario: A nested menu
- **WHEN** the menu `main` has an item with a child item
- **THEN** the HTML contains both items, with the child nested in the parent

#### Scenario: A hidden item
- **WHEN** a menu item is inactive or outside its publication period
- **THEN** it and its branch are not output

#### Scenario: A menu without a record
- **WHEN** a template calls a menu with a key that does not exist
- **THEN** the placement outputs nothing, and the page response is successful

#### Scenario: A separate menu template
- **WHEN** the theme has `menus/menu--footer`
- **THEN** the menu `footer` is rendered by it, and the other menus — by the template `menus/menu`

#### Scenario: The active item and its parents
- **WHEN** the menu has the item `Catalog` (`/catalog`) with the child `Jackets` (`/catalog/jackets`) and the page `/catalog/jackets` is requested
- **THEN** `Jackets` is active, `Catalog` is in the trail and not active, and no other item has a flag

#### Scenario: A prefix match on a segment boundary
- **WHEN** the item `/news` exists and `/news/hello` is requested
- **THEN** the item is active
- **AND WHEN** `/newsletter` is requested
- **THEN** the item is not active

#### Scenario: The longest match wins
- **WHEN** the items `/news` and `/news/culture` exist and `/news/culture/today` is requested
- **THEN** only `/news/culture` is active and `/news` is not

#### Scenario: The home address
- **WHEN** the item `/` exists and `/about` is requested
- **THEN** the item is not active
- **AND WHEN** `/` is requested
- **THEN** the item is active

#### Scenario: Query and fragment are ignored
- **WHEN** `/news?page=2` is requested and the item `/news` exists
- **THEN** the item is active

#### Scenario: Items that never match
- **WHEN** the menu has an item without an address, an item `https://example.org/news`, an item `mailto:a@b.c`, and an item `#top`, and `/news` is requested
- **THEN** none of them is active and none is in the trail

#### Scenario: A relative address becomes absolute
- **WHEN** an item is stored with the address `/about` and the site is served from `https://example.org`
- **THEN** the template receives `https://example.org/about` and `is_external` is `false`

#### Scenario: Other addresses are unchanged
- **WHEN** an item is stored with the address `https://example.org/x`, `mailto:a@b.c`, or `#top`
- **THEN** the template receives it unchanged
