# Spec Delta

## ADDED Requirements

### Requirement: Header search form submits to the search page
The search form of the header (and of its modal below 1024 px) SHALL be a `GET` form to `/search` with the text field `q`. Submitting it SHALL open the site search page with the entered term.

#### Scenario: Submitting the header form
- **WHEN** a guest types `lamp` into the header search field and submits the form
- **THEN** the browser opens `/search?q=lamp`

#### Scenario: Modal form
- **WHEN** a guest submits the form of the search modal on a narrow screen
- **THEN** the browser opens `/search?q=<entered term>`

### Requirement: Search results page in the theme
The theme SHALL provide the `search/results` template inside the shared page frame, with a heading, the search form that holds the current term, and either the result cards, a hint about the minimum term length (fewer than 3 characters), or an empty-state message when nothing matches. A result card SHALL show the title as a link, the section title of the node's type (for a page, none) on one line with the publication date (with a clock icon), and the plain-text excerpt. The paginator of the results SHALL use the theme's pagination and keep the term. The texts SHALL be translatable (`dcculture.search.*`) in Ukrainian and English.

#### Scenario: Results with the form
- **WHEN** a guest opens `/search?q=lamp` and a record matches
- **THEN** the page shows the form filled with `lamp` and a card with the record's title linking to it and its publication date

#### Scenario: Short term hint
- **WHEN** a guest opens `/search?q=ab`
- **THEN** the page shows the form and the hint about the minimum length instead of results

#### Scenario: Empty state
- **WHEN** a guest searches a term with no matches
- **THEN** the page shows the empty-state message and no cards
