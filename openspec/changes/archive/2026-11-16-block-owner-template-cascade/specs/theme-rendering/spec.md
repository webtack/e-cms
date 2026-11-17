## MODIFIED Requirements

### Requirement: The content of a referenced node
Each presented `node_ref` value in a field template SHALL carry `content`: the HTML of the target rendered by the target's own template (its `title` is already in the value). Only a visible node (active, published, not expired) SHALL have content; a value whose target is missing, deleted, or not visible SHALL NOT be presented at all (as before).

For a target that is a **block**, `content` SHALL be the output of the block's template, chosen by the candidates of a block placed by its slug (`blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`) preceded by the candidates of the **owner type** (`{owner}/block-{t}--{slug}`, `{owner}/block-{t}`, see "A block in the context of its owner" below), with the same variables plus `$owner`. For a target that is a **node of any other kind**, `content` SHALL be the output of the card template of the target, chosen by the card candidates (`{t}/preview--{id}`, `{t}/preview`, `content/preview`, `{t}` being the key of the TARGET's type), with the card variables and the address of the target as `$url`; the owner type SHALL NOT take part in the card candidates. The card SHALL show the card fields of the target's type: the `list_fields` of the type for the kinds that have them, and the type's page fields for a kind that has no `list_fields`. In both cases a theme overrides the look by files of its own and no separate embed template, no new template directive SHALL exist: the `node_ref` field template decides how the content is wrapped. The references inside the output SHALL be presented in turn.

The values SHALL be read in the reading language without substitution by another language, and only for the fields the template shows. Producing the content SHALL NOT count as a view of the target and SHALL NOT create a public address.

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
- **THEN** `content` does not show the default-language value in its place

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

## ADDED Requirements

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
