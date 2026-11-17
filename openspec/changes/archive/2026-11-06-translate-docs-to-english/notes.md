# Working notes: translate-docs-to-english

## Terminology glossary (task 2.1)

Fixed English forms used across all translated files. Code identifiers stay verbatim.

| Ukrainian (source) | English (fixed) | Notes |
| --- | --- | --- |
| запис контенту / запис | content node (node) | Table `content_nodes`, model `ContentNode`. "Записи" → "nodes" in UI-facing prose. |
| тип контенту | content type | Model `ContentType`, kind `content`. |
| тип сторінки | page type | Kind `page`. |
| тип блоку | block type | Kind `block`. |
| вид типу (`kind`) | kind | Kept as `kind`. |
| поле типу (поле) | content field | Model `ContentField`. |
| значення | value (content value) | Table `content_values_*`. |
| таксономія | taxonomy | Section `/admin/taxonomy`. |
| словник | vocabulary | Taxonomy dictionary. |
| термін | term | Taxonomy term. |
| медіа | media | Table `media`. |
| медіатека | media library | Section `/admin/media`. |
| меню | menu | Table `admin_menu_entries` is kept verbatim. |
| пункт меню | menu item | |
| тема | theme | `APP_THEME`, `themes/`. |
| кандидат (шаблону) | candidate | Template lookup candidate. |
| мова | language | `app_languages`; locale code = locale. |
| локаль | locale | Used for code-level language codes. |
| сторінка (сайту) | page (page node) | Public page of a `page` type node. |
| блок | block | Block node of kind `block`. |
| вибірка | query / querying | `content-querying.md` → "Content querying". |
| адмінка | admin panel | |

## Heading anchor map

Anchors are fixed after translation with a scripted pass that pairs Ukrainian headings from `git show HEAD:<file>` with their English replacements in the same order. See task 6.1.

## Cyrillic literals kept (task 5.1)

To be filled in task 5.1.
