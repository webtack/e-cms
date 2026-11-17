# Spec Delta

## MODIFIED Requirements

### Requirement: Search results page
The results SHALL be paginated by `content.per_page` and the pagination links SHALL keep `q`. The page SHALL be rendered through the theme with the template candidate `search/results` and the hook `search`, inside the site shell, and SHALL ask search engines not to index it. The template SHALL receive the search term, the list of results (title, address, type kind, section title, publication date, plain-text excerpt, and the title and the excerpt split into parts that mark the occurrences of the term) and the paginator. A search with no matches SHALL answer 200 with an empty list.

#### Scenario: Paginated results
- **WHEN** more nodes match than fit on one page
- **THEN** the paginator links to the other pages contain the same `q`

#### Scenario: No matches
- **WHEN** a guest searches a term that matches no visible node
- **THEN** the response 200 is rendered with an empty result list

#### Scenario: Not indexed
- **WHEN** a guest opens any search page
- **THEN** the document tells robots not to index it

#### Scenario: Excerpt is plain text
- **WHEN** a matching `richtext` field contains HTML tags
- **THEN** the excerpt of the result contains no tags and is not longer than `content.meta_description_max`

## ADDED Requirements

### Requirement: Search excerpt around the match
The excerpt of a result SHALL show the place where the term was found. It SHALL be taken from the first text field of the node (in field order, the title excluded) whose plain text contains the term case-insensitively, and SHALL be a part of that text around the first occurrence, not longer than `content.meta_description_max` characters, cut at word boundaries, with "…" at each edge where the text continues. When no text field contains the term in its plain text (for example the node matched by its title only, or the database matched something the plain-text comparison does not), the excerpt SHALL be the start of the first non-empty text value, as before. A node without a non-empty text value other than the title SHALL have no excerpt.

#### Scenario: Match deep in a long text
- **WHEN** a record has a `body` of 1000 characters with the word `lamp` after the 500th character and a guest searches `lamp`
- **THEN** the excerpt of its result contains `lamp` and is not longer than `content.meta_description_max`, and begins and ends with "…"

#### Scenario: Match in a later field
- **WHEN** the first text field of a record does not contain the term and its second text field does
- **THEN** the excerpt is taken from the second field

#### Scenario: Match at the start of the text
- **WHEN** the term is in the first words of the text
- **THEN** the excerpt starts at the beginning of the text without a leading "…"

#### Scenario: Match in the title only
- **WHEN** a node matches by its title and no text field contains the term
- **THEN** the excerpt is the start of the first non-empty text value

#### Scenario: Multibyte text
- **WHEN** the text around the match holds multibyte letters
- **THEN** the cut never breaks a letter

### Requirement: Search result parts for highlighting
Every result SHALL carry its title and its excerpt additionally as a list of parts, each part holding a piece of the text and a flag telling whether the piece is an occurrence of the term. The parts in order SHALL give back exactly the plain title and the plain excerpt. Every case-insensitive occurrence of the term in the text SHALL be a separate flagged part. When the term does not occur in the text, or the text is empty, there SHALL be no flagged part. The parts SHALL hold plain text only, never markup, so that a template escapes them when it prints them.

#### Scenario: Occurrences in the title
- **WHEN** a page has the title "Lamp museum of the lamp" and a guest searches `lamp`
- **THEN** the title parts are "Lamp" (flagged), " museum of the " and "lamp" (flagged)

#### Scenario: Case is kept
- **WHEN** the text has the term in another case than the typed one
- **THEN** the flagged part holds the text as it is written in the node

#### Scenario: No occurrence
- **WHEN** the title does not contain the term
- **THEN** the title parts are one unflagged part equal to the title

#### Scenario: Literal characters
- **WHEN** a guest searches `100%` or a term with regular-expression characters such as `a.b` or `(x`
- **THEN** only the literal text is flagged and no error occurs

#### Scenario: Parts rebuild the text
- **WHEN** the parts of a title or an excerpt are joined
- **THEN** the result equals the plain title or the plain excerpt
