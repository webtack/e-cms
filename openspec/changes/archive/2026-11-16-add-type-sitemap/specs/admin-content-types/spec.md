# Spec Delta

## ADDED Requirements

### Requirement: "Sitemap" tab of the type
The section menu of an existing type of kind `content` or `page` SHALL have a "Sitemap" tab (`/admin/content-types/{key}/sitemap`, `/admin/page-types/{key}/sitemap`) right after "SEO" and before "Fields", for a user with the `types` right, and the same destination SHALL be an item "Sitemap" of the row menu in the same position (the section menu and the row menu keep the same destinations). A type of kind `block` has no such tab, and `/admin/block-types/{key}/sitemap` SHALL answer 404 (also for saving). Opening or saving the tab without the `types` right SHALL answer 403. The tab SHALL be a separate page with **one** form and one sticky bar "Cancel / Save", with the type name as its heading above the tabs.

The form SHALL have two bordered blocks. The block "XML sitemap settings" SHALL have the checkboxes "Content type" (only for kind `content`) and "Content", the select "Change frequency" (`always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never`) and the select "URL priority" (`0.0` to `1.0` in steps of `0.1`). The block "Web sitemap settings" SHALL have the checkboxes "Content type" (only for kind `content`) and "Content". When the type has no stored settings, the checkboxes SHALL be off and the selects SHALL show `weekly` and `0.5`; stored settings SHALL be shown as saved. A type of kind `content` without a slug SHALL show a hint that it has no public address and so does not appear in the maps; saving stays possible. Saving (`PUT /admin/content-types/{key}/sitemap`, `/admin/page-types/{key}/sitemap`) SHALL store `settings.sitemap` by the rules of the capability `type-sitemap`, keep the other keys of `settings`, and answer with a success message; a validation error SHALL show at the field and save nothing. The saved tab SHALL show the same values after reload. A cloned type SHALL receive the sitemap settings of its source along with the other type settings.

#### Scenario: Menu of a content type
- **WHEN** the user opens any page of the type `news` of kind `content`
- **THEN** the section menu has "Settings", "Display", "SEO", "Sitemap", "Fields", "Nodes" in this order, and the row menu has "Sitemap" after "SEO"

#### Scenario: Page type
- **WHEN** the user opens the "Sitemap" tab of a page type
- **THEN** both blocks have only the "Content" checkbox and there is no "Content type" checkbox

#### Scenario: Block type
- **WHEN** the user opens `/admin/block-types/{key}/sitemap` or the block types menu
- **THEN** the address answers 404 and there is no "Sitemap" tab or item

#### Scenario: Without the types right
- **WHEN** a user without the `types` right opens `/admin/content-types/news/sitemap`
- **THEN** the response is 403

#### Scenario: Defaults of an unconfigured type
- **WHEN** the user opens the tab of a type with no sitemap settings
- **THEN** all four checkboxes are off, and the selects show `weekly` and `0.5`

#### Scenario: Saving and reopening
- **WHEN** the user turns on "Content type" in the XML block, picks `monthly` and `0.8`, turns on "Content" in the web block, and saves
- **THEN** a success message is shown, and after reopening the tab the same values are shown

#### Scenario: Type without a slug
- **WHEN** the user opens the tab of a `content` type without a slug
- **THEN** a hint says it has no public address and will not appear in the maps, and the form still saves

#### Scenario: Other settings survive
- **WHEN** the type has `settings.list` and `settings.public` and the user saves the tab
- **THEN** those keys are unchanged

#### Scenario: Clone copies the settings
- **WHEN** the type `news` has sitemap settings and is cloned as `press`
- **THEN** `press` has the same sitemap settings
