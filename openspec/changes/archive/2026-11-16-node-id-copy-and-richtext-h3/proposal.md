# Proposal

## Why

Editors need two heading levels in the rich text editor (the toolbar offers only `h2`, although the editor and the server already keep `h2`–`h4`); the owner asked for `h2`, `h3` and `h4`. Support and content work also needs the node's identifier at hand: it is not visible on the form, and the slug in the node table cannot be copied without selecting text by hand.

## What Changes

- The `richtext` toolbar replaces the single "Heading" button with one dropdown "Block style" with the items "Normal" (paragraph), "Heading 2" (`h2`), "Heading 3" (`h3`) and "Heading 4" (`h4`); the button shows the current block style.
- The edit form of a node shows the numeric database `id` of the node in the "Details" block; clicking it copies the `id` to the clipboard and confirms the copy. The create form shows no `id` (the node does not exist yet).
- The service `slug` column of the node table becomes click-to-copy: a click on the slug copies its full value to the clipboard and confirms the copy.
- Cleaning of `richtext` values stops storing a paragraph inside a list item: `<li><p>Item</p></li>` is saved as `<li>Item</li>` (only for values saved from now on; earlier values are not migrated).
- Pasting lists copied from Word (`.docx`) into the `richtext` editor produces real nested `ul` / `ol` lists instead of paragraphs with typed bullets and spaces.
- The server passes the node `id` to the edit form (`node.id`); the node table row already carries the slug.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `content-values`: new requirement — no `p` inside `li` when a `richtext` value is cleaned.
- `admin-content-nodes`: toolbar block-style dropdown (Normal, `h2`, `h3`, `h4`), display and copying of the node `id` in the form, click-to-copy of the slug in the node table, pasting Word lists.

## Impact

- Code: `app/Http/Controllers/Admin/ContentNodeController.php` (`edit` adds `id` to `node`), `app/Content/HtmlSanitizer.php` (unwrapping `li > p`), `resources/js/Components/Admin/ContentNodes/RichTextToolbar.tsx`, `RichTextEditor.tsx` and `richTextUtils.ts` (Word list conversion on paste), `NodeServicePanel.tsx`, `types.ts` (`NodeMeta.id`), `resources/js/Pages/Admin/ContentNodes/Index.tsx`, a small shared copy-to-clipboard helper/component in `resources/js`.
- Translations: `lang/en.json`, `lang/uk.json` (new keys for the buttons, the `ID` label, the copy hints and confirmations).
- Tests: `tests/Feature/Content/HtmlSanitizerTest.php`, the Feature test of the edit page props, the e2e spec `e2e/admin-content-nodes.spec.js` (the existing step that clicks "Heading" is renamed).
- Docs: `docs/content-values.md` (sanitization), `docs/admin-content-nodes.md` (toolbar, `node` props of the form, table slug column), `docs/e2e-testing.md` only if a new spec appears (none planned).
