# Spec Delta

## MODIFIED Requirements

### Requirement: Search results page in the theme
The theme SHALL provide the `search/results` template inside the shared page frame, with a heading, the search form that holds the current term, and either the result cards, a hint about the minimum term length (fewer than 3 characters), or an empty-state message when nothing matches. A result card SHALL show the title as a link, the section title of the node's type (for a page, none) on one line with the publication date (with a clock icon), and the plain-text excerpt. In the title and in the excerpt every occurrence of the search term SHALL be highlighted with a visible mark, and the text of the node SHALL be shown escaped, never as markup. The paginator of the results SHALL use the theme's pagination and keep the term. The texts SHALL be translatable (`dcculture.search.*`) in Ukrainian and English.

#### Scenario: Results with the form
- **WHEN** a guest opens `/search?q=lamp` and a record matches
- **THEN** the page shows the form filled with `lamp` and a card with the record's title linking to it and its publication date

#### Scenario: Short term hint
- **WHEN** a guest opens `/search?q=ab`
- **THEN** the page shows the form and the hint about the minimum length instead of results

#### Scenario: Empty state
- **WHEN** a guest searches a term with no matches
- **THEN** the page shows the empty-state message and no cards

#### Scenario: Term highlighted in the card
- **WHEN** a guest searches `lamp` and a record has `lamp` in its title and in its excerpt
- **THEN** the card shows each occurrence of `lamp` in the title and in the excerpt highlighted, and the title is still one link to the record

#### Scenario: Markup in the text is not interpreted
- **WHEN** the matching text contains characters such as `<` or `&`
- **THEN** the card shows them as text and highlights only the term
