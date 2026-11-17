## ADDED Requirements

### Requirement: Contacts block on the pages of Internet resources
The theme SHALL render the block of the type `contacts` referenced by the field `contacts_reference` of a record of the type `institutions_sources` with a template of its own, separate from the one used by the other types that reference a `contacts` block (`institutions`, `collectives_*`). That template SHALL print only the social links of the block (the same icon links as the shared block), under a label that is the `title` of the block, and SHALL NOT print the address, e-mail, phones, schedule or map rows. The row SHALL NOT be printed when the block has no social links. The look of the shared contacts block on the pages of the other types SHALL NOT change.

#### Scenario: A page of Internet resources
- **WHEN** a guest opens a record of the type `institutions_sources` whose `contacts_reference` points to a visible `contacts` block with social links
- **THEN** the page shows the block rendered by the template of `institutions_sources`: the block's title as the label and the social icon links, without the address, e-mail, phone and schedule rows

#### Scenario: Other types keep the shared block
- **WHEN** a guest opens a record of the type `institutions` or `collectives_ckd` whose `contacts_reference` points to a visible `contacts` block
- **THEN** the page shows the contacts block as before, rendered by the shared template

#### Scenario: No social links
- **WHEN** the referenced block of an `institutions_sources` record has no social links
- **THEN** the page prints no social row for it
