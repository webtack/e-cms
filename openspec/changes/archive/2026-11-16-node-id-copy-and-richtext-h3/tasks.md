# Tasks

## 1. Block style dropdown in the rich text editor

- [x] 1.1 In `RichTextToolbar.tsx` replace the "Heading" button with a `BlockStyleMenu` dropdown (Normal, Heading 2, Heading 3, Heading 4; modeled on `AlignMenu`; current style from `useEditorState`; `setParagraph()` / `setHeading({ level })` with focus kept in the editor; disabled in the "Source" mode), add JSDoc with `@example`, and add the keys (`Block style`, `Normal`, `Heading 2`, `Heading 3`, `Heading 4`) to `lang/en.json` and `lang/uk.json`; remove `Heading` if unused (`grep`). Verify with the e2e tests of step 1.2.
- [x] 1.2 In `e2e/admin-content-nodes.spec.js` replace the step that clicks `Heading` (the "clear formatting" test) with choosing "Heading 2" in the "Block style" dropdown, and add tests: choosing `h3` and `h4` and saving, switching `h2` → `h3`, back to "Normal", the dropdown shows the style at the cursor, and with an image selected no item is current. Verify: `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "heading|Heading|clear formatting|block style"`.
- [x] 1.3 Update the toolbar description in `docs/admin-content-nodes.md` (the "Block style" dropdown replaces the heading button) and verify no doc still describes a single heading button.

## 2. Copy-to-clipboard component, slug in the table

- [x] 2.1 Check `resources/js` for an existing toast/notification mechanism, then add `Components/Admin/CopyableText.tsx` (button, Clipboard API with `execCommand` fallback, confirmation, English JSDoc with `@example`) and the translation keys for the hint and confirmation in `lang/en.json` / `lang/uk.json`. Verify with the e2e test of step 2.3.
- [x] 2.2 In `Pages/Admin/ContentNodes/Index.tsx` render the service slug cell through `CopyableText` (full `row.slug` copied, ellipsis and `title` kept, no row action triggered). Verify visually and with step 2.3.
- [x] 2.3 Add e2e tests to `e2e/admin-content-nodes.spec.js`: clicking a slug puts the full slug in the clipboard (grant `clipboard-read`/`clipboard-write` to the context) and shows the confirmation; the list stays open. Verify: `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "slug"`. Also run `e2e/admin-data-table.spec.js` only if `DataTable` itself was touched (it is not planned).
- [x] 2.4 Update the table slug column description in `docs/admin-content-nodes.md` (click-to-copy, keyboard operation) and the component list if it names shared components.

## 3. Node id in the edit form

- [x] 3.1 In `ContentNodeController::edit()` add `'id' => $node->id` to `node`; add `id?: number` to `NodeMeta` in `types.ts`. Extend the existing Feature test of the edit page props (find it with `grep -rn "'author'" tests/Feature/Admin`) to assert `node.id`, and check that `create` has no `id`. Verify: `docker compose exec app php artisan test --compact --filter=<that test class>` and `docker compose exec app vendor/bin/pint --dirty --format agent`.
- [x] 3.2 In `NodeServicePanel.tsx` show the row "ID" with `CopyableText` when `meta.id` is defined (in the edit form only), update its JSDoc, and add the `ID` key to `lang/*.json`. Verify with step 3.3.
- [x] 3.3 Add e2e tests: the edit form shows the node `id` and a click copies it; the create form has no "ID". Verify: `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "node id|Details"`.
- [x] 3.4 Update `docs/admin-content-nodes.md`: the `node` props now include `id` on edit, the "Details" block lists "ID" (edit only, click to copy).

## 4. No paragraph inside a list item

- [x] 4.1 In `app/Content/HtmlSanitizer.php` add the DOM pass to `clean()` that unwraps the only attribute-less `p` of an `li` (several paragraphs, attributed paragraphs and nested lists handled per the spec), with PHPDoc and an `@example` in the class comment. Verify by the tests of step 4.2.
- [x] 4.2 Add tests to `tests/Feature/Content/HtmlSanitizerTest.php` for each scenario of the `content-values` delta (simple item, inline formatting kept, nested list, several paragraphs, aligned paragraph, visible text of `text()` unchanged, an already clean value stays the same). Verify: `docker compose exec app php artisan test --compact tests/Feature/Content/HtmlSanitizerTest.php` and `docker compose exec app vendor/bin/pint --dirty --format agent`; also run the value tests that use richtext (`--filter=RichText`) and `--filter=Legacy` because the legacy import cleans through the sanitizer.
- [x] 4.3 Add an e2e test to `e2e/admin-content-nodes.spec.js`: create a bulleted list in the editor, save, reopen, check the items are still list items and an item can be edited; check the stored HTML has no `li > p`. Verify: `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "list"`.
- [x] 4.4 Update `docs/content-values.md` (sanitization: `li > p` rule, earlier values not migrated) and, if it describes list markup, `docs/admin-content-nodes.md`; verify the links still resolve.

## 5. Pasting Word lists

- [x] 5.1 Get the clipboard HTML of the owner's sample (the plain text sample about the «Арлекін» news shows `•` plus four spaces and may carry no list markup; inspect what the browser really receives with a temporary `paste` listener logging `clipboardData.getData('text/html')`), then add `convertWordLists(html)` to `richTextUtils.ts` (levels, bullet vs. number detection, marker removal, grouping, nesting; English JSDoc with `@example`) and wire it as `transformPastedHTML` and `transformPastedText` in `RichTextEditor.tsx` (bullet glyph at the start of a paragraph or a plain-text line also makes a list item). Verify with the e2e tests of step 5.2 and by pasting the real sample by hand.
- [x] 5.2 Add e2e tests to `e2e/admin-content-nodes.spec.js` that dispatch a paste event with Word-shaped HTML: a bulleted list, a numbered list, a nested list, two lists split by a paragraph, the three `•    …` paragraphs of the owner's sample and the `1.    …`, `2.    …`, `3.    …` sample (as HTML paragraphs and as plain text) between ordinary paragraphs, and a lone `2026. …` paragraph and non-consecutive numbers stay text, a plain `ul` from a browser stays as before; assert no bullet characters or leading spaces in the editor text. Verify: `npm run test:e2e -- e2e/admin-content-nodes.spec.js -g "paste"`.
- [x] 5.3 Document the paste behavior in `docs/admin-content-nodes.md` (rich text editor section) and verify the cross-link to the sanitizer rule in `docs/content-values.md`.
