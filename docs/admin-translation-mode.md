# Translation mode in the admin panel

One behavior for editing the translations of any admin entity: **one language at a time, on the edit page of the entity**. Terms and menu items use it ([admin-taxonomy.md](admin-taxonomy.md), [admin-menus.md](admin-menus.md)); nodes, type values and field labels use it too ([admin-content-nodes.md](admin-content-nodes.md#translation-mode), [admin-content-types.md](admin-content-types.md), [admin-content-fields.md](admin-content-fields.md)); site settings and file descriptions adopt the same contract later. The storage rules (what a translation row holds, fallback) belong to the domain docs: [taxonomy.md](taxonomy.md#translations), [menus.md](menus.md#translations), [content-languages.md](content-languages.md).

## Contract

- **Switcher.** Shown above the form of an *existing* entity when multilingual mode is on and at least one additional language is active. It lists the base language first (labelled "Base"), then the active languages by weight; a language with a stored translation carries a marker. It is never shown on create pages, with multilingual mode off or with no active additional language.
- **Address.** The language is the query parameter `lang`. No parameter, or the code of the base language (`ContentLanguages::baseLang()`), is the base mode. The code of an active additional language is the translation mode. Anything else (unknown, inactive, or any additional language while multilingual mode is off) answers **404**. Choosing a language opens the same page with the matching parameter.
- **Fields.** Only the translatable fields are editable (term: name, description; menu item: title, description). The form keeps its base layout: the shared attributes (slug, parent, weight, address, image, dates, …) stay where they are, shown `inert` and dimmed with their base values, because moving a field between blocks confuses users; the translation routes do not accept them. Saving the base form never creates, changes or deletes a translation.
- **Prefill.** Each field shows its stored translation, otherwise the base value as an editable starting value. The server merges the values; nothing is saved by opening the page.
- **Saving.** Only by the "Save" button, disabled while the form equals its initial state. Only the current language is changed. A value equal to the base value of the same field is not stored, an empty value removes its field, and the row disappears when nothing remains. After a save the user stays on the same entity and language with the message "Translation saved." Fields are never required.
- **Clearing.** "Clear translation" (offered only when a translation exists) asks for confirmation, deletes the translation of the current language and returns to the same page with the base values.
- **Unsaved changes.** Switching language with a modified form asks for confirmation ("Stay" / "Discard changes"); a clean form switches at once.
- **Access.** The same right as the base edit page (`can:taxonomy`, `can:menus`): no extra right, the same 403 for the translation page and its save and clear routes.
- **"Translated" marker.** An entity is translated into a language when it has at least one stored value in it (stored values are the ones that differ from the base). One definition feeds the switcher and any list that shows translation status.

## Routes and props

For every adopting entity:

| Request | Purpose |
| --- | --- |
| `GET .../{entity}/edit[?lang=xx]` | Base mode, or the translation mode of `xx`. |
| `PUT .../{entity}/translations/{lang}` | Saves one language (body: the translatable fields). Redirects to the edit page with `?lang={lang}`. |
| `DELETE .../{entity}/translations/{lang}` | Clears one language; same redirect. |

Terms: `/admin/taxonomy/{vocabulary}/terms/{term}/...` (`admin.taxonomy.terms.translations.save` / `.clear`). Menu items: `/admin/menus/{menu}/items/{item}/...` (`admin.menus.items.translations.save` / `.clear`).

Site settings: `/admin/settings?lang=xx`, `PUT`/`DELETE /admin/settings/translations/{lang}` (`admin.settings.translations.save` / `.clear`) under `can:settings`; the page is a single entity without an id, and its logos, indexing flag and the identity of contact and social rows stay in place, inert ([admin-site-settings.md](admin-site-settings.md#translation-mode)).

Nodes: `/admin/{section}/{contentType}/nodes/{node}/...` (`admin.{section}.nodes.translations.save` / `.clear`, `section` is `content-types`, `page-types` or `block-types`); the right is the node's edit right (`Gate::authorize('update', $node)`). Their values go through `TranslationSaver` and the marker comes from `TranslatedLanguages` ([content-values.md](content-values.md#saving-one-language)); for a node `translation.values` is keyed by field key and the list page gets the Translations column and the `untranslated` filter.

The edit page receives the Inertia prop `translation`, `null` when no switcher is shown:

```
translation: {
  lang: string | null,        // null in the base mode
  baseLang: string,
  languages: [{ code, name, translated }],
  values: { ...translatable fields } | null,   // prefilled values in the translation mode
  exists: boolean             // a stored translation exists in `lang`
} | null
```

The shared entity attributes and the base-language values (`term.name`, `item.title`, …) travel in the entity prop as before. The create pages receive no `translation` and no language list.

## Server pieces

- `Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTranslationLanguage` — `resolveTranslationLang($request)` (null = base, 404 otherwise), `activeTranslationLang($code)` for the `{lang}` route parameter, `translationProp(...)` builds the prop.
- `TermWriter` / `MenuItemWriter` — `saveTranslation($entity, $lang, $data)` and `clearTranslation($entity, $lang)` (rules in the domain docs). Validation errors come back as errors of the fields.
- `SiteSettings` — `translationValues($lang)`, `saveTranslation($lang, $data)`, `clearTranslation($lang)` and `hasTranslation($lang)`; request `SaveSiteSettingsTranslationRequest`.
- Form requests `SaveTermTranslationRequest`, `SaveMenuItemTranslationRequest` — both fields optional.

## Frontend pieces

- `Components/Admin/Translation/LanguageSwitcher.tsx` — the tabs with markers (`data-testid="language-switcher"`, `language-tab-{code|base}`, `translated-marker-{code}`) and the unsaved-changes dialog.
- `Components/Admin/Translation/ClearTranslationButton.tsx` — the "Clear translation" button with its confirmation dialog.
- `hooks/useDirtyGuard.ts` — `go(href)` / `confirm()` / `cancel()` over a `dirty` flag.
- `types/translation.ts` — `TranslationState`.

Entity forms with shared attributes (`TermForm`, `MenuItemForm`, `ContentNodeForm`) are one form in both modes: they render the switcher above the form, lock what is not translated and put `ClearTranslationButton` into their own `FormActionBar` (`saveDisabled` until a value changes). The edit pages key the form by language, existence and prefilled values, so a switch or a save remounts it with fresh initial values.

## Adopting the mode for a new entity

1. Add `saveTranslation`/`clearTranslation` to the entity's writer (store only differing, non-empty values; delete the row when nothing remains).
2. Use `ResolvesTranslationLanguage` in the controller: call `resolveTranslationLang()` in `edit`, return `translationProp($lang, $translatedCodes, $values, $exists)`, and add the `PUT`/`DELETE` translation routes under the entity's existing permission.
3. In the form, render `LanguageSwitcher` above it. If the entity has shared attributes, keep one form and lock them in the translation mode (never move fields between blocks); a form with nothing but translatable fields is the same one form, so the rule is the same.
4. Add e2e coverage next to the existing specs and list the consumer in the "Shared code → specs" table of [e2e-testing.md](e2e-testing.md).
