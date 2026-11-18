# Design

## Context

The field-type registry (`config/content.php` → `FieldType`) allows adding a type without changes to the core; `image` and `file` already use a foreign table through `table()`. `ContentValueWriter` first normalizes the values (`''` → `null`, drops empty list items), then validates by the type's `rules()`, then stores `prepare()`. The field form does not accept `settings` for non-file types (`settingsRules` → `prohibited`), so `min`/`max` for `text` can be set only programmatically. The value widget is chosen in one place — `NodeFieldInput.tsx`. The motivation and the boundaries are in `proposal.md`; the requirements are in `specs/`.

## Goals / Non-Goals

**Goals:**
- `richtext` as one more registry type with minimal intervention in the core.
- Security: a single authoritative sanitization layer on the server.
- The editor is built into the existing record form (the field frame, the label, errors, multiple fields).

**Non-Goals:**
- Toolbar settings in `settings` (the `settings` keys for `richtext` remain forbidden, as for `text`).
- Inserting images, tables, code blocks; the media library; Markdown; collaboration.
- Rendering on the site: the type gives the sanitized HTML.

## Decisions

**1. Format: HTML in `content_values_text`.** No new table or migration: `RichTextType::table()` returns `content_values_text`. Reading, multiplicity, translations, the filter `eq`/`contains` work as for `text`. Alternative — a JSON document of Tiptap/ProseMirror: more precise, but it requires a renderer in every client, breaks `contains` and length. Rejected.

**2. Sanitization: `symfony/html-sanitizer` (MIT) in a separate service `App\Content\HtmlSanitizer`.** The allow-list is set in the configuration of the service: the tags `p br h2 h3 h4 strong em u s blockquote ul ol li a`, the attribute `href` for `a` with the schemes `http/https/mailto/tel`, `rel="noopener noreferrer nofollow"`, relative links are allowed; `img` and everything else is dropped. The service has `clean(string): string` and `text(string): string` (the visible text). Why not `ezyang/htmlpurifier`: heavier, needs a cache directory, an old API. Why not sanitize only on the client: the client can be bypassed with a direct request. The package version will be confirmed by `composer require` (compatibility with Laravel 13 / Symfony); if it is incompatible, the fallback is `htmlpurifier`, the behaviour according to the specification does not change.

**3. Where to sanitize: in `prepare()`, and validate in `rules()`.** `rules()` returns `string` + a closure for the length by `HtmlSanitizer::text()` (`min`/`max` from `settings`, if set). `prepare()` returns `clean()` — what goes to the DB. So on reading the value is always clean, and the core knows nothing about sanitization. Alternative — remove only on output: then an unsafe HTML would lie in the DB and every client would have to sanitize itself. Rejected.

**4. An empty value — a small shared hook.** `<p></p>` is not `''`, so `normalize()` in `ContentValueWriter` would not clear it, and `required` would let it pass. We add to `FieldType` a method `isBlank(mixed): bool` (in `AbstractFieldType` by default `$value === null || $value === ''`, in `RichTextType` — there is no visible text; nested `img`/links are not needed, because `img` is forbidden), and `ContentValueWriter::normalize` calls it with the field type (for a list — for each item). Alternative — only the client sends `''` for an empty editor (`editor.isEmpty`): works for the admin, but not for the API/seeder. We do both: the hook on the server is the guarantee, the client `''` is a convenience. The change to the core is minimal and additive (the behaviour of other types does not change); it is described in the specification as an exception.

**5. Editor: Tiptap 3 (`@tiptap/react`, `@tiptap/pm`, `@tiptap/starter-kit`; MIT).** `StarterKit` already contains Link and Underline (confirmed by the Tiptap documentation), so no additional packages are needed. The component `RichTextEditor` in `Components/Admin/ContentNodes/`: `useEditor` with `StarterKit` (`codeBlock`, `code`, `horizontalRule`, `strike` disabled as needed, headings h2–h4 according to the allow-list), `onUpdate` → `onChange(editor.isEmpty ? '' : editor.getHTML())`. The toolbar on the existing `Button`s, icons from `lucide-react`; the link — a `Popover` with an address field. The styles of the editing areas — Tailwind (`prose`-like rules locally, without the typography plugin). Controlled mode: `value` is synchronized with the editor only when it differs from `getHTML()`, so as not to lose the cursor. `aria-label`/`id` and `aria-invalid` are passed to the editable element (`editorProps.attributes`), so that `<label htmlFor>` and e2e work.
Alternatives: Lexical (more manual work, HTML separately), Plate/Slate (JSON), BlockNote/Editor.js (block-based, duplicate `block-types`), CKEditor/TinyMCE (licenses).

**6. The list of records: text without tags.** For the column `richtext` the controller converts the value to plain text through `HtmlSanitizer::text()` and shortens it (for example, to 200 characters with "…"). The column type stays `text` (the fallback `COLUMN_TYPES`), sorting is not available, as for `text`.

**7. The type in the field form.** `richtext` appears in the list of types automatically (from the registry); the word "Rich text" gets into `lang/*.json`. `settings` are forbidden for it, as for `text`.

## Risks / Trade-offs

- [XSS through bypassing the sanitization] → one authoritative layer on the server in `prepare()`, an allow-list, tests for `script`, `onclick`, `javascript:`, `data:`, nested tags; the value is read already cleaned.
- [Distrust of data stored before the sanitizer appeared] → the new type has no old data; changing the type of a field `text` to `richtext` is the subject of a separate change about the immutability of the field type (see its artifacts), this change does not touch the rule.
- [The search `contains` matches tags and entities (`&nbsp;`)] → acceptable for the first iteration, recorded in the documentation; a full-text index by the visible text — separately.
- [The length by the visible text is more expensive than `strlen`] → computed only when `min`/`max` are set.
- [The change in the core (`isBlank`)] → additive, the default keeps the old behaviour; covered by the existing `ContentValueWriterTest` plus new ones.
- [The `@tiptap/*` types and `typescript ^7`/`React 19`] → compatibility is confirmed by `npm install` and `npm run build` (`tsc --noEmit`) in the first task; the package is installed with `--legacy-peer-deps` according to `docs/e2e-testing.md`.
- [The cursor "jumps" with a controlled value] → synchronization only on a difference in HTML, covered by e2e (typing several characters in a row).

## Migration Plan

There are no DB migrations. Dependencies: `composer require symfony/html-sanitizer` in the `app` container, `npm install --legacy-peer-deps @tiptap/react @tiptap/pm @tiptap/starter-kit` on the host, `npm run build`. Rollback: remove the row `richtext` from `config/content.php` (fields of this type will stop being valid — before the rollback they are deleted) and the dependencies.

## Documentation

`docs/content-values.md` (the registry, the table of types, a section on `richtext` and sanitization), `docs/admin-content-nodes.md` (the widget and the list), `docs/admin-content-fields.md` (the list of types), `docs/e2e-testing.md` (the scenario and the spec tables).
