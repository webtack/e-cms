## Context

`site.description` is the existing precedent for a multiline translatable setting: `SiteSettings::text()` reads it, `save()` merges it into the `default` key, `UpdateSiteSettingsRequest::LIMITS` caps it, and `Site.tsx` renders a `textarea`. Described in `docs/admin-site-settings.md` and `docs/app-settings.md`.

## Goals / Non-Goals

**Goals:** a translatable multiline `site.address` editable in the "Contacts" block.
**Non-Goals:** a language switcher, structured address fields, map/geocoding, public theme output.

## Decisions

- **Reuse the description path.** Store `site.address` as `{"default": "…"}`; add it to the key list handled by `text()` and `save()` (`['name','short_name','description','address',…]`). No migration; empty value removes the `default` key. Alternative (structured street/city/zip) rejected: the request asks for one textarea.
- **Limit 500 characters** in `LIMITS['address']`, passed to the page via `limits`. Assumption: an address is shorter than a description.
- **UI:** a `Label` + `Textarea` with `id="address"` placed after the phones `FieldGroup` in the Contacts `Section`, modelled on the description field. Label and translations `Address` in `lang/uk.json` and `lang/en.json` (check for an existing key first).
- **Prop shape:** `values.address` is a string, empty when absent.
