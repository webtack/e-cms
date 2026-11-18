# Proposal

## Why

A long text (`text`) in the record form is edited with a plain `Textarea`: no headings, lists, links or emphasis. Pages and articles need a field with formatting. The editor must be open-source only, and the stored content must be readable by any client without its own renderer (display on the site is the business of the client code, see `docs/content-values.md`).

## What Changes

- A new value type `richtext`: an HTML string in `content_values_text` (`LONGTEXT`), without a new table (as `image` and `file` in `content_values_string`).
- Server-side sanitization of the HTML by an allow-list of tags and attributes before saving (XSS); links only with safe schemes. The client HTML is not trusted.
- The boundary values of length `min`/`max` (if set in the `settings` of the field) are counted by the visible text, not by the HTML markup.
- The search (`contains`) and the text search over records work for `richtext`, as for `text`.
- A widget in the record form on **Tiptap** (MIT, `@tiptap/react` + `@tiptap/starter-kit`): a toolbar with headings, bold, italic, lists (bulleted and numbered) and a link. Without images and files (inserting from the media library is a separate future change).
- In the list of records the value of `richtext` is shown as plain text without tags (shortened), not as raw HTML.
- A value without visible text (`<p></p>`) is considered empty: a small shared hook by which the type defines an empty value.
- The form of creating/editing a field for `richtext` has no additional settings (as for `text`).

Not included: a change of the rules for changing the type of a field (a separate change), a block editor (it would duplicate `block-types`), inserting images and files, collaborative editing, Markdown, storing a JSON document.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-values`: the new type `richtext` (HTML in `content_values_text`), sanitization, length by the visible text, an empty value is defined by the type.
- `admin-content-nodes`: the input element `richtext` in the record form (Tiptap) and the display of the value without tags in the table of records.

## Impact

- Code (PHP): the new `app/Content/FieldTypes/RichTextType.php` and an HTML sanitization service, a row in `config/content.php` (`field_types`), `ContentValueWriter` and `FieldType` (the type can itself define an empty value: `<p></p>` = empty), `ContentNodeController` (the column type and the display of the value in the list), a `composer` dependency for sanitization (a candidate: `symfony/html-sanitizer`, MIT).
- Code (frontend): `Components/Admin/ContentNodes/NodeFieldInput.tsx` + a new editor component, `npm` dependencies: `@tiptap/react`, `@tiptap/pm`, `@tiptap/starter-kit`; translations `lang/*.json`.
- Tests: Feature tests of the type and sanitization; e2e `admin-content-nodes` (and `admin-content-fields` for the choice of the type).
- Documentation to be updated: `docs/content-values.md` (the registry of types, a section on `richtext` and sanitization), `docs/admin-content-nodes.md` (the widget), `docs/admin-content-fields.md` (the list of types), `docs/e2e-testing.md` (the scenarios); `docs/README.md` remains valid (no new files).
