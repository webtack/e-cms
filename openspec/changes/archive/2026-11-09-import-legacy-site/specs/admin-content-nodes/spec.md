# Spec Delta

## ADDED Requirements

### Requirement: Saving a node with an unchanged legacy slug
The edit form of a node SHALL accept the slug that the node already has, even if it does not match the strict slug format (a slug kept from the legacy import), and the other changes SHALL be saved. A slug that is changed in the form SHALL still follow the strict format and the other slug rules. The create form SHALL NOT accept the legacy format.

#### Scenario: Editing a node with a legacy slug
- **WHEN** a node with the slug `volnovaha---misto-geroy-ukrayini` is edited and saved with a new title and the same slug
- **THEN** the changes are saved

#### Scenario: Changing a legacy slug to another legacy slug
- **WHEN** the slug of such a node is changed in the form to `a---b`
- **THEN** the change is not saved, and an error is shown next to the slug field

#### Scenario: Creating a node with a legacy slug
- **WHEN** the create form is submitted with the slug `a---b`
- **THEN** the node is not created, and an error is shown next to the slug field
