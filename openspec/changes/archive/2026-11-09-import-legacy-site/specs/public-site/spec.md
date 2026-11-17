# Spec Delta

## ADDED Requirements

### Requirement: Public addresses accept legacy node slugs
The public site SHALL serve a visible node whose slug is in the legacy format (lowercase letters, digits, hyphens including repeated, leading or trailing ones, and underscores) at the same address as any other node, so that the addresses of the legacy site keep working after the import. The format of the part of an address SHALL still exclude uppercase letters, spaces, empty parts and any other characters, which give the 404 page. The slug of a type is not affected: it keeps its own format.

#### Scenario: Node with repeated hyphens
- **WHEN** the type with the slug `news` has a visible node with the slug `volnovaha---misto-geroy-ukrayini`, and a guest opens `/news/volnovaha---misto-geroy-ukrayini`
- **THEN** the response 200 displays the node

#### Scenario: Node with an underscore and a trailing hyphen
- **WHEN** visible nodes have the slugs `etno_wave-poltava` and `jam-session-`, and a guest opens their addresses
- **THEN** the response 200 displays each node

#### Scenario: Unsupported characters
- **WHEN** a guest opens `/news/Hello` or `/news/he llo`
- **THEN** the response is the 404 page of the site
