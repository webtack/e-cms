# theme-rendering Specification

## Purpose

Converting field values into ready-to-output data and rendering fields, blocks, and menus with the theme's templates, with the candidate cascade and hooks.

## Requirements

### Requirement: Ready field values
Each field type SHALL provide a public representation of the value (`present`): data ready for output without additional queries in the template. Files and images SHALL be presented with a public URL together with the file name and its description (the description is empty if not set); a value of type `term` — with the name of the term in the reading language (without substitution by another language); `node_ref` — with the `uuid`, the title and the address of the target node (an invisible or deleted node SHALL give no value; the rendered `content` of the target is added to it by the field renderer, see "The content of a referenced node"); `type_ref` — with the key, the name, and the address of the type (for the `content` kind); `youtube` — with the identifier, the view address, and the embed address; `url` — with the address and the title; `richtext` — with the cleaned HTML. The other types SHALL be presented with the value of their PHP type.

#### Scenario: An image as a URL
- **WHEN** a field of type `image` has the value `{uuid}.png`
- **THEN** the field template receives the public URL of this file, not `{uuid}.png`

#### Scenario: File name and description
- **WHEN** a field of type `file` refers to a file with the name `price.pdf` and the description `Price list`
- **THEN** the field template receives the URL, the name `price.pdf`, and the description `Price list`, and for a file without a description — an empty description

#### Scenario: A term as a name
- **WHEN** a field of type `term` refers to a term that has a translation in the reading language
- **THEN** the field template receives the name of the term in this language

#### Scenario: An invisible target node
- **WHEN** a `node_ref` field refers to a node that is inactive or unpublished
- **THEN** the template does not receive this value and outputs nothing about it

#### Scenario: The uuid of a target node
- **WHEN** a `node_ref` field refers to a visible node
- **THEN** the field template receives the `uuid` of that node together with its title and address

#### Scenario: YouTube
- **WHEN** a `youtube` field has a value with a video identifier
- **THEN** the template receives the identifier and the `youtube-nocookie.com` embed address

### Requirement: Rendering a field
The system SHALL render a field with a template chosen from a list of eight candidates from specific to general: `{t}/field-{ftype}--{key}`, `{t}/field-{ftype}`, `{t}/field--{key}`, `{t}/field`, `fields/field-{ftype}--{key}`, `fields/field-{ftype}`, `fields/field--{key}`, `fields/field`, where `{t}` is the key of the node's type, `{ftype}` is the field type, and `{key}` is the field key. The template SHALL always receive `$items` — an array of ready values, also for a single-value field, and `$field`, whose `hide_label` tells whether the label is hidden. The template SHALL also receive `$values` — the values of the same node by field key, as the caller already has them (the values passed to `@field` or `@fields`; for a card of a list, the title and the list fields), so the template can use another field of the record without a query; `$values` SHALL be an empty array when the field is rendered without node values. A field without a value SHALL output nothing. Before rendering, the hooks `preprocess('field')` and `preprocess('field.{ftype}')` SHALL run, and `suggest('field')` SHALL be able to change the list of candidates. The theme `default` SHALL have a template for each of the 15 field types and the common `fields/field`; each of them SHALL output the field label (its name) unless the field has `hide_label` on, and the file template — the file name as a link and its description. A field with the label hidden SHALL still output its values.

#### Scenario: Cascade from the field key
- **WHEN** the theme has `fields/field--cover` and `fields/field-image`, and the field `cover` of type `image` is rendered
- **THEN** `fields/field-image--cover` is used if it exists, otherwise `fields/field-image`, and `fields/field--cover` only when `fields/field-image` does not exist

#### Scenario: The node type template outranks the global one
- **WHEN** the node type `news` has `news/field-string`, and `default` has `fields/field-string`
- **THEN** a field of type `string` of a node of `news` is rendered by the template `news/field-string`

#### Scenario: A single-value field as a list
- **WHEN** a single-value field with a value is rendered
- **THEN** the template receives `$items` with one element

#### Scenario: The node values in the field template
- **WHEN** a template calls `@fields($values)` for a record whose `title` is "Hello" and the template of one of its fields reads `$values['title']`
- **THEN** that field template sees "Hello", and no query is made for it beyond those the caller already made

#### Scenario: A field rendered without node values
- **WHEN** a field is rendered without the values of its node
- **THEN** the template receives `$values` as an empty array and the field still renders

#### Scenario: An empty field
- **WHEN** a field has no value (or a multiple field has an empty list)
- **THEN** the HTML of the field is empty

#### Scenario: Label and file description in the default theme
- **WHEN** a non-empty field of any type is rendered, and for `file` — with the file name and description
- **THEN** the HTML contains the field name as a label; for `file` — the file name as the link text and the description under it

#### Scenario: Hidden label in the default theme
- **WHEN** a non-empty field of any type with `hide_label` on is rendered
- **THEN** the HTML contains the values of the field and does not contain the field name as a label

#### Scenario: The preprocess hook of a field
- **WHEN** the theme registers `preprocess('field.youtube')` that changes the variables
- **THEN** the template of the `youtube` field receives the changed variables, and the hook `field` was executed earlier

### Requirement: The content of a referenced node
Each presented `node_ref` value in a field template SHALL carry `content`: the HTML of the target rendered by the target's own template (its `title` is already in the value). Only a visible node (active, published, not expired) SHALL have content; a value whose target is missing, deleted, or not visible SHALL NOT be presented at all (as before).

For a target that is a **block**, `content` SHALL be the output of the block's template, chosen by the candidates of a block placed by its slug (`blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`) preceded by the candidates of the **owner type** (`{owner}/block-{t}--{slug}`, `{owner}/block-{t}`, see "A block in the context of its owner" below), with the same variables plus `$owner`. For a target that is a **node of any other kind**, `content` SHALL be the output of the card template of the target, chosen by the card candidates (`{t}/preview--{id}`, `{t}/preview`, `content/preview`, `{t}` being the key of the TARGET's type), with the card variables and the address of the target as `$url`; the owner type SHALL NOT take part in the card candidates. The card SHALL show the card fields of the target's type: the `list_fields` of the type for the kinds that have them, and the type's page fields for a kind that has no `list_fields`. In both cases a theme overrides the look by files of its own and no separate embed template, no new template directive SHALL exist: the `node_ref` field template decides how the content is wrapped. The references inside the output SHALL be presented in turn.

The values SHALL be read in the reading language, each missing value being replaced by the base-language value of the same field and by no other language, and only for the fields the template shows. Producing the content SHALL NOT count as a view of the target and SHALL NOT create a public address.

A node SHALL NOT have content while it is already being rendered: the node whose page or field is being rendered, and a node already rendered higher in the same chain, SHALL get `content` equal to `''`; this SHALL hold also for the references placed inside a block template or a card template. Content nested more than 3 levels deep SHALL be `''` as well. An empty `content` SHALL NOT remove the value: the template still receives the title and address and can print a link.

#### Scenario: The content of a referenced node
- **WHEN** a `node_ref` field points to a visible `news` node and the theme has `news/preview`
- **THEN** the item received by the field template has `content` equal to the output of `news/preview` for that node, with the address of the node as `$url`

#### Scenario: The card cascade of the target
- **WHEN** the theme has only `content/preview` and the target is of the type `news`
- **THEN** `content` is the output of `content/preview`

#### Scenario: A template for a field of the target type
- **WHEN** the theme has `news/field-string--body`, the target is of the type `news` and its card prints the field `body` with `@field`
- **THEN** the field is output by `news/field-string--body`, wherever the reference is placed

#### Scenario: A card for one node
- **WHEN** the theme has `news/preview--7` and the target is the `news` node with id 7
- **THEN** `content` for that node is the output of `news/preview--7` and other `news` nodes use `news/preview`

#### Scenario: The owner does not change the card
- **WHEN** the theme has `article/block-banner` and a `node_ref` field of an `article` node points to a `news` node
- **THEN** `content` is the card of the `news` node chosen by the card candidates only

#### Scenario: Fields of the target
- **WHEN** the type of the target has `list_fields` `price` and `cover`, and `body` is only a page field
- **THEN** the card receives `price` and `cover` and does not receive `body`

#### Scenario: A kind without list fields
- **WHEN** the target is a node of a type that has no `list_fields`
- **THEN** the card receives the page fields of the type

#### Scenario: A block
- **WHEN** a `node_ref` field points to a visible block with the slug `block-footer` of the type `banner`, the theme has `blocks/block-banner--footer` and no template of the owner type applies
- **THEN** `content` equals the output of that template, the same as `@block('block-footer')` gives, and the item has no address

#### Scenario: A block without its own template
- **WHEN** the target is a block and the theme has only `blocks/block`
- **THEN** `content` is the output of `blocks/block` with the page fields of the block

#### Scenario: An invisible or missing node
- **WHEN** the target is inactive, not yet published, expired, deleted, or does not exist
- **THEN** the value is not presented, the field template outputs nothing about it, and the page response is successful

#### Scenario: The reading language is not substituted
- **WHEN** the target has a field value only in the default language and the page is rendered in another language
- **THEN** `content` shows the default-language value of that field in its place (the base language is the only substitute, never another language)

#### Scenario: A node that references itself
- **WHEN** a node has a `node_ref` field that points to the node itself and its page is rendered
- **THEN** the item has the title and address but `content` is `''`

#### Scenario: A reference cycle
- **WHEN** node A references node B, node B has a card that shows a `node_ref` to A, and the page of A is rendered
- **THEN** the item for B has content, and inside it the item for A has `content` equal to `''`

#### Scenario: A reference cycle through a block
- **WHEN** a block references a node and that node references the same block, and the page of the node is rendered
- **THEN** the block content is produced once and the repeated reference has `content` equal to `''`

#### Scenario: Depth limit
- **WHEN** nodes are chained by `node_ref` fields A -> B -> C -> D -> E without a cycle and the page of A is rendered
- **THEN** the content of B, C, and D is produced and the content of E is `''`

#### Scenario: A view is not counted
- **WHEN** the page of A shows the content of B
- **THEN** the view counter of B does not change

### Requirement: A node reference in the default theme
The theme `default` SHALL output a `node_ref` field value as the `content` of the target, together with the field label unless the field has `hide_label` on, and SHALL NOT print the title of the target again next to it, because the card or block already prints what the target shows. When `content` is empty because of a cycle or the depth limit, the template SHALL output only the title as a link to its address (plain text for a block), so the value is not lost. A reference to an invisible or deleted node SHALL output nothing, as before.

#### Scenario: A node reference shows the content
- **WHEN** a node has a `node_ref` field that points to a visible node, and the page of the first node is rendered
- **THEN** the HTML contains the label of the field and the card of the target, and the title of the target appears once

#### Scenario: A multiple node reference
- **WHEN** a multiple `node_ref` field points to two visible nodes
- **THEN** the cards of both are output in the order of the values

#### Scenario: The fallback to a link
- **WHEN** a `node_ref` value has empty `content` because it would repeat a node already being rendered
- **THEN** the HTML contains the title of that node as a link and no card

#### Scenario: A hidden label
- **WHEN** a `node_ref` field with `hide_label` on is rendered
- **THEN** the HTML contains the content and does not contain the field name as a label

### Requirement: Blocks in templates
A template SHALL be able to place a block by its slug with an explicit call. A block placed this way SHALL select the template from the list `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`, where `{t}` is the key of the block's type, and `{slug}` is the slug of the node without the prefix `block-`; it has no owner, so the templates of an owner type SHALL NOT take part. The block's template SHALL receive the node, the type, and the values, and its fields SHALL be output by the same mechanism as the fields of a page. A block that does not exist, is inactive, or is unpublished SHALL output nothing and SHALL NOT break the page.

#### Scenario: Placing a block
- **WHEN** a visible block with the slug `block-footer` exists, and the template calls its placement
- **THEN** the HTML contains the content of the block assembled by the template `blocks/block-{t}--footer`, and if it is absent — `blocks/block-{t}` or `blocks/block`

#### Scenario: A placed block ignores owner templates
- **WHEN** the theme has `article/block-banner` and a template places a block of the type `banner` with `@block('block-footer')` on the page of an `article` node
- **THEN** the block is assembled by the candidates `blocks/block-banner--footer`, `blocks/block-banner`, `blocks/block`, and `article/block-banner` is not used

#### Scenario: An invisible block
- **WHEN** the block is inactive, not scheduled, or does not exist
- **THEN** the placement outputs nothing, and the page response is successful

#### Scenario: A block is not routed
- **WHEN** a block is placed in a template
- **THEN** the block gets no public address of its own

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

### Requirement: A block in the context of its owner
A block rendered as the `content` of a `node_ref` item SHALL select its template from the list `{owner}/block-{t}--{slug}`, `{owner}/block-{t}`, `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`, where `{owner}` is the key of the type of the node whose field holds the reference, `{t}` the key of the block's type, and `{slug}` the slug of the block without the prefix `block-`. The owner SHALL be the node whose field is being rendered, so a reference inside another target (a block or a card) takes the type of that target as its owner. The directory of the owner type SHALL be the same `views/{owner}/` that holds the other templates of that type, and a theme without such files SHALL render the block exactly as before. The template SHALL receive the variables of a block (`$node`, `$type`, `$values`, `$fields`) and `$owner`, the owner node; for a block placed by its slug `$owner` SHALL be `null`. The cascade SHALL keep the first template found in the order "candidate, then theme" (the active theme, then `default`), as for every other template.

#### Scenario: A template of the owner type
- **WHEN** the theme has `institutions_sources/block-contacts` and a `node_ref` field of an `institutions_sources` node points to a visible block of the type `contacts`
- **THEN** `content` is the output of `institutions_sources/block-contacts`, and `$owner` is that `institutions_sources` node

#### Scenario: Other owners are not affected
- **WHEN** the theme has `institutions_sources/block-contacts` and a `node_ref` field of an `institutions` node points to a block of the type `contacts`
- **THEN** `content` is the output of `blocks/block-contacts` (or `blocks/block`), as before

#### Scenario: The owner template for one block
- **WHEN** the theme has `institutions_sources/block-contacts--museum` and `institutions_sources/block-contacts`, and the referenced block has the slug `block-museum`
- **THEN** `content` is the output of `institutions_sources/block-contacts--museum`, and other blocks of that type referenced from the same owner type use `institutions_sources/block-contacts`

#### Scenario: The owner template wins over the block's own template
- **WHEN** the theme has both `institutions_sources/block-contacts` and `blocks/block-contacts--museum`, and the block `block-museum` is referenced from an `institutions_sources` node
- **THEN** `content` is the output of `institutions_sources/block-contacts`

#### Scenario: A reference nested in another target
- **WHEN** a `news` card prints a `node_ref` field that points to a block of the type `banner`, and the theme has `news/block-banner`
- **THEN** `content` of that item is the output of `news/block-banner`, wherever the card is placed

### Requirement: Presented values use the reading fallback
The public representation of a value (`present`) SHALL use the reading language with fallback to the base language, field by field: the name of a `term` value, the description of an `image` or `file` value (the translated file description, else the base description), and every text value of a field. The label printed for a field SHALL be its translated label in the reading language, else its base name. A value that is missing in both the reading and the base language SHALL be presented as missing, as before.

#### Scenario: Term name
- **WHEN** a term has the English name "Jackets" and a base name "Куртки", and the page is read in English, then in Polish with no Polish name
- **THEN** the English page receives "Jackets" and the Polish page receives "Куртки"

#### Scenario: File description
- **WHEN** an image has the English description "Front cover" and a base description, and the page is read in English, then in Polish without a Polish description
- **THEN** the English page receives "Front cover" and the Polish page receives the base description

#### Scenario: Field label
- **WHEN** a field has the translated label "Opening hours" in English and the page is read in English, then in Polish
- **THEN** the English page prints "Opening hours" and the Polish page prints the base name of the field

### Requirement: Field templates receive the label in the reading language
The field that a field template receives SHALL carry, in its `name`, the label of the field in the reading language, falling back to the base name when the label is not translated, so that templates printing `name` as the label need no change. This SHALL hold for every field template, in every theme, and SHALL NOT change the stored name of the field. The name of a content type SHALL NOT be translated: it is a service name, and the public title of a type comes from its section title.

#### Scenario: Template prints the field name
- **WHEN** a field has the base name "Графік" and the English label "Schedule", and a field template prints `name` on an English page
- **THEN** the template prints "Schedule"

#### Scenario: Label is not translated
- **WHEN** the field has no English label and the page is English
- **THEN** the template prints the base name

#### Scenario: Stored name is untouched
- **WHEN** a page in any language has been rendered
- **THEN** the stored name of the field is unchanged

#### Scenario: Type name
- **WHEN** a page in any language is rendered
- **THEN** the service name of a content type is not translated and is not printed as a public title
