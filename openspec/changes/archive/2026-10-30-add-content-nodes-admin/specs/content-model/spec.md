# Spec Delta

## ADDED Requirements

### Requirement: Purpose of the node slug and uuid
The `slug` of a content node SHALL be the public identifier of the node for the frontend of the site (page addresses, references from templates) and SHALL NOT be used as the identifier of the node in the admin and in internal references: they SHALL rely on the immutable `uuid`. A change of the slug SHALL NOT change the `uuid` and SHALL NOT break admin references to the node.

#### Scenario: Admin reference after a change of slug
- **WHEN** the slug of a node changes
- **THEN** the admin addresses of this node, built by the `uuid`, remain valid

#### Scenario: Public use of the slug
- **WHEN** the frontend needs to find a node by its public path
- **THEN** it looks up the node by slug, while the admin looks it up by `uuid`
