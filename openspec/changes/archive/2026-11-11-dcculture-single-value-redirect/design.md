# Design

## Context

See proposal.md — Why. Theme hooks (`ThemeHooks`, `preprocess` and `suggest`) cannot return a response; they run inside `ThemeManager::render()`, which `PublicNodeController::details()` and `page()` call through `respond()`. The controller calls `ViewCounter::record()` only after `render()` returns. The hook receives `$vars` with `node`, `type`, `values` and `fields`. `values` holds the values of all fields of the type (the controller calls `ContentValueReader::load()` without a field list), while `fields` holds only the fields of `page_fields` (`DisplayFields::pageFields()`), the ones the template shows.

Field types (`FieldTypeRegistry`) have `isBlank()`; `UrlType::present()` returns `null` for an unacceptable link; `file_url($value, $profile)` with the profile of the `file` type (`document`; global helper, `app/helpers.php`) turns `{uuid}.{ext}` into the public address without a database query.

Documented in: `docs/site-themes.md` (hooks), `docs/theme-dcculture.md` (theme), `docs/public-site.md` (routes, view counter), `docs/content-values.md` (`file`/`url` values and presentation).

## Goals / Non-Goals

**Goals:**
- Redirect from a node page to a single file or link, for `content` details and `page`, in the `dcculture` theme only.
- Keep the view counter correct.

**Non-Goals:**
- A new hook kind or any change to core, controllers, other themes, the admin panel or the schema.
- An editor bypass, caching of redirects, an admin-panel switch (the exclusions are code in the hook file).
- Changing list cards or `content.preview`.
- Redirect for `image`, `youtube` or other field types.

## Decisions

**1. One shared callback in `themes/dcculture/hooks/single-value-redirect.php` (loaded by `hooks.php`, which only lists the hook files), registered with `preprocess` on `content.details` and `page`.** Both hooks give `node`, `fields` and `values` in `$vars`, so one function serves them. Alternative: a new core hook kind that returns a redirect — cleaner, but it widens the scope into the core and tests, and the owner chose a theme-level solution.

**2. End the request with `throw new HttpResponseException(redirect()->to($target, 302))`.** Laravel turns it into the response from anywhere in the request. The status is 302: the editor may change or remove the value later, and a 301 would stay in browser caches. Alternative `abort(302)` is not applicable (no `Location`).

**3. Only the visible fields (`$vars['fields']`) take part (the owner's choice, variant B).** For each key in `fields`, read `values[key]`, take the items (a multiple field gives its list, otherwise one item) and keep the non-blank ones by the field type's `isBlank()`. Redirect only when exactly one non-blank item remains in total and its field is of type `file` or `url`. A filled hidden field neither triggers nor blocks the redirect. `title` and service attributes are not among `fields`, so they do not count. Alternative (all fields of the type) was rejected by the owner.

**4. The target.** For `url`: the link itself, only when `UrlType::isValid()` accepts it; no redirect otherwise (`UrlType::present()` needs a `PresentContext`, so it is not used; verified in code). For `file`: `file_url($value, $profile)` with the profile of the `file` field type (`document`; there is no profile named `file`). This avoids `PresentContext` and its file-name queries, which the redirect does not need.

**5. Count the view in the hook, before the redirect:** `app(ViewCounter::class)->record(request(), $node)`. The exception skips the controller's own `record()` call, so there is no double count. `record()` applies all the rules (bots, `count_authenticated`, `ttl`) and never throws. Not counted when the hook decides not to redirect (the controller counts as usual).

**6. Exclusions are two arrays at the top of the hook file.** `$excludeTypes` (content type keys, pages included) and `$excludeNodes` (node uuids). A node whose `content_type` or `uuid` is listed is never redirected; both lists empty means every node can be redirected. The lists add up. Alternative (an allow-list) was rejected by the owner: the default stays "redirect everywhere". Alternative (a setting in the admin panel) is out of scope.

**7. List cards are unchanged.** The card links to `/{type}/{slug}`, which counts the view and redirects. A direct link from the card would bypass the counter.

## Risks / Trade-offs

- [A redirect with an external `Location` is an open redirect by value of an admin-entered link] → the value comes from editors, `url` fields reject unacceptable schemes, and the card already links the same address.
- [The view is counted even when the target is unreachable] → accepted; the counter counts visits to the node.
- [A rich-text field whose value is only empty markup may be treated as non-blank and block the redirect] → follows the field type's `isBlank()`; fixing blank detection is out of scope.
- [The preview in the admin panel or other callers of the same hooks] → the hooks `content.details` and `page` are fired only by the public controller; to be confirmed by a search for the hook names during implementation.
- [An editor cannot open the node page on the public site] → accepted by the owner (no bypass).
