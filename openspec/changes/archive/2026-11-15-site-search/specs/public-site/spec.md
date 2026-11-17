# Spec Delta

## ADDED Requirements

### Requirement: Site search route and reserved path
`GET /search` SHALL serve the site search page, with the search term in the query parameter `q` and the results page number in `page`. The first segment `search` SHALL be a reserved path (`content.reserved_paths`): no page slug and no `content` type slug whose first segment is `search` can be saved, and `/search` is never served as a page, a type list or a node address. The route SHALL take priority over `/{path}`. Only the exact address `/search` is the search page; `/search/anything` SHALL give the 404 of the site.

#### Scenario: Opening the search page
- **WHEN** a guest opens `/search?q=lamp`
- **THEN** the response 200 is the search page, not a page, type list or 404

#### Scenario: Page slug search is rejected
- **WHEN** an administrator saves a page with the slug `search`
- **THEN** the slug is rejected as reserved

#### Scenario: Type slug starting with search is rejected
- **WHEN** an administrator saves a `content` type with the slug `search/reports`
- **THEN** the slug is rejected as reserved

#### Scenario: Deeper address
- **WHEN** a guest opens `/search/lamp`
- **THEN** the response is the 404 of the site

### Requirement: Search scope and visibility
The search SHALL look through the nodes of kinds `page` and `content` and SHALL NOT return nodes of kind `block`. Only visible nodes (active, published, not unpublished) SHALL be returned. A `content` node whose type has no slug has no public address and SHALL NOT be returned. The search SHALL match the node's `title` and the values of its text fields (`string`, `text`, `richtext`) in the reading language of the request, without falling back to the base language. The match SHALL be a case-insensitive "contains" match of the whole term. Every result SHALL link to the public address of the node (`/{slug}` for a page, `/{type slug}/{node slug}` for a record).

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
- **THEN** the node is not in the results

### Requirement: Search term rules
The term SHALL be trimmed and cut to 100 characters. A term shorter than 3 characters, or an absent or empty `q`, SHALL show the search page with the form and no results, without querying nodes, and without an error status. The characters `%` and `_` in the term SHALL be matched literally.

#### Scenario: Too short term
- **WHEN** a guest opens `/search?q=ab`
- **THEN** the response 200 shows the form and a hint about the minimum length, and no results

#### Scenario: Empty term
- **WHEN** a guest opens `/search`
- **THEN** the response 200 shows the form only

#### Scenario: Wildcards are literal
- **WHEN** a guest searches `100%` and one visible node contains `100%` while another contains `1000`
- **THEN** only the first node is in the results

#### Scenario: Long term
- **WHEN** a guest searches a term of 150 characters
- **THEN** only its first 100 characters are used and the page is answered with status 200

### Requirement: Search results page
The results SHALL be paginated by `content.per_page` and the pagination links SHALL keep `q`. The page SHALL be rendered through the theme with the template candidate `search/results` and the hook `search`, inside the site shell, and SHALL ask search engines not to index it. The template SHALL receive the search term, the list of results (title, address, type kind, section title, publication date, plain-text excerpt) and the paginator. A search with no matches SHALL answer 200 with an empty list.

#### Scenario: Paginated results
- **WHEN** more nodes match than fit on one page
- **THEN** the paginator links to the other pages contain the same `q`

#### Scenario: No matches
- **WHEN** a guest searches a term that matches no visible node
- **THEN** the response 200 is rendered with an empty result list

#### Scenario: Not indexed
- **WHEN** a guest opens any search page
- **THEN** the document tells robots not to index it

#### Scenario: Excerpt is plain text
- **WHEN** a matching `richtext` field contains HTML tags
- **THEN** the excerpt of the result contains no tags and is not longer than `content.meta_description_max`
