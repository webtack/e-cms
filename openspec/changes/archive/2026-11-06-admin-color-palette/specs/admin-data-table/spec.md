# Spec Delta

## ADDED Requirements

### Requirement: Boolean cell and header styling
By default the table SHALL show a boolean value as a text-only badge ("Yes" as a success badge, "No" as quiet muted text), translated into the interface language; a column MAY request a warning tone for "No". The table header row SHALL be shown on a faint tint of the accent colour, and a data row SHALL get a subtle highlight on hover. These changes SHALL not alter sorting, filtering or the filter values "yes/no".

#### Scenario: Default boolean cell
- **WHEN** a table has a boolean column without a custom cell
- **THEN** a true value is shown as a "Yes" badge and a false value as "No" text, and filtering by that column still offers the values "Yes" and "No"

#### Scenario: Warning tone for No
- **WHEN** a boolean column requests the warning tone and a row has the value false
- **THEN** that cell shows "No" as an amber badge

#### Scenario: Row hover
- **WHEN** the user moves the pointer over a data row
- **THEN** the row is subtly highlighted and its content does not shift
