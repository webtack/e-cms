# Design

## Context

The motivation and the scope: see `proposal.md`. The current state, from which the approach starts:

- All the integrity rules of types and records live in the models (`ContentType::assertValid()`, `ContentNode::assertValidSlug()`, the event `saving`), while form requests duplicate them deliberately, so that the errors are shown next to the fields (`StoreContentTypeRequest::sharedRules()`, `StoreContentNodeRequest::slugRules()`).
- The prohibition of reserved keys exists only for fields (`ContentField::RESERVED_KEYS`, `Rule::notIn` in `StoreContentFieldRequest`); the translation «This key is reserved.» is already in `lang/*.json`.
- The slug of a type is unique among types, the slug of a record is unique among records; they are not checked against each other. The type `content` has `slug` (`kind = content`), the type `page` does not have it.
- `SlugGenerator::fromTitle()` checks occupancy only in `content_nodes`; `ContentNodeCreator` catches `UniqueConstraintViolationException` as the last boundary of uniqueness.
- There is no public routing yet; the first segment of the service routes: `admin`, `login`, `logout`, `locale`, `email`, `up`; in `public/` there are the directories `build` and `storage`.
- The working DB is clean: types `ddd`, `dsfsdf`, `news` (the slug of the type `news`), the page `rtrtyrty`.

## Goals / Non-Goals

**Goals:**
- One source of truth for the reserved keys of types and for the reserved paths.
- The same behaviour in the model (code, import) and in the forms (an error next to the field `slug` or `key`).
- The automatic generation of the slug of a page does not create collisions.
- Protection against drift: a new service route or a directory in `public/` without a record in the list breaks the test.

**Non-Goals:**
- Language prefixes (`/en/…`): a separate change; when it appears, the language codes will be added to the reserved paths.
- The rule of priority during public routing (a service path, a type, a page): decided by the change with the public routes. Here we only guarantee that no collisions in the data arise.
- Fixing the existing data with collisions (a migration or an audit command): there are none in the working DB, so we leave it without automatic correction.
- Checking the slug of records `content` and `block`: they are not on the first segment.
- A DB-level constraint between the two tables.

## Decisions

### 1. Reserved keys of types: a constant in `ContentType`

`ContentType::RESERVED_KEYS = ['content', 'pages', 'blocks', 'fields', 'menus', 'errors']` next to `KEY_PATTERN`. The check in `assertValid()` only for a new type (`! $this->exists`), because `key` is immutable, so the existing types neither break nor get checked again. In `StoreContentTypeRequest::rules()` `Rule::notIn(ContentType::RESERVED_KEYS)` is added to the key (for all three sections); the message «This key is reserved.» (the translation already exists).

*Why a constant and not config:* the list is tied to the names of the folders of the theme, that is, to the code, not to an environment setting; a new service directory of the theme means a new row in the code and in the test, not a change of `.env`. The same is decided for `ContentField::RESERVED_KEYS`.
*Alternative:* a subfolder `types/` for the folders of types in the theme. Rejected by the owner: the prohibition of keys was chosen.

### 2. Reserved paths: `config('content.reserved_paths')` plus a guard test

The list `['admin', 'login', 'logout', 'locale', 'email', 'up', 'storage', 'build']` in `config/content.php`. It is a config because it depends on the deployment (the directories of `public/`, the separate service routes); the project may add its own.

The guard test collects the set «the first segment of each route without a parameter as the first segment (except `/`)» plus «the directories in `public/`» and checks that it is contained in the config. A new route or a directory without a record breaks the test.

*Alternative:* derive the list dynamically from `Route::getRoutes()`. Rejected: the validation would depend on the order of the registration of routes and on a future public catch-all, while a static list with a test is simpler and more predictable.

### 3. The service `PublicPaths` in `app/Content/`

The single implementation of the rule, which is called by the model, the form requests and the generator:

- `reserved(): array` reads the config in lower case;
- `pageSlugConflict(string $slug): ?string` returns the reason (`reserved` or `type`) or `null`: the slug matches a reserved path or the slug of a type `content`;
- `typeSlugConflict(string $slug, ?int $ignoreTypeId = null): ?string`: with a reserved path or with the slug of a record of the kind `page` (a query through `content_nodes` → `content_types.kind = 'page'`);
- `isTakenForPage(string $slug): bool` for the generator.

The comparison is done on the values in lower case; the format of the slug (lower-case letters) is already checked earlier, so the case is only an additional safeguard.

### 4. Levels of the check

- **Model.** `ContentNode::assertValidSlug()` after the check of the format and of the prefix of a block: for the kind `page` it calls `pageSlugConflict`. `ContentType::assertValid()`: for `kind = content` and a non-empty slug it calls `typeSlugConflict`. Both calls are made only when `! $this->exists || $this->isDirty('slug')`. For a record this is already so; for a type the condition must be added, because now `assertValid()` is called on every save. This ensures «an existing match does not block other edits». The error: `ValidationException` under the key `slug`.
- **Forms.** A validation wrapper rule (`AvailablePublicPath`, `ValidationRule`) calls the service. It is connected in `StoreContentNodeRequest` / `UpdateContentNodeRequest` only for the route of the kind `page` (for update also only when the slug differs from the saved one) and in `StoreContentTypeRequest::sharedRules()` for the kind `content` (for update only when the slug is changed). Messages: «This slug is reserved.», «This slug is already used by a content type.», «This slug is already used by a page.» (added to `lang/en.json` and `lang/uk.json`).

*Why both levels:* this is how the rest of the project works (the form gives an error next to the field, the model remains the last boundary for tinker and import).

### 5. Automatic generation of the slug of a page

`SlugGenerator::forType()` for the kind `page` passes a flag to `fromTitle()`, and the set of «occupied» values (now only the slugs of records with the prefix of the base) is supplemented with the slugs of types `content` and with the reserved paths that match the base or its numeric suffixes. So `admin` gives `admin-2`, `news` with a type with the slug `news` gives `news-2`. For `content` the behaviour does not change. An explicit slug is never changed: a collision gives a `ValidationException` with the key `slug`.

`ContentNodeCreator` does not change: the model throws a `ValidationException` before the INSERT, and a race (two records at the same time) is still caught by the existing branch `UniqueConstraintViolationException` for uniqueness between records.

## Risks / Trade-offs

- [A race between the tables: a type and a page with the slug `x` are saved at the same time] → there is no DB constraint between the tables; a record in the admin is rare, and the public routing later is deterministic in any case. We accept it without locks.
- [Existing data with a match] → we do not fix it automatically; the check happens only on creation and on a change of the slug, so such records are edited without an error. There are no matches in the working DB. They will be detected by `theme:check` or by a separate audit in the change about public routes.
- [Tests and fixtures that use reserved keys, in particular `pages`] → we update them in one pass to neutral keys (`landing`, `static`, `articles`), without changing the essence of the tests. The list is found by `grep` in `tests/`, `e2e/`, `database/`.
- [The list of paths becomes outdated] → the guard test (decision 2).
- [Language prefixes will add more reserved segments] → deliberately out of scope; the change about the languages of the site will add the codes of the active languages to `reserved()`.
- [A slug of a page that matches the name of a file in `public/` without a dot] → the guard checks the directories of `public/`; files with an extension do not match the format of the slug.

## Migration Plan

No DB migrations. Deployment: an ordinary deploy of the code. Rollback: return the code; the saved data does not change. Before deploying in environments with real data, check manually (a query in `tinker`) the absence of matches of the slugs of types and pages and of the reserved keys of types.
