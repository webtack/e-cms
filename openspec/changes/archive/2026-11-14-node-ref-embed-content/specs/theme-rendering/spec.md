# Spec Delta

## ADDED Requirements

### Requirement: The content of a referenced node
Each presented `node_ref` value in a field template SHALL carry `content`: the HTML of the public page fields of the target node (its `title` is already in the value). Only a visible node (active, published, not expired) SHALL have content; a value whose target is missing, deleted, or not visible SHALL NOT be presented at all (as before). The fields of the target SHALL be output by the same mechanism as the fields of a page, in the context of the target node, so their templates are chosen by the candidates of the target's type (`{t}/field-{ftype}--{key}`, ..., `fields/field`, `{t}` being the key of the TARGET's type), and the references inside them are presented in turn. The values SHALL be read in the reading language without substitution by another language, and only for the type's public page fields. Producing the content SHALL NOT count as a view of the target and SHALL NOT create a public address. There SHALL be no separate embed template and no template directive for it: the `node_ref` field template decides how the content is wrapped.

A node SHALL NOT have content while it is already being rendered: the node whose page or field is being rendered, and a node already rendered higher in the same chain, SHALL get `content` equal to `''`. Content nested more than 3 levels deep SHALL be `''` as well. An empty `content` SHALL NOT remove the value: the template still receives the title and address and can print a link.

#### Scenario: The content of a referenced node
- **WHEN** a `node_ref` field points to a visible `news` node that has a title and a rich text body
- **THEN** the item received by the field template has `content` that contains the body of that node, output by the field template of the `news` type or, if it is absent, by the generic ones

#### Scenario: Fields of the target
- **WHEN** the type of the target shows the fields `body` and `cover` on its page
- **THEN** both fields are in `content`, output by their field templates, and a field the type does not show is not in it

#### Scenario: A template for a field of the target type
- **WHEN** the theme has `news/field-string--body` and the target is of the type `news`
- **THEN** the field `body` in `content` is output by `news/field-string--body`, wherever the reference is placed

#### Scenario: An invisible or missing node
- **WHEN** the target is inactive, not yet published, expired, deleted, or does not exist
- **THEN** the value is not presented, the field template outputs nothing about it, and the page response is successful

#### Scenario: A block
- **WHEN** the target is a block
- **THEN** its fields are in `content` and the item has no address

#### Scenario: The reading language is not substituted
- **WHEN** the target has a field value only in the default language and the page is rendered in another language
- **THEN** `content` does not show the default-language value in its place

#### Scenario: A node that references itself
- **WHEN** a node has a `node_ref` field that points to the node itself and its page is rendered
- **THEN** the item has the title and address but `content` is `''`

#### Scenario: A reference cycle
- **WHEN** node A references node B, node B references node A, and the page of A is rendered
- **THEN** the item for B has content, and inside it the item for A has `content` equal to `''`

#### Scenario: Depth limit
- **WHEN** nodes are chained by `node_ref` fields A -> B -> C -> D -> E without a cycle and the page of A is rendered
- **THEN** the content of B, C, and D is produced and the content of E is `''`

#### Scenario: A view is not counted
- **WHEN** the page of A shows the content of B
- **THEN** the view counter of B does not change

### Requirement: A node reference in the default theme
The theme `default` SHALL output a `node_ref` field value as the title of the target as a link to its address (plain text for a block) followed by the `content` of the target, together with the field label unless the field has `hide_label` on. When `content` is empty because of a cycle or the depth limit, the template SHALL output only the title as a link, so the value is not lost. A reference to an invisible or deleted node SHALL output nothing, as before.

#### Scenario: A node reference shows the content
- **WHEN** a node has a `node_ref` field that points to a visible node with a title and a body, and the page of the first node is rendered
- **THEN** the HTML contains the label of the field, the title of the target as a link to its address, and the body of the target

#### Scenario: A multiple node reference
- **WHEN** a multiple `node_ref` field points to two visible nodes
- **THEN** the content of both is output in the order of the values

#### Scenario: The fallback to a link
- **WHEN** a `node_ref` value has empty `content` because it would repeat a node already being rendered
- **THEN** the HTML contains the title of that node as a link and no content

#### Scenario: A hidden label
- **WHEN** a `node_ref` field with `hide_label` on is rendered
- **THEN** the HTML contains the title and the content and does not contain the field name as a label

## MODIFIED Requirements

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
