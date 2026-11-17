# Spec Delta

## MODIFIED Requirements

### Requirement: Search scope and visibility
The search SHALL look through the nodes of kinds `page` and `content` and SHALL NOT return nodes of kind `block`. Only visible nodes (active, published, not unpublished) SHALL be returned. A `content` node whose type has no slug has no public address and SHALL NOT be returned. The search SHALL match the node's `title` and the values of its text fields (`string`, `text`, `richtext`) in every language of the node: the base language and every active additional language, not only the reading language of the request. A node SHALL be returned once however many of its languages match, and its title SHALL be shown in the reading language with fallback to the base language, while its excerpt SHALL be taken around the first match in the language in which the match was found. The match SHALL be a case-insensitive "contains" match of the whole term. Every result SHALL link to the public address of the node (`/{slug}` for a page, `/{type slug}/{node slug}` for a record).

#### Scenario: Match in the title
- **WHEN** a visible page has the title "Lamp museum" and a guest searches `lamp`
- **THEN** the page is in the results and links to its address

#### Scenario: Match in a text field of a record
- **WHEN** a visible record of the type with the slug `news` has the word `lamp` in a `richtext` field and a guest searches `lamp`
- **THEN** the record is in the results and links to `/news/{slug}`

#### Scenario: Invisible nodes are hidden
- **WHEN** a node that contains the term is disabled, scheduled, or expired
- **THEN** it is not in the results

#### Scenario: Blocks and nodes without an address
- **WHEN** a block, or a record of a type without a slug, contains the term
- **THEN** it is not in the results

#### Scenario: Reading language without fallback
- **WHEN** the reading language is not the base language and a node has the term only in its base-language value
- **THEN** the node is in the results, because the search is not limited to the reading language, and its title is shown in the reading language, falling back to the base language

#### Scenario: Match in another language
- **WHEN** a guest on the English site searches a word that occurs only in the Ukrainian text of a page
- **THEN** the page is in the results once

#### Scenario: Match in several languages
- **WHEN** the term occurs in the base and in the English value of one node
- **THEN** the node appears once in the results
