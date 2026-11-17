# Spec Delta

## MODIFIED Requirements

### Requirement: List of nodes of a content type
`GET /{type.slug}` SHALL serve the list of visible nodes of a type of kind `content` whose slug equals the whole path of the address; the slug may have several segments (`/common/institutions`). An unknown type slug SHALL give 404.

#### Scenario: List of a type
- **WHEN** the type `news` of kind `content` has the slug `news` and two visible nodes, and a guest opens `/news`
- **THEN** the response 200 contains both nodes and does not contain the invisible ones

#### Scenario: List of a type with a slug of several segments
- **WHEN** the type has the slug `common/institutions` and two visible nodes, and a guest opens `/common/institutions`
- **THEN** the response 200 contains both nodes and does not contain the invisible ones

#### Scenario: Part of a nested slug is not a list
- **WHEN** the type has the slug `common/institutions`, no type has the slug `common`, and a guest opens `/common`
- **THEN** the response is 404

#### Scenario: Unknown type
- **WHEN** a guest opens `/unknown`, and there is neither a page nor a type with such a slug
- **THEN** the response is 404

### Requirement: Details of a node of the content type
`GET /{type.slug}/{node.slug}` SHALL serve the visible node of this type by the node's slug; the type slug may have several segments (`/common/institutions/hello`), and the last segment of the address is the node slug. A node of another type, an invisible one, or a non-existent one SHALL give 404.

#### Scenario: Details of a visible node
- **WHEN** the type with the slug `news` has a visible node with the slug `hello`, and a guest opens `/news/hello`
- **THEN** the response 200 displays the node

#### Scenario: Details of a node of a type with a slug of several segments
- **WHEN** the type with the slug `common/institutions` has a visible node with the slug `hello`, and a guest opens `/common/institutions/hello`
- **THEN** the response 200 displays the node

#### Scenario: Node of another type
- **WHEN** the node `hello` belongs to the type with the slug `events`, and a guest opens `/news/hello`
- **THEN** the response is 404

#### Scenario: Node of another type with a nested slug
- **WHEN** the node `hello` belongs to the type with the slug `common/institutions`, and a guest opens `/common/institutions-sources/hello`
- **THEN** the response is 404

#### Scenario: Invisible node
- **WHEN** the node `hello` is disabled
- **THEN** `/news/hello` responds with 404

## ADDED Requirements

### Requirement: Resolution of an address of any depth
The public site SHALL resolve an address of one or more segments in this order: (1) with one segment, a visible node of kind `page` with this slug; (2) a type of kind `content` whose slug equals the whole address, shown as a list; (3) with two or more segments, a type whose slug equals the address without its last segment, and its visible node with the last segment as the slug; (4) otherwise the 404 page. A reserved site path SHALL be checked by the first segment of the address, and the service routes SHALL keep priority over the public ones. A type list SHALL win over a node when the same address could mean both. Addresses with an empty segment or with characters outside the slug format SHALL give 404.

#### Scenario: A type list wins over a node
- **WHEN** the type with the slug `common` has a visible node `institutions` (created before the collision rules), and another type has the slug `common/institutions`
- **THEN** `/common/institutions` shows the list of the type `common/institutions`

#### Scenario: A page with one segment
- **WHEN** a visible page has the slug `about`, and a guest opens `/about`
- **THEN** the response 200 displays this page

#### Scenario: No page for several segments
- **WHEN** a visible page has the slug `about`, and a guest opens `/about/team`
- **THEN** the response is 404, unless a type has the slug `about` with a visible node `team`

#### Scenario: Reserved first segment
- **WHEN** a guest opens `/admin/something` or `/storage/something-unknown`
- **THEN** the public site does not serve it (the service route or the standard response applies)

#### Scenario: Deep unknown address
- **WHEN** a guest opens `/no/such/page/at/all`
- **THEN** the response is the 404 page of the site

#### Scenario: Invalid segment
- **WHEN** a guest opens `/news//hello` or `/News_1/hello`
- **THEN** the response is the 404 page of the site
