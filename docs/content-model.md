# Content model

Content structure is data: content types, fields, and nodes are created as database rows, without migrations. Field values and translations are described in [content-values.md](content-values.md), languages in [content-languages.md](content-languages.md), querying in [content-querying.md](content-querying.md), and categorization (vocabularies and terms) in [taxonomy.md](taxonomy.md).

## Entities

| Table / model | Purpose |
| --- | --- |
| `content_types` / `ContentType` | Content type: `key`, `name`, `slug`, `description`, `kind`, `parent_type`, `settings`. |
| `content_fields` / `ContentField` | Field definition: `key`, `type`, `is_multiple`, `is_required`, `is_translatable`, `hide_label` (hides only the field label on the public page; the admin node form keeps it), `is_system`, `weight`, `settings`. |
| `content_nodes` / `ContentNode` | Content node: `uuid`, `content_type`, `slug`, and the service attributes `published_at`, `unpublished_at`, `is_active`, `user_id`, `views` (see [Node service attributes](#node-service-attributes)). Values live in separate tables. |

`ContentType` is the only way to create a type: on creation, the [system fields](#system-fields) are added in the same transaction: `title`, plus the metadata and section fields depending on the type's kind.

## Identifiers and relations

- A **type** is identified by a unique immutable `key` (format `^[a-z][a-z0-9_]*$`, up to 64 characters). Relations to a type (`content_types.parent_type`, `content_fields.content_type`, `content_nodes.content_type`) are foreign keys to `content_types.key`, not to `id`.
- A **field** is identified by the pair (`content_type`, `key`); the field `key` is immutable and unique within its type. The field value type (`type`) is also immutable after creation, regardless of whether values exist: to change it to another type, the field is created anew. Value tables reference the field by this pair.
- A **node** has a `uuid` (UUID v7, unique, immutable): it can be set explicitly (import) or generated. `ContentNode::findByUuid` looks up by it. Value tables store `content_node_id` and a copy of `content_node_uuid`.
- Only nodes have a `uuid`: types and fields already have immutable `key`s.

Purpose for future import/export: the import order is types (parents first by `parent_type`), then fields and nodes, then values. A type is looked up by `key`, a field by (`content_type`, `key`), a node by `uuid`. A `uuid → id` map is needed only for nodes (to write the local `content_node_id` into values); the `id` from the source environment is not transferred. Import/export itself is a separate change.

Immutable after creation: the type's `key` and `kind`, the field's `key`, `type` (value type), and `content_type`, the node's `content_type` and `uuid`. An attempt to change them throws `ValidationException`.

## Type kind (`kind`)

`kind` ∈ `config('content.kinds')` (`content`, `page`, `block`; default `content`) and does not change after creation. All kinds have the same fields, values, translations, and querying: the kind only classifies the type. Nodes of all three kinds are edited in the admin panel in their own sections ([admin-content-nodes.md](admin-content-nodes.md)); behavior specific to kinds (page tree, public routing, block placement) is for future changes. The scope `ContentType::ofKind($kind)` returns the types of one kind.

## Type hierarchy

`parent_type` is the `key` of the parent type; the tree is organizational only, and fields are not inherited. Checks: the parent exists, has the same `kind`, and there are no cycles. A type with children cannot be deleted (`restrictOnDelete`). Scopes `childrenOf($key)` and `roots()`. `ContentType::descendantKeys()` returns the keys of all descendants at any depth (breadth-first traversal); forms use it so that a descendant cannot be chosen as the parent type.

## Type slug

The type `slug` is an optional public site path that opens the list of nodes of this type (the route and response are described in [public-site.md](public-site.md#routes)). It has one or more segments separated by `/` (`news`, `common/institutions`); each segment has the same format as the [node slug](#node-slug) (`^[a-z0-9]+(-[a-z0-9]+)*$`), and the whole slug is up to 190 characters (`ContentType::SLUG_PATTERN`; no leading, trailing or repeated `/`). It is unique among all types (case-insensitive; the column `content_types.slug` has a unique index, `NULL` does not conflict). It is available only for `kind = content`: for `page` and `block` it must be empty, and an attempt to set it throws `ValidationException`. An empty slug (`''` or `null`) is stored as `NULL` and means that the type has no public list. Unlike `key` and `kind`, the slug can be changed and cleared. The first segment of the type slug is shared with page slugs and reserved paths (see [Reserved paths](#reserved-paths)), and the whole slug may not equal a page slug or a node address: `PublicPaths::typeSlugConflict()` in `ContentType::assertValid()`, an error under the key `slug`. The checks apply only when the slug is created or changed, so an existing conflict does not block other type edits.

**Hierarchy of type slugs.** A type with a `parent_type` and a slug must have a slug that starts with the parent's slug, `/`, and a non-empty tail (`common` → `common/institutions` → `common/institutions/deep`); the parent must have a slug. A child without a slug is allowed, a root type may have any slug. The comparison is case-insensitive and by whole segments (`commonplace/x` does not start with `common`). The rule runs on creation and when `slug` or `parent_type` changes (`ContentType::assertValidSlugHierarchy()`, service `Webtack\Ecms\Content\TypeSlugs`, errors under `slug`); removing the parent keeps the slug. **Blocked parent change:** the slug of a type cannot be changed or cleared while a direct child has a slug (the message tells to change the children first); there is no cascade, and only direct children matter because a slugged grandchild needs a slugged child. Older rows that break the rule do not block unrelated edits. The form side: [admin-content-types.md](admin-content-types.md). The admin shows the tree through the parent column of the types table and the ["Child types" tab](admin-content-types.md#child-types-tab) of each type.

**Address collisions.** The address of a `content` node is `type slug` + `/` + `node slug`. When it equals the slug of another type, the type list wins in the [public resolution](public-site.md#routes) and the node would be unreachable, so it is rejected: a node slug that makes such an address (`PublicPaths::nodeAddressConflict()` in `ContentNode::assertValidSlug()`, skipped for types without a slug and for `page`/`block`), a type slug that equals the address of an existing node, and a type slug change that makes the address of one of the type's own nodes equal to the slug of a type one segment deeper (`PublicPaths::typeSlugConflict($slug, $type)`).

## Node slug

The `slug` is required, unique among all nodes of all types and kinds (case-insensitive), and is not translated. Format: `^[a-z0-9]+(-[a-z0-9]+)*$`, up to 190 characters (`ContentNode::SLUG_PATTERN`). The `block-` prefix is required for nodes of kind `block`; for other kinds it is not restricted. `ContentNode::findBySlug` looks up a node regardless of type. Public node addresses by slug: [public-site.md](public-site.md#routes). The slug of a `page`-kind node cannot match a `content`-type slug or a reserved path (`PublicPaths::pageSlugConflict()` in `ContentNode::assertValidSlug()`, an error under the key `slug`, only on creation and slug change): [Reserved paths](#reserved-paths). `content` and `block` nodes do not pass this check, except that a `content` node address must not equal a type slug (see [Type slug](#type-slug)).

**Legacy slugs.** Nodes imported from the legacy site ([legacy-import.md](legacy-import.md)) may keep addresses that break the strict format (`a---b`, `jam-session-`, `etno_wave`), because the old addresses must keep working. The wider format is `ContentNode::LEGACY_SLUG_PATTERN` (`^[a-z0-9_-]+$`, up to 190 characters). It is opt-in and never stored: `ContentNode::allowLegacySlug()` sets a transient flag on the instance that makes `assertValidSlug()` use the wider pattern, and `ContentNodeCreator::create()` sets it for the named argument `legacySlug: true` (only the importer passes it). Every other slug rule (uniqueness, the `block-` prefix, page restrictions, the type-list collision) still applies, and creating a slug in the form stays strict (`StoreContentNodeRequest`). **Edit form:** `UpdateContentNodeRequest` skips the strict `regex` only when the submitted slug equals the stored one (`StoreContentNodeRequest::slugRules($stored, $submitted)`), so editors can save an imported node; changing the slug to another legacy-shaped value is rejected under the key `slug`. The public route accepts the wider segment format ([public-site.md](public-site.md#routes)).

**Purpose.** The `slug` is the public identifier of a node for the site frontend (page addresses, references from templates). The admin panel and internal links to a node rely on the immutable `uuid`, not on the `slug`: addresses `/admin/content-types/{key}/nodes/{uuid}/edit` do not break when the `slug` changes ([admin-content-nodes.md](admin-content-nodes.md)). Changing the `slug` does not change the `uuid`. The frontend looks up a node by `slug`, the admin panel by `uuid`. Automatic slug generation is `SlugGenerator`, see [content-values.md](content-values.md#creating-a-node-and-its-slug). For pages, the slugs of `content` types and reserved paths also count as taken: the title "Admin" gives `admin-2`, "News" with a type whose slug is `news` gives `news-2`; `content` nodes do not take these into account.

## Reserved paths

The first segment of a public address is shared between page slugs, `content` type slugs (a type slug of several segments counts by its first segment for the reserved list, so `admin/reports` is rejected and `news/admin` is not; a page `common` next to a type `common/institutions` is no conflict), and the application's service paths. The service paths are `config('content.reserved_paths')`: `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`, `app`, `themes`, `images`, `search`, `sitemap`, `sitemap.xml` (service routes and `public/` directories; the first segment of the package assets directory `vendor/ecms`, so `vendor`, is added by `PublicPaths::reserved()` on top of the config; `app` holds the static files of themes, see [site-themes.md](site-themes.md#static-files-fonts-images); `search` is the [site search page](public-site.md#routes), `sitemap` and `sitemap.xml` are the [sitemaps](type-sitemap.md)); the project may add its own. The codes of the content languages (`en`, `uk`) are not in the config: they are read from the database and are reserved separately, see [Language codes](#language-codes).

The service `Webtack\Ecms\Content\PublicPaths` provides the single implementation of the rule: `reserved()` (the list in lowercase), `pageSlugConflict($slug)` (`reserved`, `language` or `type`), `typeSlugConflict($slug, $type = null)` (`reserved`, `language`, `page` or `node`; the reserved list and the language codes are compared with the first segment of the slug, pages with the whole slug), `languageCodes()` and `languageCodeConflict($code)` (`reserved`, `page` or `type`), `nodeAddressConflict($type, $nodeSlug)`, `isTakenForPage($slug)` (for the slug generator). The comparison is case-insensitive; slugs of `content` and `block` nodes are not checked, because they are not on the first segment.

### Language codes

The codes of the content languages (the saved base language code and every additional language, **active or not**, also while the multilingual switch is off) are part of the first-segment namespace, because an additional language is addressed as `/{code}/...` ([public-site.md](public-site.md#language-prefix)). They are compared case-insensitively and are enforced in both directions:

- A page slug, or the first segment of a `content` type slug, equal to a language code is rejected with the reason `language` ("This slug is the code of a content language.") under `slug` (`ContentNode::assertValidSlug()`, `ContentType::assertValid()`, `AvailablePublicPath`). The slug generator skips such slugs too (the title "En" gives `en-2`).
- Adding a language whose `code` equals a reserved path, a page slug or the first segment of a type slug is rejected under `code` (`StoreContentLanguageRequest`); saving such a base language code is rejected under `base_lang` (`ContentLanguages::setBaseLang()`). Both use `PublicPaths::languageCodeConflict()`.

Unlike the service paths of `reserved()`, a language code never gets the standard 404 page: an address with an unknown or inactive code ends in the site 404.

**Guard test** `tests/Feature/Content/ReservedPathsGuardTest.php`: the first segment of every route (except `/` and routes that start with a parameter) and every directory in `public/` must be in `content.reserved_paths`. A new service route or directory without an entry in the config breaks the test. Attaching the rule to models and forms is described in the sections on type and node slugs.

## Node service attributes

The `content_nodes` columns that do not depend on the language and are not type fields:

| Column | Type | Rules |
| --- | --- | --- |
| `published_at` | `datetime`, NOT NULL | The moment from which the node can be visible. UTC, no time zone conversion; the form works to the minute (`Y-m-d\TH:i`). An empty date on creation gives `now()`. |
| `unpublished_at` | `datetime`, nullable | An optional moment from which the node is hidden again (exclusive: at this moment the node is already hidden). The same format as `published_at`; must be **later** than `published_at` (the model throws `ValidationException` under the key `unpublished_at`; the form shows the error next to the field). `NULL` means no end of display. Disabling **does not change** the date. |
| `is_active` | `boolean`, NOT NULL, default `true` | Whether the node is allowed to be shown on the site. Disabling **does not change** `published_at`. |
| `user_id` | FK to `users`, NOT NULL, `restrictOnDelete` | The author. Set by the server on creation: `ContentNodeCreator::create()` takes an explicit `authorId` or `auth()->id()`; without an author it throws `LogicException`. Not mass-assignable (`#[Fillable]` does not include it), does not come from the request, and does not change. A user who is the author of nodes cannot be deleted. The `own` level of a role works with it ([access control by roles](access-control.md)). |
| `views` | `unsignedBigInteger`, NOT NULL, default `0` | The view counter of the node. A node created through `ContentNodeCreator` starts at `1` (the creation counts as the first view); the column default `0` applies only to rows inserted outside the creator and to nodes that existed before the counter. Belongs to nodes of every kind (`content`, `page`, `block`), does not depend on the language, and is not a type field. Not mass-assignable (`#[Fillable]` does not include it) and does not come from the request: the create and update forms of the admin panel ignore it. Non-negative: the model throws `ValidationException` under the key `views` for a negative value (SQLite in tests does not enforce `unsigned`, so the check is in `ContentNode::assertValid()`). |

A node is **visible** (for the frontend) when `is_active = true`, `published_at <= now()`, and (`unpublished_at IS NULL` or `unpublished_at > now()`) — `ContentNode::scopeVisible()`; the interval is half-open and is computed on read, without cron or queues (the same model as in [menus](menus.md#visibility-by-schedule)); the querying parameter and `findBySlug(..., onlyVisible: true)` are described in [content-querying.md](content-querying.md#node-visibility). The status "Published / Scheduled / Expired / Disabled" is derived and not stored (`ContentNode::status(?CarbonInterface $now)`: `disabled` → `scheduled` → `expired` → `published`); disabled takes precedence over the dates. Index `(content_type, published_at)`. The form and table of nodes in the admin panel: [admin-content-nodes.md](admin-content-nodes.md).

**Migration** `2026_10_31_100000_add_service_attributes_to_content_nodes_table`: adds the columns as nullable, backfills existing nodes (`published_at = created_at`, `user_id` — the administrator by `ADMIN_EMAIL`, as in `DatabaseSeeder`; outside production, if the variable is missing, `admin@example.com` is used), then makes `published_at` and `user_id` NOT NULL. For an empty table the backfill is skipped; for a non-empty table without an administrator the migration fails with an explanation before any changes — set `ADMIN_EMAIL` to an existing user's email and run it again. The rollback drops the three columns together with their data.

**Migration** `2026_11_02_180000_add_unpublished_at_to_content_nodes_table` adds the nullable column `unpublished_at` after `published_at` without a backfill (existing nodes stay visible); the rollback drops the column together with the dates.

**Setting and increasing `views`.** `ContentNodeCreator::create()` starts a node at `views = 1` by default (the creation counts as the first view; the admin forms and any code that omits the argument get `1`). The code that creates a node may set another initial value with the named argument `ContentNodeCreator::create(..., views: 1250)` (for example, an import that carries a counter over from another site); it is assigned on the model next to `user_id`, not through mass assignment, and the model validates it. `ContentNode::incrementViews(int $by = 1)` increases the counter atomically with a single `UPDATE ... SET views = views + n` on the base query builder, so `updated_at` and model events are not touched (a view must not look like an edit) and the in-memory attribute is synced; a negative `$by` throws `InvalidArgumentException`. Views are recorded by the public site ([public-site.md](public-site.md#view-counter)) and shown and sorted in the admin table and lists ([content-querying.md](content-querying.md#sorting-by-service-attributes)).

**Migration** `2026_11_06_100000_add_views_to_content_nodes_table` adds the column `views` after `unpublished_at` with the default `0` (existing nodes get `0`, no backfill); the rollback drops the column together with the counters. A second migration, `2026_11_06_200000_add_views_index_to_content_nodes_table`, adds the index `content_nodes_views_index` for the sort by `views`; it is rolled back first.

## System fields

The application creates system fields together with the type; their single list is the class `Webtack\Ecms\Content\SystemFields` (next to `FieldTypeRegistry`). Field creation (`ContentType::booted()`, the `created` event, the same transaction as `save()`), reserved keys, the owner of field values, and the backfill for existing types are derived from it.

All system fields have `is_system = true`: they cannot be deleted, and their `key`, `type`, `is_multiple`, and `is_required` cannot be changed; the name, weight, description, and settings can be changed. All except `title` are optional; all are single-value and translatable.

| Key | Value type | `content` | `page` | `block` | Weight |
| --- | --- | --- | --- | --- | --- |
| `title` | `string`, required | node | node | node | 0 |
| `meta_title` | `string` | node and type | node and type | — | 1 |
| `meta_description` | `text` | node and type | node and type | — | 2 |
| `meta_image` | `image` | node and type | node and type | — | 3 |
| `section_title` | `string` | type | — | — | 4 |
| `section_description` | `richtext` | type | — | — | 5 |
| `section_cover` | `image` | type | — | — | 6 |

Where `section_title` is used: it is the public title of a `content` type. `Webtack\Ecms\Theme\TypeSection` reads it in the reading language, then in the base language, then falls back to `name`; the list page passes it to its template as `$sectionTitle`, and the breadcrumbs title every type of the trail with it ([public-site.md](public-site.md#layout-and-template-data), [theme-templates.md](theme-templates.md#breadcrumbs)). `section_description` and `section_cover` are not shown on the public site yet.

"Node" means the field has values in the nodes of the type; "type" means the value of the type itself ([content-values.md](content-values.md#value-owner)); "—" means that this field is not created for a type of this kind. The names (`name`) are stored in English; the interface labels are translated by the client part by key.

**Value owner.** `SystemFields::owns($kind, $key, $owner)` tells whether the field belongs to the owner (`node` or `type`) in a type of a given kind. A field without an entry in the list (an ordinary one) and `title` belong only to nodes. Attempting to write a value to an owner the field does not belong to is rejected ([content-values.md](content-values.md)).

**Backfill.** The migration `2026_11_02_100100_backfill_system_metadata_fields` adds the missing fields of their kind to existing types, with a weight after the largest weight of the type. The list in it is a deliberate snapshot that does not depend on the evolution of the class: a new system key requires a new migration. If a type already has a **user** field with the key of a system field, the migration fails before any changes with a list of `type.key`; rename such fields and run it again. A repeated run adds only what is missing. The rollback deletes the `meta_*` and `section_*` fields together with their values and file relations.

### The `title` field

A system field: `string`, required, single-value, translatable, `weight = 0`, belongs only to nodes. Deleting the whole type also deletes `title` (a database-level cascade).

## Translated field labels

The label of a field is its `name`. Translations of it live in the field's `settings` under `labels`: an object that maps a normalized language code to the translated text (up to 255 characters), for example `{"labels": {"en": "Opening hours", "pt-BR": "Horário"}}`. No table is added. The key is optional and an empty object is never stored; every value type, including system fields, may hold it, and it is exempt from the per-type restrictions on `settings` (a `string` field accepts no other settings but may hold labels; the settings checks of `term`, `type_ref` and `node_ref` ignore it and do not run on a labels-only change).

```php
$field->setLabel('en', 'Opening hours');   // store; null or '' removes it; saves only settings.labels
$field->label('en');                        // "Opening hours", or null — no fallback to the name
$field->labels();                           // ['en' => 'Opening hours']
```

The base field form never changes labels (the controller drops any `labels` it receives and keeps the stored ones, for every value type); they change only through the translation mode of the field page ([admin-content-fields.md](admin-content-fields.md)). Cloning a type copies them with the field settings. A language with a translated label cannot be deleted (`FieldLabelsUsage` in the [language usage registry](content-languages.md)); deleting a field deletes its labels with it.

## Reserved type keys

The type key becomes the name of the site template folder, so `ContentType::RESERVED_KEYS = ['content', 'pages', 'blocks', 'fields', 'menus', 'errors']` (names of the theme's service folders) cannot be used for a new type of any kind. The match is checked only against the full key: `pages_list` or `page` pass. The check applies only on creation (`! $this->exists`, the `key` is immutable), so existing types with such keys do not break and can be edited. Two levels: `StoreContentTypeRequest` (the `not_in` rule, the error "This key is reserved" next to the key field in all three sections) and `ContentType::assertValid()` (a `ValidationException` under the key `key`, the last boundary for code and import). The list is in the code, not in the config, because it is tied to the theme folders; field keys have a separate list (see below).

## Reserved field keys

There are two sets of reserved keys: `ContentField::RESERVED_KEYS = ['uuid', 'slug', 'status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views', 'author']` — the keys of the service columns of the node table, and the keys of the system metadata and section fields (`SystemFields::reservedKeys()`: `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`), which only the system creates; `title` is not included, because the uniqueness of the key already rejects it. The rule applies to the user (`is_system = false`); creating the system field itself bypasses it. A new field with such a key cannot be created: `StoreContentFieldRequest` (the `not_in` rule, the error "This key is reserved" next to the key field) and `ContentField::assertValid()` (only for new fields, `! $this->exists`, the last boundary for code and import). Existing fields with such keys do not break: they can be updated, but `ContentNodeController::listSettings()` uses the same list and does not offer them as table columns. A new service attribute that becomes a table column adds its key to `RESERVED_KEYS`. The key `views` is reserved too (it is the service column of the view counter, shown and sorted in the node table): a new field named `views` is rejected, while an older field with this key stays valid and the service column wins over it. Migration `2026_11_16_100000_append_author_to_list_visible` appends `author` to every type's own `settings.list.visible` that has more than `title` and no `author` (the default composition of a type without its own list includes it already); the rollback does nothing. The key `author` is reserved in the same way (the service column with the author's name and avatar; not sortable). User-facing side: [admin-content-fields.md](admin-content-fields.md#reserved-keys).

## Type settings (`settings`)

A JSON object. The `list` key sets the default list configuration (`visible`, `search`, `sort`), which is checked against the type's fields, the `title` key (column position), and the node service keys (`slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`); it is edited from the type form, and on save only the `list` key is updated; see [content-querying.md](content-querying.md#default-list-settings).

The `public` key sets the public display of the type: `list_fields` (added card fields in the list), `page_fields` (added node page fields), `sort` (`field`, `direction`), `per_page` (an integer from 0 to `content.max_per_page`), `pagination` (boolean), `archive` (`{field, sort}` — the date the public list is split into the main list and the archive by, and the sort of the archive list; see [public-site.md](public-site.md#archive-period-of-a-list)), and `meta` (`title`, `description`, `image` — the key of one added field for the title, description, and image of the page in `<head>`). "Added" means user fields (`is_system = false`) that hold node values, without a reserved key; `title`, service attributes, `meta_*`, and `section_*` are always available to the template and are not stored here. It is edited on the type's "Display" tab ([admin-content-types.md](admin-content-types.md#display-tab-all-kinds)); the `meta` key is edited on the "SEO" tab ([admin-content-types.md](admin-content-types.md#meta-data-block-on-the-seo-tab)); saving "Display" does not touch it. It is validated on save in `ContentType::assertValidPublicSettings()` (next to `assertValidListSettings()`): foreign, system, or non-sortable fields, a direction outside `asc`/`desc`, and `per_page` outside its bounds throw `ValidationException` under the key `settings`. `archive.field` is an added `date` / `datetime` field, `created_at`, or `unpublished_at` (any other key, including `published_at`, is rejected); `archive.sort` has the shape and rules of `sort`, and is not allowed without `field`; unknown keys of `archive` are rejected. `list_fields`, `sort`, `per_page`, `pagination`, and `archive` are accepted only by the `content` kind (only it has a public list), `page_fields` by all kinds; `meta` by `content` and `page` (a block has no address of its own), where `title` points to a `string` type field, `description` to `text` or `richtext`, and `image` to `image` or `youtube`; other types, non-existent and system fields throw `ValidationException`.

Default values (the key is absent): in the card only `title`; on the page all added fields, where an **absent** `page_fields` means "all" at read time (a new field appears exactly then), and a saved one, even an empty one, is the list; sorting by `published_at` descending; `per_page = 0` (all nodes); `pagination = true`, but with `per_page = 0` it effectively means "no"; there is no `archive` (the list is not split). An absent `archive.sort` means "the main sort" at read time. The `meta` key is absent — nothing is selected. Reading (`Webtack\Ecms\Content\PublicDisplaySettings::resolve()`) silently drops references to deleted fields (and a meta selection whose type is no longer allowed); see [content-querying.md](content-querying.md#public-type-settings).

## Deletion cascades

- Node → all its values in all tables and languages.
- A non-system field is deleted only if it has no saved values: the `ContentField` model (the `deleting` event) throws `ValidationException` under the key `key` if values exist in at least one node or language; the field and values remain. This applies to any path (admin panel, code, tinker, import); a mass `ContentField::query()->delete()` bypasses the model events.
- Type → its fields, nodes, and their values, and the type's own values (rows with `entity = 'type'` in `content_values_*` disappear together with the fields).

The type cascade is executed by the database (`cascade` foreign keys), so model events do not fire during it, and the prohibition on deleting a field with values does not apply to it.

Content fields (`kind = content`) can be created, edited, deleted, and ordered in the admin panel: [admin-content-fields.md](admin-content-fields.md).
