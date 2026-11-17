# Spec Delta

## ADDED Requirements

### Requirement: Author avatar in the node list and form
The "Author" column of the node table and the read-only "Author" row of the node form SHALL show the author's avatar next to their name when the author has one, and their initials otherwise.

#### Scenario: Author with an avatar in the list
- **WHEN** a node's author has an avatar and the table shows the `author` column
- **THEN** the row shows that picture next to the author's name

#### Scenario: Author with an avatar in the form
- **WHEN** a user opens the edit form of a node whose author has an avatar
- **THEN** the "Author" row of the "Details" block shows the picture next to the name

#### Scenario: Author without an avatar
- **WHEN** the author of a node has no avatar
- **THEN** the list cell and the form row show the initials next to the name
