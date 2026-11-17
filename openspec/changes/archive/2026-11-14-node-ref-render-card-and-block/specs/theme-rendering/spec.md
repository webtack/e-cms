## MODIFIED Requirements

### Requirement: The content of a referenced node
Each presented `node_ref` value in a field template SHALL carry `content`: the HTML of the target rendered by the target's own template (its `title` is already in the value). Only a visible node (active, published, not expired) SHALL have content; a value whose target is missing, deleted, or not visible SHALL NOT be presented at all (as before).

For a target that is a **block**, `content` SHALL be the output of the block's template, chosen by the same candidates as for a block placed by its slug (`blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`), with the same variables, so the block looks the same wherever it is placed. For a target that is a **node of any other kind**, `content` SHALL be the output of the card template of the target, chosen by the card candidates (`{t}/preview--{id}`, `{t}/preview`, `content/preview`, `{t}` being the key of the TARGET's type), with the card variables and the address of the target as `$url`. The card SHALL show the card fields of the target's type: the `list_fields` of the type for the kinds that have them, and the type's page fields for a kind that has no `list_fields`. In both cases a theme overrides the look by the same files it already uses for the block or the card, and no separate embed template, no new template name and no template directive SHALL exist: the `node_ref` field template decides how the content is wrapped. The references inside the output SHALL be presented in turn.

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

#### Scenario: Fields of the target
- **WHEN** the type of the target has `list_fields` `price` and `cover`, and `body` is only a page field
- **THEN** the card receives `price` and `cover` and does not receive `body`

#### Scenario: A kind without list fields
- **WHEN** the target is a node of a type that has no `list_fields`
- **THEN** the card receives the page fields of the type

#### Scenario: A block
- **WHEN** a `node_ref` field points to a visible block with the slug `block-footer` of the type `banner` and the theme has `blocks/block-banner--footer`
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
