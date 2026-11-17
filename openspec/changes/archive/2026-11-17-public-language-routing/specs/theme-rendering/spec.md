# Spec Delta

## MODIFIED Requirements

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

## ADDED Requirements

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
