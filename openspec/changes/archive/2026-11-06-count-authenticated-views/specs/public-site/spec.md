## MODIFIED Requirements

### Requirement: Counting views of public node pages
The public site SHALL increase the view counter (`views`) of a node by one when a successful (200) response to a `GET` request is built for the page of that node: the details page of a node of kind `content` (`/{type.slug}/{node.slug}`) or a node of kind `page` (`/{slug}`). It SHALL NOT count type lists, blocks (they have no address), 404 responses, invisible nodes, or requests with a method other than `GET` (including `HEAD` and `OPTIONS`). A request SHALL NOT be counted when its `User-Agent` header is empty or contains (case-insensitively) a substring from the configurable list `content.views.bot_agents`, or when counting is disabled by `content.views.enabled`. Requests of authenticated users of any role SHALL be counted by default and SHALL be excluded only when `content.views.count_authenticated` is false (env `CONTENT_VIEWS_COUNT_AUTHENTICATED`, default true). Repeated requests of the same visitor SHALL be counted by default; when `content.views.ttl` (env `CONTENT_VIEWS_TTL`, seconds, default `0`) is greater than zero, a visitor, identified by the node, the IP address, and the `User-Agent`, SHALL be counted for a node at most once per that period, the check SHALL be atomic so that concurrent requests do not pass twice, and no cookies SHALL be used. The increase SHALL be one atomic update that does not change `updated_at`, made after the response is built. A failure of the cache (when `ttl` is greater than zero) or of the update SHALL NOT change the response and SHALL only be logged.

#### Scenario: A visit to a content node
- **WHEN** a guest opens `/news/hello` of a visible node with `views` = 5
- **THEN** the response is 200, and `views` becomes 6

#### Scenario: A visit to a page
- **WHEN** a guest opens `/about` of a visible page
- **THEN** the `views` of that page increases by one

#### Scenario: Repeated visit of the same visitor
- **WHEN** `content.views.ttl` is `0` (the default) and the same visitor (the same IP and `User-Agent`) opens the same node twice
- **THEN** `views` increases by two

#### Scenario: Another visitor or another node
- **WHEN** another visitor opens the same node, or the same visitor opens another node
- **THEN** that node's `views` increases by one for each

#### Scenario: The period has passed
- **WHEN** `content.views.ttl` is greater than zero and the same visitor opens the node again after that period
- **THEN** `views` increases again

#### Scenario: A repeated visit within a configured period
- **WHEN** `content.views.ttl` is 86400 and the same visitor (the same IP and `User-Agent`) opens the same node twice within the period
- **THEN** `views` increases only once

#### Scenario: Authenticated users excluded by configuration
- **WHEN** `content.views.count_authenticated` is false and a signed-in user opens a visible node
- **THEN** `views` does not change

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
- **THEN** `views` increases by one

#### Scenario: Counting is disabled
- **WHEN** `content.views.enabled` is false and a guest opens a visible node
- **THEN** `views` does not change

#### Scenario: The update time is kept
- **WHEN** a view is counted
- **THEN** `updated_at` of the node does not change

#### Scenario: A failure does not break the page
- **WHEN** the cache store (with `ttl` greater than zero) or the counter update throws an error during a visit
- **THEN** the response is still 200 with the page, and the error is written to the log
