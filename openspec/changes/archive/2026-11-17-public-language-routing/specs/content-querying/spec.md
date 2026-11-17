# Spec Delta

## ADDED Requirements

### Requirement: Sorting by a translated value with fallback
When a selection is made in the fallback mode for the public site, sorting by a field SHALL use, for each node, the value in the requested language and, when the node has none, its base-language value, so that nodes without a translation are ordered by their base value among the translated ones and do not all go to the end. Without the fallback mode the existing rule SHALL stay: nodes without a value in the requested language go after the others.

#### Scenario: Untranslated node among translated ones
- **WHEN** a list is sorted by title in English with fallback, node A has the English title "Beta", node B has no English title and the base title "Alpha"
- **THEN** B goes before A

#### Scenario: Without the fallback mode
- **WHEN** the same list is sorted without the fallback mode
- **THEN** B goes after A, as before
