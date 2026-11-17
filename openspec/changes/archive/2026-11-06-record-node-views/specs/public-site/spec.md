# Spec Delta

## ADDED Requirements

### Requirement: Counting views of public node pages
The public site SHALL increase the view counter (`views`) of a node by one when a successful (200) response to a `GET` request is built for the page of that node: the details page of a node of kind `content` (`/{type.slug}/{node.slug}`) or a node of kind `page` (`/{slug}`). It SHALL NOT count type lists, blocks (they have no address), 404 responses, invisible nodes, or requests with a method other than `GET` (including `HEAD` and `OPTIONS`). A request SHALL NOT be counted when its `User-Agent` header is empty or contains (case-insensitively) a substring from the configurable list `content.views.bot_agents`, when the request is made by an authenticated user of any role, or when counting is disabled by `content.views.enabled`. A visitor, identified by the node, the IP address, and the `User-Agent`, SHALL be counted for a node at most once per period `content.views.ttl` (24 hours by default); the check SHALL be atomic, so that concurrent requests do not pass twice, and SHALL NOT use cookies. The increase SHALL be one atomic update that does not change `updated_at`, made after the response is built. A failure of the cache or of the update SHALL NOT change the response and SHALL only be logged.

#### Scenario: A visit to a content node
- **WHEN** a guest opens `/news/hello` of a visible node with `views` = 5
- **THEN** the response is 200, and `views` becomes 6

#### Scenario: A visit to a page
- **WHEN** a guest opens `/about` of a visible page
- **THEN** the `views` of that page increases by one

#### Scenario: Repeated visit of the same visitor
- **WHEN** the same visitor (the same IP and `User-Agent`) opens the same node twice within the period
- **THEN** `views` increases only once

#### Scenario: Another visitor or another node
- **WHEN** another visitor opens the same node, or the same visitor opens another node
- **THEN** that node's `views` increases by one for each

#### Scenario: The period has passed
- **WHEN** the same visitor opens the node again after the period `content.views.ttl`
- **THEN** `views` increases again

#### Scenario: Lists and blocks are not counted
- **WHEN** a guest opens `/news` (a type list)
- **THEN** no counter changes

#### Scenario: Not found and invisible nodes
- **WHEN** a guest opens the address of a disabled node, or an unknown address
- **THEN** the response is 404, and no counter changes

#### Scenario: A bot
- **WHEN** the `User-Agent` is `Googlebot/2.1`, or `curl/8.0`, or empty
- **THEN** the response is 200, and `views` does not change

#### Scenario: Not a GET request
- **WHEN** a `HEAD` request opens an existing node
- **THEN** `views` does not change

#### Scenario: An authenticated user
- **WHEN** a signed-in user (any role) opens a visible node
- **THEN** `views` does not change

#### Scenario: Counting is disabled
- **WHEN** `content.views.enabled` is false and a guest opens a visible node
- **THEN** `views` does not change

#### Scenario: The update time is kept
- **WHEN** a view is counted
- **THEN** `updated_at` of the node does not change

#### Scenario: A failure does not break the page
- **WHEN** the cache store or the counter update throws an error during a visit
- **THEN** the response is still 200 with the page, and the error is written to the log

### Requirement: Views in public templates
The node passed to the templates of the public site SHALL expose its counter as the integer attribute `views`, on the details page and on a page of kind `page`, and for the nodes of a type list (the cards). A page showing the counter SHALL show the value read before the current visit is counted, without a second query.

#### Scenario: A template reads views
- **WHEN** a theme template of `content/details` prints `node.views` for a node with 12 views
- **THEN** the value 12 is available as an integer

#### Scenario: Views in list cards
- **WHEN** a list template reads the `views` of the nodes of the paginator
- **THEN** each node has its own integer value

### Requirement: Public sorting by views
The public display settings of a type SHALL accept `views` as the sort field of the public list (`settings.public.sort.field`), as with the other service attributes, so that a type can show the most viewed nodes first.

#### Scenario: Popular nodes first
- **WHEN** a type has `settings.public.sort` = `{field: "views", direction: "desc"}` and three visible nodes with 3, 30, and 12 views, and a guest opens the type list
- **THEN** the nodes are shown in the order 30, 12, 3

#### Scenario: Saving the setting
- **WHEN** the display settings of a type are saved with the sort field `views`
- **THEN** the saving succeeds
