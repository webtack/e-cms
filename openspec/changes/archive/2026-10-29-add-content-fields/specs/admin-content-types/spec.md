# Spec Delta

## MODIFIED Requirements

### Requirement: Row action menu
Each row SHALL have a "⋮" menu with the items "Edit", "Fields" and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated from the others). "Edit" SHALL open the edit form of this type, "Fields" — the fields page of this type, "Delete" — start the deletion with confirmation.

#### Scenario: Menu content
- **WHEN** the user opens the "⋮" menu in a type row
- **THEN** they see the active items "Edit", "Fields", "Delete" with translated labels

#### Scenario: Edit
- **WHEN** the user chooses "Edit" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Items are inactive
- **WHEN** the user chooses "Fields" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/fields` opens
