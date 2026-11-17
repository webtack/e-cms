# Content values

Values of node fields are stored in typed tables `content_values_{type}` (EAV). The structure is in [content-model.md](content-model.md), languages in [content-languages.md](content-languages.md), querying in [content-querying.md](content-querying.md).

## Field type registry

The registry `Webtack\Ecms\Content\FieldTypeRegistry` is built from `config/content.php` (`field_types`: key → class). The class implements `Webtack\Ecms\Content\FieldTypes\FieldType` (usually extends `AbstractFieldType`):

| Method | Purpose |
| --- | --- |
| `key()` / `table()` | the key in `content_fields.type` and the value table (`content_values_{key}`) |
| `rules(ContentField)` | validation rules for one value, taking the field's `settings` into account |
| `prepare($v)` / `cast($v)` | value → DB column / DB column → PHP type |
| `operators()` | allowed filter operators: `eq`, `gt`, `gte`, `lt`, `lte`, `between`, `contains` |
| `searchable()` | whether the type takes part in text search |
| `isBlank($v)` | whether one incoming value is considered empty (it is cleared or dropped from the list); by default `null` or `''` |
| `present($v, ContentField, PresentContext)` | the public representation of one value for a theme template — [Public presentation](#public-presentation) |

Built-in types:

| Key | `value` column | PHP type | Settings (`settings`) |
| --- | --- | --- | --- |
| `string` | `VARCHAR(512)` | `string` | length `min`, `max` (`max` ≤ 512) |
| `text` | `LONGTEXT` | `string` | length `min`, `max` |
| `richtext` | `LONGTEXT` in `content_values_text` | `string` (sanitized HTML) | length `min`, `max` of the **visible text** — [Rich text](#rich-text) |
| `integer` | `BIGINT` | `int` | value `min`, `max` |
| `decimal` | `DECIMAL(20,6)` | `string` (canonical, without trailing zeros: `12.50` → `12.5`) | `min`, `max`; more than 6 decimal places — an error, no rounding |
| `boolean` | `TINYINT(1)` | `bool` | — |
| `date` | `DATE` | `CarbonImmutable` | date `min`, `max` |
| `datetime` | `DATETIME` | `CarbonImmutable` | moment `min`, `max` |
| `image` | `VARCHAR(512)` in `content_values_string` | `string` `{uuid}.{extension}` | `mimes`, `max_size`, `max_width`, `max_height` — [Images and files](#images-and-files) |
| `file` | `VARCHAR(512)` in `content_values_string` | `string` `{uuid}.{extension}` | `mimes`, `max_size` — [Images and files](#images-and-files) |
| `youtube` | `VARCHAR(512)` in `content_values_string` (link) + `options` | `array` `{url, video_id, title, description, screen}` | no settings — [YouTube videos](#youtube-videos) |
| `url` | `VARCHAR(512)` in `content_values_string` (link) + `options` | `array` `{url, title}` | no settings — [Links](#links) |
| `term` | `VARCHAR(512)` in `content_values_string` (the term's `uuid`) | `string` (`uuid`) | `vocabulary` — the vocabulary key, required — [Term](#term-term) |
| `type_ref` | `VARCHAR(512)` in `content_values_string` (the type's `key`) | `string` (`key`) | `kind` — the kind of types, required — [Type reference](#type-reference-type_ref) |
| `node_ref` | `VARCHAR(512)` in `content_values_string` (the node's `uuid`) | `string` (`uuid`) | `content_type` — the key of the node type, required — [Node reference](#node-reference-node_ref) |

### How to add a new type

1. The `FieldType` class (for example, `ColorType extends AbstractFieldType`: `key()`, `baseRules()`, if needed `prepare`/`cast`/`operators`/`isBlank`; `isBlank` is needed only if the empty value of the type is not `null` or `''`, like `<p></p>` in `richtext`).
2. A migration of the table `content_values_{key}` with the [shared contract](#value-table-schema), in particular with `entity` and nullable `content_node_id`/`content_node_uuid` (the value owner — a node or a type).
3. A row in `config/content.php` → `field_types`. The core (writing, reading, querying) does not change; in tests a type can be added on the fly via `FieldTypeRegistry::register()`.
4. Optional type capabilities (separate interfaces): `TracksFiles` (file tracking), `ComposedValue` (a scalar + `options`), `ValidatesSettings::settingsErrors()` (checking the field's `settings` on save — `ContentField` calls it for a new field and when `settings` change; errors go under the key `settings.<name>`), `LocksReferences::lockReferences()` (locking references in the node write transaction), `PreloadsReferences::preload()` (batch reading of references for `present`), `ExpandsFilterValues` (expanding a filter value — [content-querying.md](content-querying.md#operators-by-type)).
5. If a value consists of a scalar and additional data, the type also implements `ComposedValue` — see [Composed values](#composed-values-and-options).

## Value table schema

Shared columns of each `content_values_*` table:

| Column | Meaning |
| --- | --- |
| `entity` | the value owner: `node` (a node, the default) or `type` (the type itself) |
| `content_node_id` | the node (`content_nodes.id`); `NULL` for type values |
| `content_node_uuid` | a copy of the node's `uuid` (exporting values without a JOIN); filled from the node; `NULL` for type values |
| `content_type` | the type's `key`; for node values it **matches the node's type**, for type values it is the owning type itself |
| `field_key` | the field's `key`; a field = the pair (`content_type`, `field_key`), there is no `field_id` column |
| `lang` | `default` or the normalized language code, binary collation |
| `weight` | the order of values of a multiple field |
| `value` | a value of the native type, `NOT NULL` (a missing value = a missing row) |
| `options` | JSON for additional value settings (not for translations) |

Integrity is ensured by the database:

- a composite FK (`content_type`, `field_key`) → `content_fields(content_type, key)`, `cascade`: a value of a non-existent field is rejected, deleting a field deletes its values;
- a composite FK (`content_node_id`, `content_type`) → `content_nodes(id, content_type)`, `cascade`: **the value's type must match the node's type**, and therefore the field belongs to the node's type; deleting a node deletes its values;
- `CHECK`: `entity = 'node'` requires `content_node_id` and `content_node_uuid`, `entity = 'type'` requires both columns to be empty, so a contradictory row is rejected by the DB;
- uniqueness (`content_node_id`, `content_type`, `field_key`, `lang`, `weight`) for nodes;
- a functional unique index `cv_{type}_owner_unique` on (`COALESCE(content_node_id, 0)`, `content_type`, `field_key`, `lang`, `weight`) for both owners. A plain unique does not limit type values, because `NULL` does not equal `NULL`; the expression replaces `NULL` with `0` (real node `id`s start from 1).

The composite FK to `content_nodes` is not checked for a type row (when one of the columns is `NULL`, the FK is skipped), while the FK to `content_fields` applies to both owners: deleting a field or a type deletes type values.

### Value owner

A value belongs to a node or to the type itself (metadata and section: `meta_*`, `section_*`). Existing rows are node rows (`entity = 'node'`). The migration `add_owner_entity_to_content_values_tables` changes all seven tables; on MySQL — `ALTER TABLE`, on SQLite — a table rebuild preserving the rows (SQLite cannot add a `CHECK`). The rollback deletes type values.

**Query rule:** a query "all values of a field" not tied to a node (for example, whether a field has any values) must explicitly choose the owner via `entity`. Queries for node listing, filters, sorting, and search join by `content_node_id` and do not see type values.

The index for filters and sorting: (`content_type`, `field_key`, `lang`, `value`); for `text` — without `value`. The `string` index takes ≈ 2624 of 3072 bytes in InnoDB: do not increase the lengths of `field_key`, `lang`, `value` without re-checking the limit.

## Composed values and options

A type value may consist of a scalar (the `value` column) and additional data (JSON in the `options` column of the same row). Such a type implements `Webtack\Ecms\Content\FieldTypes\ComposedValue`:

- `split($input): array` — from one validated input value, gives `[scalar, ?options]`;
- `compose($scalar, ?array $options): mixed` — assembles the composed value from the row on reading.

`ContentValueWriter::replace` for such a type calls `split()` instead of `prepare()` and writes `value` and `options` (`NULL` if there is no additional data); `ContentValueReader::load` selects `options` and calls `compose()` instead of `cast()`. Types without `ComposedValue` work as before; their `options` stays `NULL`. `options` is replaced and deleted together with the value (languages, multiplicity, and clearing are the same).

**List vs composed value.** A composed value is an associative array, so for a type with `ComposedValue` the Writer considers the value a list only when `array_is_list`. A single-value field accepts one associative array and rejects a list; a multiple field requires a list of composed values (the `list` rule) and rejects one value. For other types the rule "array = list" does not change.

Filters and sorting work only on the `value` column; `options` does not take part in them.

## Creating a node and its slug

A node is created by `Webtack\Ecms\Content\ContentNodeCreator::create($type, $values, ?$slug, $lang)` — the single path, because `slug` is required and `title` is among the values. In a transaction: validation of values → determining the slug → inserting the node → storing the values.

- An explicit `slug` is checked (format, the `block-` prefix for blocks, uniqueness) and is not changed; a taken one → `ValidationException`.
- `content`/`page`: the slug from `title` in `default` (`Str::slug($title, '-', 'uk')` transliterates Cyrillic: `About us` → `about-us`; without transliteration an empty result); on a match, the suffixes `-2`, `-3`; for `page`, a match also includes the slug of a `content` type and a reserved path (`PublicPaths`): `Admin` → `admin-2`; an empty result → `node`. No prefix is added: the title `Block 1` gives the slug `block-1`.
- `block`: `block-` + 8 random lowercase letters `a–z`, repeated on a match.
- Changing `title` later does not change the slug. The unique index is the final arbiter: on a race, the automatic slug is generated again once.

## Translations

A translation is a separate value row with a different `lang`; `default` holds the base values. Languages are independent: writing one does not change others, and **there is no fallback to `default` by default** (a missing translation is read as `null` / `[]`). The public site asks for the fallback explicitly: see [Reading with fallback](#reading-with-fallback).

Writing a value with `lang ≠ default` is allowed when the field is `is_translatable`, multilingual is enabled, and the language is in `app_languages` and active ([rules](content-languages.md#language-validity-rules)). `lang` is normalized (`pt-br` → `pt-BR`).

### Saving one language

`Webtack\Ecms\Content\TranslationSaver` is the operation the admin translation mode uses (and any code that saves a translation form). It sits on top of the writer, which stays strict:

```php
use Webtack\Ecms\Content\TranslationSaver;
use Webtack\Ecms\Content\ValueOwner;

$saver = app(TranslationSaver::class);

$saver->save($node, 'en', ['title' => 'Hello', 'body' => '']);                 // stores the title, removes the body translation
$saver->save(ValueOwner::forType($news), 'en', ['section_title' => 'News']);   // values of a type
$saver->clear($node, 'en');                                                     // removes every `en` value of the node
```

- Only translatable fields the owner holds are accepted; anything else is a `ValidationException` under that key and nothing is stored. `lang` follows the usual rules (multilingual on, the language listed and active) and must not be the base language (`default`).
- Each submitted value is validated by its field type, but **required is not enforced**: an empty value means "use the base value" and removes the translation of that field.
- A value equal to the base value of the same field is not stored (and an existing translation that now equals it is removed). Values are compared as the field type stores them (`FieldType::prepare()`, `split()` for composed values); a multiple field is compared as a whole list, in order.
- Other fields and other languages are untouched; the save is one transaction.

### Translated in a language

A node, or a type's own values, is *translated* into a language when at least one value of it is stored in that language. `Webtack\Ecms\Content\TranslatedLanguages` answers it without a query per node:

```php
$translated = app(\Webtack\Ecms\Content\TranslatedLanguages::class);

$translated->forNodes([5, 6]);                                         // [5 => ['en'], 6 => []]  (one UNION ALL query)
$translated->forType($news);                                           // ['en', 'pl']  (both tabs)
$translated->withoutTranslation(ContentNode::query(), 'pl');           // the same query without nodes that have a `pl` value
```

## Writing

```php
use Webtack\Ecms\Content\ContentValueWriter;

$writer = app(ContentValueWriter::class);

$writer->write($node, ['title' => 'Hi', 'tags' => ['b', 'a', 'c']]);   // base language
$writer->write($node, ['title' => 'Hello'], 'en');                          // translation
$writer->write($node, ['visits' => null, 'tags' => []]);                      // clear fields
```

- Only the specified fields in the specified language are replaced; other fields and languages do not change. Everything is done in one transaction after successful validation.
- The row's `entity`, `content_type`, and `content_node_uuid` are taken from the owner, not from the input data.
- `null`, `''`, or `[]` clear a field; empty list elements are dropped.
- An unknown key, a non-translatable field in another language, a list in a single-value field → `ValidationException` with errors under the field keys.

### Type values

The owner of a node is a `ContentNode` (shorthand for `ValueOwner::forNode($node)`); the owner of the type's own values is `ValueOwner::forType($type)`:

```php
use Webtack\Ecms\Content\ValueOwner;

$writer->write(ValueOwner::forType($news), ['section_title' => 'News', 'meta_image' => $file->path]);
$writer->write(ValueOwner::forType($news), ['section_title' => 'News'], 'en');
```

The owner determines which fields can be written: according to the [list of system fields](content-model.md#system-fields), type values have only `meta_*` (kinds `content` and `page`) and `section_*` (only `content`), while node values have `title`, `meta_*`, and regular fields. A field that does not belong to the owner (`title` or a regular field for a type, `section_*` for a node or for a type of kind `page`, any field for a type of kind `block`) is rejected by `ValidationException` with an error under the field key, and nothing is stored. Languages, translations, normalization, clearing empty values, and validation of the value type are the same as for a node. Files of type values (`meta_image`, `section_cover`) are tracked as relations with the owner `content_type`: [media-library.md](media-library.md).

## Validation

Rules are built by the registry from the type and the field's `settings` (a multiple field: `key` — `array`, and with `max_items > 0` also `max:{max_items}` — no more than the limit of non-empty items; `key.*` — the item rules). Requiredness is checked in the `default` language: for all required fields when a node is created (including `title`) and for each required field that is written later. In other languages requiredness is not required.

## Multiplicity

A multiple field stores a list where `weight` is the index of the item within the language; reading returns the same order (`["b", "a", "c"]`). A single-value field has at most one value per language.

## Reading

```php
use Webtack\Ecms\Content\ContentValueReader;

$values = app(ContentValueReader::class)->load($nodes, ['title', 'price'], 'en');
$values[$node->id]['title'];   // "Hello" or null
```

One query per value table (and per content type), with no query per node: the number of queries does not depend on the number of nodes. `fieldKeys = null` — all fields of the node (without fields that belong only to the type); an unknown key or a type field key (`section_*`) → `ValidationException`. The language is checked by the reading rules (`assertReadable`). Reading nodes takes only rows `entity = 'node'`.

### Type values

```php
$values = app(ContentValueReader::class)->loadForType($news, ['section_title', 'meta_image'], 'en');
$values['section_title'];   // "News" or null
```

`loadForType` returns the type's own values for one language: `field key → value` (a missing one — `null`, for a multiple field — `[]`). **The base language is not substituted** for a missing translation. `fieldKeys = null` — all fields whose values have the type (for `content`: `meta_*` and `section_*`; for `page` and `block` the list is empty). An unknown key or a field that does not belong to the type (`title`, regular fields) → `ValidationException` under the key `fields`. Type values are not mixed with node values: it is a separate method and a separate set of rows.

## Public presentation

A theme template does not know the storage format: the method `FieldType::present($value, ContentField $field, PresentContext $context)` turns the raw value from `ContentValueReader` (`{uuid}.{ext}` for a file, the term's `uuid`, the composed array for youtube) into ready-to-output data. It is called for ONE item (a multiple field — for each item); `null` means "do not show", and such an item is dropped from the field's `$items` list. `AbstractFieldType::present()` returns the value as is, so `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime` override nothing. A new type gets its public representation by overriding `present()`; the render core does not change ([theme-templates.md](theme-templates.md#fields)).

| Type | `present()` returns |
| --- | --- |
| `image`, `file` | `['url', 'name', 'description']`: the public URL of the file (`FileStorage::urlForValue`), its `original_name` and `description` (`null` if empty); `null` if the value is not `{uuid}.{ext}`. A file without a row in `media` keeps the URL, and `name` is the stored `{uuid}.{ext}` |
| `richtext` | the HTML, sanitized by `HtmlSanitizer` once more (protection against values written outside the API) |
| `youtube` | `['id', 'url' (watch address), 'embed' (https://www.youtube-nocookie.com/embed/{id}), 'title', 'description', 'screen' (URL or null)]`; `null` if `video_id` is not 11 characters `[A-Za-z0-9_-]` |
| `url` | `['url', 'title']`; `null` for an address that fails `UrlType::isValid` (for example, `javascript:`) |
| `term` | the term's name in the reading language; `null` if the translation in this language is missing (it is not replaced by another language) |
| `node_ref` | `['uuid', 'title', 'url']` of a visible node (`uuid` — the key of the target; the field renderer adds `content`, the output of the target's block template or card, see [theme-templates.md](theme-templates.md#node-reference-content); `url` — `/{slug}` for a page or `/{type.slug}/{slug}` for a `content` node, `null` for a block); `null` for an invisible or deleted node |
| `type_ref` | `['key', 'name', 'url']`; `url` — `/{slug}` only for the `content` kind; `null` if the type does not exist |

Types with references (`term`, `node_ref`, `type_ref`, `image`, `file`) implement `PreloadsReferences`: before rendering a page or list, the renderer passes all values to them, and `PresentContext` reads terms, target nodes (together with their types and titles), types, and file names and descriptions with one query per kind, not per value. `PresentContext` is a scoped service: its cache lives for one request.

```php
$context = app(PresentContext::class);
$type = app(FieldTypeRegistry::class)->get('node_ref');
$type->preload($uuids, $field, $context);                 // one batch of queries
$type->present($uuids[0], $field, $context);              // ['uuid' => '0190…', 'title' => 'Story', 'url' => '/news/story']
```

All examples in the table are checked by `tests/Feature/Theme/PresentTest.php`, including a constant number of queries for a list of values.

## Rich text

The `richtext` type (`Webtack\Ecms\Content\FieldTypes\RichTextType`) stores an HTML fragment in `content_values_text` (via `table()`), with no new table and no migration. Reading, multiplicity, translations, and the operators `eq`/`contains` work as for `text`; the admin widget is the [Tiptap editor](admin-content-nodes.md#richtext-field).

- **Format.** An HTML string that any client can read without its own renderer. Display on the site is the job of the client code.
- **Sanitization.** `prepare()` returns `Webtack\Ecms\Content\HtmlSanitizer::clean()` (`symfony/html-sanitizer`), so only sanitized HTML is in the DB, and on reading the same is returned. Allowed tags: `p`, `br`, `h2`–`h4`, `strong`, `em`, `b`, `i` (pasted bold and italic, kept as written without attributes; the editor rewrites them to `strong` / `em` on the next save), `u`, `s`, `blockquote`, `ul`, `ol`, `li`, `span`, tables (`table`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`, see below), `a[href]` (schemes `http`, `https`, `mailto`, `tel` and relative addresses; `rel="noopener noreferrer nofollow"` is added forcibly), and `iframe` (see below). `img`, `figure`, `figcaption` (see below). Scripts, event handlers, and other tags and attributes are removed; the text of unwrapped tags (`div`, `h1`, `h5`, `h6`, `pre`, `caption`…) remains. Sanitization happens on the server regardless of the widget: the client is not trusted.
- **No paragraph inside a list item.** After the allow-list pass `HtmlSanitizer::clean()` runs a DOM pass (`unwrapListParagraphs()`, `Dom\HTMLDocument`, only when the markup has an `li` followed by a `p`): a `p` that is the only `p` child of its `li`, has no attributes and sits in an `li` without loose text is replaced by its children, so `<li><p>One</p></li>` is stored as `<li>One</li>` (the editor serializes every item with a `p`; on opening, ProseMirror wraps the item text into a paragraph again, so editing is unaffected). Inline markup and a nested list move into the `li` unchanged. An `li` with several paragraphs, or with an aligned paragraph (`style`), keeps them. The visible text is unchanged: `text()` also separates the start of a nested `ul` / `ol` from the preceding text. Values saved earlier are **not** migrated; they get this form when they are saved again. The editor side of lists, including pasting from Word, is in [admin-content-nodes.md](admin-content-nodes.md#richtext-field).
- **`style` (`span`, `p`, `h2`–`h4`, `blockquote`, `li`).** The attribute passes through `Webtack\Ecms\Content\StyleAttributeSanitizer` and keeps only `text-align` (`left`, `center`, `right`, `justify`), `color`, and `background-color` with a value `#rgb`, `#rrggbb`, or `rgb(r, g, b)`. Everything else (`url(...)`, `expression(...)`, `position`, color names) is dropped, and a `style` without any declaration is removed. On other tags (`strong`, `em`, `ul`, `ol` …) `style` is removed entirely.
- **Input length.** The sanitizer reads up to `content.richtext.max_input_length` characters (`config/content.php`, default 1,000,000). The library's own default of 20,000 would silently cut the end of a long text, such as a large table.
- **Tables.** `table`, `thead`, `tbody`, `tfoot`, `tr`, `th` and `td` stay; the only kept attributes are `colspan` and `rowspan` on `th` and `td`, and they pass through `Webtack\Ecms\Content\TableSpanSanitizer` (a whole number from 1 to 100, `03` becomes `3`; any other value removes the attribute and the cell stays). `style`, `width`, `align`, `valign`, `class` and event handlers of table tags are dropped, so pasted Word tables carry no fixed widths; a paragraph inside a cell keeps its own `text-align`. `caption`, `colgroup` and `col` are unwrapped (the text of a caption stays as it is), because the editor has no such nodes and would drop them on the next save.
- **Links.** `a[href]` also passes through `Webtack\Ecms\Content\LinkHrefSanitizer`: an address that starts with `//` (external, looks relative) or contains a backslash loses the `href`, the link text stays (the same rule as the `url` field). `/about`, `#top`, `?page=2`, `http(s)`, `mailto:` and `tel:` are kept.
- **`iframe`.** Only the attributes `src`, `width`, `height`, `title`, `allowfullscreen`, `allow` remain. `src` passes through `Webtack\Ecms\Content\IframeSrcSanitizer`: only `https`, no login in the address, no dot segment (`.`, `..`), backslash or percent-encoded dot, slash or backslash (`%2e`, `%2f`, `%5c`, any case) in the path (checked as written, so `/embed/../account` cannot escape the prefix), the host must match exactly an entry of the key `richtext.iframe_hosts` in `config/content.php` (suffix matches such as `youtube.com.evil.example` do not pass), and an entry with a path prefix (`www.facebook.com/plugins/`) additionally requires the path to start with it. `allow` is cleaned by `Webtack\Ecms\Content\IframeAllowSanitizer` to the names from `richtext.iframe_allow` (without `camera`, `microphone`, etc.). The server forcibly sets `sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"` and `loading="lazy"`. An `iframe` without an acceptable `src` is removed entirely. The default list covers YouTube, Vimeo, Google Drive and Docs, Google Maps (`www.google.com/maps/embed`), Facebook plugins, Instagram, Twitter, TikTok and Telegram. The list of hosts is provided to the client in the shared Inertia props (`richtext.iframeHosts`), so that the editor checks the address by the same rule.
- **Images.** `img` remains only with the attributes `src`, `alt`, `width`. `src` passes through `Webtack\Ecms\Content\ImageSrcSanitizer`: the only accepted form is a relative address of a storage file `{public path of the public disk}/{32 hex}.{extension}` (by default `/storage/{uuid}.png`; the prefix is taken from `Storage::disk('public')->url('')`); absolute addresses (`http://host/storage/...`), protocol-relative (`//host/...`), `data:`, addresses with a query, and any other paths remove the tag entirely (as does an `img` without `src`). `width` passes through `Webtack\Ecms\Content\ImageWidthSanitizer`: an integer from 1 to 4000, otherwise the attribute is removed and the image remains; `height` is forbidden, so the height is proportional. A caption is stored as `<figure><img><figcaption>…</figcaption></figure>`; `figcaption` has no attributes, and `figure` has only `style` with four kinds of properties (`StyleAttributeSanitizer`): `text-align` (`left`, `center`, `right`, `justify`), `width` (`{N}px` 1–4000, `fit-content`, and `min-content` only for nodes saved earlier), `max-width` only `100%`, and `margin-left` / `margin-right` only with the values `auto` and `0`. This is how the alignment of an image is kept together with its caption: `figure` has the width of the image (`width: {width}px; max-width: 100%`, and without a set width — `fit-content`; `min-content` does not fit, because a percentage `max-width` on `img` squeezes it to the longest word of the caption), so a long caption wraps within the image width, and the `auto` margins shift it (both — center, only left — right; left without margins), with the caption centered inside: `<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center"><img width="500"></figure>` (without a caption also in `figure`; "left" without a caption — a bare `img`). A previously saved `<figure style="text-align: center">` without margins still passes sanitization. Other values (`width: 9999px`, `width: 50%`, `margin-left: 50px`, `position`, `color`) and any `style` on `img` are removed; `width` and margins are not allowed on other tags. The sanitizer configuration enables `allowRelativeMedias()` (the built-in Symfony check would otherwise strip the relative `src`), and the narrower check is performed by `ImageSrcSanitizer`.
- **Image files.** `RichTextType` implements `TracksFiles`: `fileUuids()` first cleans the value (a string or a list of a multiple field), finds `img[src]`, and returns the unique `uuid`s. The rule in `rules()` requires that each `uuid` exists in `media` with the `image` profile; otherwise it is a field error and nothing is written (without this, `media_relations` would fail on the foreign key). The upload profile is set by `RichTextType::uploadProfile()` (`image`). Relations with the media library: [media-library.md](media-library.md#images-in-richtext).
- **Empty value.** `isBlank()` checks for the presence of visible text (`HtmlSanitizer::text()`: without tags, entities decoded, a non-breaking space is not counted, blocks, table cells and rows separated by a space) and the absence of an embedded frame (`HtmlSanitizer::hasFrame()`) and of an image (`HtmlSanitizer::hasImage()`): a value with only an `iframe` or `img` is content. The caption (`figcaption`) is part of the visible text (`text()` puts a separator after `</figcaption>`): search and `min`/`max` take it into account, while the image itself does not. So `<p></p>` clears an optional field, is rejected by a required one, and is dropped from the list of a multiple field. `ContentValueWriter` calls the field type's `isBlank()` for the value and for each list item; for other types nothing has changed (`null` or `''`).
- **Length.** `min`/`max` from the field's `settings` are counted in characters of the visible text, not of the markup (`<p><strong>Hello</strong></p>` = 5). The field `settings` form does not accept them for non-file types, so the limits are set programmatically.
- **Search.** `contains` and text search look through the stored HTML, so they also match tags and entities (`&nbsp;`). A full-text index by visible text is separate.
- **Node table.** The value is shown as plain text without tags, truncated to 200 characters (`ContentNodeController`, [admin-content-nodes.md](admin-content-nodes.md#value-in-the-row)).

## Term (`term`)

The `term` type (`Webtack\Ecms\Content\FieldTypes\TermType`) references a term of [taxonomy](taxonomy.md). The value is the term's `uuid` (lowercase), in `content_values_string`; there is no separate table or migration. The field belongs to the content type, the values belong to nodes; the field can be multiple (with `max_items`) and translatable (a value in a language is a separate row that also references a term). Reading returns the string — the `uuid`; term names and the tree are provided by `Webtack\Ecms\Taxonomy\TermTree`.

**Settings.** `settings.vocabulary` — the key of an existing vocabulary, required (`ValidatesSettings`): missing or non-existent → `settings.vocabulary`; changing the vocabulary of a field that has values is rejected, while for an empty field it is allowed.

**Value validation.** A UUID-format string; the term exists and belongs to the field's vocabulary; otherwise an error under the field key. Filters check the value by a field that is empty: the UUID format and the term's existence are enough.

**Integrity without FK.** `content_values_string` has no foreign key to terms. `ContentValueWriter::replace` for a type with `LocksReferences` calls `lockReferences()` in the write transaction: `lockForShare` on the referenced terms (a vanished term → a field error). Deleting a term locks its row with `lockForUpdate` and checks usage, so simultaneous writing and deletion are serialized: [taxonomy.md](taxonomy.md#deletion).

**Admin.** The field form offers the `term` type with the vocabulary selection ([admin-content-fields.md](admin-content-fields.md#term-field-and-vocabulary)), the node form — term selection ([admin-content-nodes.md](admin-content-nodes.md#term-field)), and terms and vocabularies are managed in the [Taxonomy](admin-taxonomy.md) section.

## Type reference (`type_ref`)

The `type_ref` type (`Webtack\Ecms\Content\FieldTypes\TypeRefType`) references a content type. The value is the type's `key`, in `content_values_string`; there is no separate table or migration. The field can be multiple (with `max_items`) and translatable. Reading returns the string — the `key`; the type's name and the edit page address are provided by `Webtack\Ecms\Reference\ReferenceLabels` (the label is never stored, so it is always current).

**Settings.** `settings.kind` — one of `config('content.kinds')` (`content`, `page`, `block`), required (`ValidatesSettings`); other `settings` keys are forbidden. Missing or unknown → `settings.kind`; changing the kind of a field that has values is rejected, while for an empty field it is allowed. The type is available across all kinds of types (unlike `term`).

**Value validation.** A string (up to 64 characters) that is the `key` of an existing type of the **specified kind**; otherwise an error under the field key. An empty value clears the field. Filters (`eq`, `in`) check the value by a field that is empty: the existence of a type of any kind is enough.

**Integrity without FK.** `lockReferences()` (`LocksReferences`) takes `lockForShare` on the referenced types in the write transaction. A type that any `type_ref` value points to (except the type's own values, which are deleted together with it) **is not deleted**: the check is in `ContentType::deleting` via `Webtack\Ecms\Reference\ReferenceUsage::forType()` (`ValidationException`, and the admin panel shows in advance a message listing the places).

**Admin.** The field settings — [admin-content-fields.md](admin-content-fields.md#type_ref-and-node_ref-fields); the widget in the node form, the search endpoint, the link label and sorting in the table — [admin-content-nodes.md](admin-content-nodes.md#type_ref-and-node_ref-fields).

## Node reference (`node_ref`)

The `node_ref` type (`Webtack\Ecms\Content\FieldTypes\NodeRefType`) references a node. The value is the node's `uuid` (lowercase, `prepare()`), in `content_values_string`; there is no separate table or migration. Multiplicity, translations, and clearing with an empty value work as for `type_ref`.

**Settings.** `settings.content_type` — the `key` of an existing type (of any kind), required; other keys are forbidden. Missing or unknown → `settings.content_type`; changing the type of a field that has values is rejected.

**Value validation.** A UUID-format string; the node exists and belongs to the type from `settings.content_type`; otherwise an error under the field key. Filters (`eq`, `in`): the UUID format and the node's existence are enough. Searching for nodes that reference a given node is supported (`eq` by `uuid`).

**Public presentation.** `present()` returns `['uuid', 'title', 'url']` of a visible target (`null` for an invisible or deleted one), see [Public presentation](#public-presentation); the field renderer adds `content`, the HTML of the target's page fields ([theme-templates.md](theme-templates.md#node-reference-content)).

**Integrity without FK.** Locking references and the prohibition on deletion are as for `type_ref`: a node that any `node_ref` value points to (except the node's own values) is not deleted (`ContentNode::deleting`, `ReferenceUsage::forNode()`); a type whose nodes are referenced in `node_ref` values of **other** types is also not deleted (`ReferenceUsage::forType()`).

**Sorting.** The node list column is sorted by the target's label: the type's `name` for `type_ref`, the `title` of the `default` language of the target for `node_ref` (by the first value; nodes without a value last in both directions); see [content-querying.md](content-querying.md#sorting) and [admin-content-nodes.md](admin-content-nodes.md#type_ref-and-node_ref-fields).

## YouTube videos

The `youtube` type (`Webtack\Ecms\Content\FieldTypes\YoutubeType`) is the first use of a [composed value](#composed-values-and-options). There are no new tables or field settings.

**Value** — an associative array `{url, video_id, title, description, screen}`. In the `value` column (`content_values_string`, up to 512 characters) lies the link `url`; in `options` — `video_id` (always) and non-empty `title` (up to 255 characters), `description` (up to 10000), and `screen`. Reading returns all five keys; missing ones are `null`. The input can also be a string (a link or an `id`).

**Link.** It is stored as it was entered (after trimming spaces), together with its parameters, including the timecode `t`. Accepted: `watch?v=`, `youtu.be/`, `embed/`, `live/`, `shorts/` on the hosts `youtube.com`, `www.`, `m.`, `music.`, `youtube-nocookie.com`, `youtu.be`. The only conversions: Shorts (`/shorts/{id}`) and a bare 11-character `id` become `https://www.youtube.com/watch?v={id}` (the other Shorts parameters are kept). Channels, playlists, and `watch` without `v` are rejected. Parsing is done by the public `YoutubeType::parse()`, which is also used by the [data retrieval request](admin-content-nodes.md#fetching-video-data).

**`video_id`** is always determined from the link during writing; the value passed in the input is ignored.

**Screenshot** — `{uuid}.{extension}` of a file of the `image` profile (the `FileValue` rule). The type implements `TracksFiles` (`fileUuids()` distinguishes one composed value from a list via `array_is_list`) and `HasUploadProfile` (the `image` profile for [uploading](file-storage.md#file-upload)). The relation to the screenshot in `media_relations` is created, replaced, and removed together with the value ([usage relations](#images-and-files)).

**Filter.** Only `contains` is supported (by the video's `id`, nodes with any form of the link are found); `eq` is rejected, because one video has different links. Text search (`searchable`) is disabled.

**Network.** Saving does not go to YouTube; the data is fetched only by a separate admin request.

## Links

The `url` type (`Webtack\Ecms\Content\FieldTypes\UrlType`) is the second use of a [composed value](#composed-values-and-options). There are no new tables or field settings (`settings` is forbidden, as for `youtube`).

**Value** — an associative array `{url, title}`. In the `value` column (`content_values_string`, up to 512 characters) lies the link, in `options` — a non-empty `title` (up to 255 characters). An empty title or one consisting of spaces is not stored (`options = NULL`) and is read as `null`. The input can also be a bare string. A value without a link is considered empty, even if the title is filled in (a required field then gives an error).

**Link** is stored as it was entered, after trimming spaces at the edges. The rule is the public `UrlType::isValid()`: no whitespace or control characters and no backslash (browsers read `/\evil.com` as `//evil.com`), and either (a) relative: starts with `/` (but not `//`), `./`, `../`, `#`, `?`; or (b) absolute `http`/`https` with a non-empty host; or (c) `mailto:` / `tel:` with a non-empty part after the colon. Other schemes (`javascript:`, `data:`, `ftp:`, etc.) are rejected.

**Filters.** `eq`, `in`, and `contains` by the link. Text search (`searchable`) is disabled. Sorting by the field in the node list is disabled ([admin-content-nodes.md](admin-content-nodes.md#url-field)).

**Network.** Saving does not contact the network and does not check that the link opens.

## Images and files

The types `image` and `file` (`ImageType`, `FileType`, the shared base `AbstractFileFieldType`) store a reference to a file from the [file storage](file-storage.md). There is no separate value table: the `table()` of both is `content_values_string`, so reading, the filter (`eq`), multiplicity, and translations work as for a short string; the general mechanism of writing, reading, and querying is unchanged.

**Value** — a string `{uuid}.{extension}` (`0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png`), that is, the same as the file's `media.path`.

**Profile ceiling.** The type defines the upload profile: `image` → `image`, `file` → `document`. The profile is the upper bound of the rules; the field only narrows it in `content_fields.settings`:

| Setting | Value |
| --- | --- |
| `mimes` | The list of allowed types — a subset of the profile's `mimes` |
| `max_size` | Maximum size, KB — not more than the profile's `max_size` |
| `max_width`, `max_height` | Only for `image`: maximum width and height, px — not more than the profile's `max_width`/`max_height`, if it sets them; a larger image is downscaled on upload ([ImageResizer](file-storage.md#image-processing-imageresizer)) |

Without settings the whole profile applies. The rules are computed by `Webtack\Ecms\Files\UploadRules` ([`forProfile`, `forField`](file-storage.md#uploadrules)): the intersection of types, the smaller of the sizes and of the pixel limits; foreign types, an oversized limit, and invalid bounds in `settings` are ignored; executable files and SVG are never allowed (even in the "extended" profile).

**Check on write** (`Webtack\Ecms\Files\Rules\FileValue`) is authoritative: the value must have the form `{uuid}.{extension}`, the file must exist, have the field's profile type, the extension must match `media.path`, the type must be among the field's allowed types, and the size must be within the field's `max_size`. An arbitrary string (`../secret.png`) is rejected with an error for this field. One file may pass in one field and fail in another if the settings differ. The filter (`ContentNodeQuery`) checks only the form of the value.

**Changing settings does not touch what is stored.** Narrowing the types, size, or pixels does not change or delete existing values; they are read as is; the new rules apply on the next write.

**The field type** can never be changed (including between `string`, `image`, and `file`), regardless of whether values exist: see [content-model.md](content-model.md#deletion-cascades).

**Usage relations.** `AbstractFileFieldType` implements `TracksFiles::fileUuids()`. After a value is written, `ContentValueWriter::replace` (in the write transaction) syncs `media_relations` via `FileRelations::sync('content_node', {node id}, {field key}, {lang}, ...)`: binding, replacement, clearing, a multiple field. A validation error before the transaction does not change the relations. The relations are removed when the node is deleted (`ContentNode::deleted`) and the field (`ContentField::deleted`, only for fields with `TracksFiles`); the file itself remains. Nodes and fields should be deleted through the model — see [media library](media-library.md#rules-for-the-consumer).

**URL without DB queries.** `FileStorage::urlForValue($profile, $value)` builds `Storage::disk(disk of the profile)->url($value)`: the file lies at the root of the disk, so the value is the path, and there is no query to `media`. Only the profile's `disk` depends on the configuration; changing it means moving files between storages. A test checks `media.path === {uuid}.{extension}` for each profile.

**Display on the site** (images with dimensions, file links) is the job of the client code: the type only returns the string. The file name (`original_name`) exists only in `media`; the admin panel fetches names in one query per page.

## Reading with fallback

`ContentValueReader::load(..., fallback: true)` and `loadForType(..., fallback: true)` are the reading of the public site: for every requested field that is blank in the requested language (by the type's `isBlank()`), the value stored in the base language (`default`) is returned.

- The fallback is **per field**: a node with an English `title` and only a base `body` reads the English title and the base body.
- A **multiple** field falls back as a whole list and a **composed** value (a video, a link) as a whole value; two languages are never mixed inside one field.
- The cost is one more query per value table in use (the base rows of all nodes at once), never one per node.
- Without the flag nothing changes: the admin panel, writers, importers and APIs keep the strict reading.

```php
app(ContentValueReader::class)->load($nodes, ['title'], 'en', fallback: true); // the base title when English is blank
app(ContentValueReader::class)->loadForType($news, ['section_title'], 'en', fallback: true);
```

`PresentContext` carries the reading language and the `fallback` flag of the public site; term names, node titles, file names and descriptions and field labels are read through it (see [theme-templates.md](theme-templates.md)).
