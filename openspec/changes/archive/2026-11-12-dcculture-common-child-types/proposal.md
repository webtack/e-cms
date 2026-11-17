# Proposal

## Why

The public page of the `common` type (`/common`) lists only its own three records. Its child types (`institutions`, `institutions_sources`, `festivals_contests`, `city_awards`, `cultural_heritage`) are reachable only through the menu, so a visitor on `/common` has no way to go down to them.

## What Changes

- The `dcculture` theme prints, under the list of `common` records, a second list of cards with the **direct child types** of `common` that have a public address. A card is styled like a record card (`.content-card`: dot marker, title link) and shows only the public title of the type, linked to `/{type slug}`.
- The titles are the public titles of the types (`section_title`, else the type name) and the cards are **sorted by that title**, in the Ukrainian collation order.
- The data comes from a new theme hook file (`hooks/common-children.php`, `preprocess('content.list')`), not from the core. The core controller and its variables stay unchanged.
- A dedicated template `common/list.blade.php` renders the page (candidate `{type}/list` is resolved before `content/list`); other types keep `content/list`.
- Only `common` is affected. No other type gets child cards; no tests (theme frontend rule).

## Capabilities

### New Capabilities

### Modified Capabilities
- `dcculture-theme`: a new requirement for the `/common` page: the child-type cards under the records list.

## Impact

- Theme code: `themes/dcculture/hooks/common-children.php` (new), `themes/dcculture/hooks.php` (one group added), `themes/dcculture/views/common/list.blade.php` (new), possibly a small partial for the type card.
- Core (`app/`): none.
- Docs: `docs/theme-dcculture.md` (Templates tree, a "Common page: child types" section, Hook files list).
- Verification: `php artisan theme:check`, theme build, a visual check of `/common` (no PHP or e2e tests for theme frontend).
