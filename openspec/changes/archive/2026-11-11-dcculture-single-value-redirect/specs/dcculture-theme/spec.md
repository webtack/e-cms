# Spec Delta

## ADDED Requirements

### Requirement: Redirect from a node page to its single file or link
With the `dcculture` theme active, a request for the page of a visible node (the details of a `content` record or a `page`) SHALL answer with a temporary redirect to the node's file or link instead of rendering the page, when the node's visible fields hold exactly one non-blank value and that value belongs to a field of type `file` or `url`. The visible fields are those the type's public display settings show on the page; hidden fields SHALL NOT be considered. A multiple field whose list has exactly one non-blank item SHALL count as one value. The target SHALL be the address of the link for a `url` field and the public address of the stored file for a `file` field. The redirect SHALL apply to every visitor, including signed-in users. The theme's hook file SHALL hold two exclusion lists, one of content type keys and one of node uuids; a node whose type key or uuid is listed SHALL NOT be redirected, and with both lists empty the redirect SHALL apply to every node. The visit SHALL be counted in the node's view counter by the usual counting rules before the redirect is sent.

#### Scenario: Node with only a file
- **WHEN** a guest opens a visible record whose only filled visible field is a `file` field holding one file
- **THEN** the response is a temporary redirect to the public address of that file

#### Scenario: Node with only a link
- **WHEN** a guest opens a visible record whose only filled visible field is a `url` field holding one link
- **THEN** the response is a temporary redirect to the address of that link

#### Scenario: Page of kind page
- **WHEN** a guest opens a visible page (`/{slug}`) whose only filled visible field is a `url` or `file` field with one value
- **THEN** the response is a temporary redirect to that link or file

#### Scenario: Multiple field with one item
- **WHEN** the only filled visible field is a multiple `file` or `url` field holding exactly one non-blank item
- **THEN** the response is a redirect to that item

#### Scenario: Multiple field with several items
- **WHEN** the filled visible field is a multiple `file` or `url` field holding two or more non-blank items
- **THEN** no redirect happens and the page is rendered as usual

#### Scenario: Another visible field is filled
- **WHEN** a record has a filled `file` or `url` field and any other visible field of a different filled value (for example a text or a second file or link field)
- **THEN** no redirect happens and the page is rendered as usual

#### Scenario: Hidden field is ignored
- **WHEN** a record's file or link is the only value among the visible fields and another field that holds a value is hidden from the page by the type's public display settings
- **THEN** the redirect to the visible field's value happens
- **AND** when the only filled field is itself hidden, no redirect happens

#### Scenario: Unacceptable link
- **WHEN** the single `url` value is not an acceptable link (for example a `javascript:` address)
- **THEN** no redirect happens and the page is rendered as usual

#### Scenario: Excluded type
- **WHEN** the type key of a node that has a single file or link is in the type exclusion list
- **THEN** no redirect happens and the page is rendered as usual

#### Scenario: Excluded node
- **WHEN** the uuid of a node that has a single file or link is in the node exclusion list
- **THEN** no redirect happens and the page is rendered as usual
- **AND** other nodes of the same type are still redirected

#### Scenario: Visit is counted before the redirect
- **WHEN** a counted visitor opens a node page that redirects
- **THEN** the node's `views` increases by one and the visitor is redirected

#### Scenario: Invisible node
- **WHEN** a guest opens a disabled, scheduled or expired node that has a single file or link
- **THEN** the response is the site's 404 page, not a redirect

#### Scenario: List cards keep their address
- **WHEN** a list shows the card of a node that has a single file or link
- **THEN** the card links to the node's own address (`/{type}/{slug}`), which then redirects
