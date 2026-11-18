# Design

## Context

The header — `AdminHeader`; the form of a record — `ContentNodeForm` with the bottom `FormActionBar` (children on the left, «Скасувати/Зберегти» — "Cancel/Save" on the right). The public addresses: the page `/{slug}`, the record `content` `/{type.slug}/{node.slug}`, the blocks have no address (`docs/public-site.md`). `ContentNodeController::edit` gives `node` without a public address. See proposal.md.

## Goals / Non-Goals

**Goals:**
- One button in the header and one in the form of the node, both with `target="_blank"`.

**Non-Goals:**
- The viewing of drafts/scheduled records, language versions, a change of the public routes.

## Decisions

- **The address is computed by the server.** `edit` adds the prop `publicUrl` (`string|null`): `url('/'.$node->slug)` for `page`, `url('/'.$type->slug.'/'.$node->slug)` for `content`, `null` for `block`. This way the frontend does not duplicate the rules of the routes. The alternative — to assemble on the client from `slug` — was rejected: it needs the slug of the type and the knowledge of the kind.
- **A relative or an absolute address.** We use the relative path (`/about`), because the admin and the site are on one domain; it does not depend on `APP_URL`.
- **The address from the saved slug.** The prop is taken from the DB, so it does not react to the changes of the slug in the form before saving (after saving Inertia will update the prop).
- **A link, not an Inertia `Link`.** An ordinary `<a target="_blank" rel="noopener noreferrer">` inside `Button asChild`; the site is not an Inertia page.
- **The icon in the header.** `ExternalLink` (lucide) in `Button variant="ghost" size="icon"`, `aria-label`/`title` «Open site», next to the switch of the language, before it.
- **The placement of «Перегляд» ("Preview").** In `FormActionBar` on the left (children) together with the button «Деталі» ("Details"); on narrow screens it remains visible (the icon + text may be shortened to the icon with `aria-label`).

## Risks / Trade-offs

- For an invisible record «Перегляд» gives 404 — deliberately accepted (the simple variant); if it becomes inconvenient, a separate change will add a protected preview of drafts.
- A change of the slug without saving: the button leads to the old address until saving.
