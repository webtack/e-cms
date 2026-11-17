# Spec Delta

## Purpose

Describes how the public site serves several languages: the language prefix of addresses, how the reading language is chosen, fallback to the base language, links that stay in the language, the language switcher, and the signals for search engines. It builds on the content languages and the translations edited in the admin panel.

## ADDED Requirements

### Requirement: Language prefix in public addresses
When multilingual mode is on, every public address SHALL be available in each active additional language by prefixing it with the language code as the first path segment (`/en/about-us`, `/en/news/story`, `/en` for the home page, `/en/search`, `/en/sitemap`). The prefix SHALL be matched case-insensitively against the normalized code and SHALL NOT be part of the node address: the slug of a page or node is one for all languages. The base language SHALL have no prefix. An address with the code of an unknown or inactive language, or with a prefix while multilingual mode is off, SHALL be treated as an ordinary address and SHALL end in the site 404 page when nothing matches it.

#### Scenario: Page in an additional language
- **WHEN** multilingual mode is on, `en` is active, and a guest opens `/en/about-us`
- **THEN** the page `/about-us` is served in the English reading language

#### Scenario: Home page
- **WHEN** a guest opens `/en` or `/en/`
- **THEN** the home page is served in English

#### Scenario: Record in an additional language
- **WHEN** a guest opens `/en/news/story`
- **THEN** the record with the slug `story` of the type `news` is served in English

#### Scenario: Inactive or unknown language
- **WHEN** a guest opens `/xx/about-us` or `/pl/about-us` with `pl` inactive
- **THEN** the response is the site 404 page

#### Scenario: Multilingual mode is off
- **WHEN** multilingual mode is off and a guest opens `/en/about-us`
- **THEN** the response is the site 404 page and `/about-us` works as before

### Requirement: The base language has no prefix
Addresses without a prefix SHALL be served in the base language. An address prefixed with the code of the base language SHALL permanently redirect (status 301) to the same address without the prefix, keeping the rest of the path and the query string. The language SHALL be chosen only by the address: neither the browser language, a cookie, nor the interface language of the admin panel SHALL change the reading language, and the site SHALL NOT redirect automatically by the browser language.

#### Scenario: Base code in the address
- **WHEN** the base language is `uk` and a guest opens `/uk/about-us?x=1`
- **THEN** the response is a 301 redirect to `/about-us?x=1`

#### Scenario: Browser language
- **WHEN** a guest with `Accept-Language: en` opens `/about-us`
- **THEN** the page is served in the base language without redirect

### Requirement: Service paths are not prefixed
The prefix SHALL NOT apply to service paths: `/admin/**`, `/login`, `/logout`, `/locale`, `/email/**`, `/up`, `/storage/**`, `/build/**`, `/sitemap.xml`. An address that puts a language prefix before a service path SHALL NOT reach it and SHALL end in the site 404 page. The pages `/search` and `/sitemap` SHALL be available with a prefix.

#### Scenario: Prefixed admin panel
- **WHEN** a guest opens `/en/admin/dashboard`
- **THEN** the response is the site 404 page and the admin panel is not served

#### Scenario: Prefixed search page
- **WHEN** a guest opens `/en/search?q=lamp`
- **THEN** the search page is served in English

#### Scenario: XML sitemap
- **WHEN** a guest opens `/en/sitemap.xml`
- **THEN** the response is 404, and `/sitemap.xml` stays one document with the addresses of the base language only

### Requirement: Fallback of the whole page to the base language
A page of an additional language SHALL always be served when the node is visible, whether or not the node has a translation: every value missing in the language SHALL be replaced by the base-language value of the same field. The visibility of a node (activity, publication dates) SHALL NOT depend on the language. The reading language SHALL be applied to values, term names, menu items, field labels, site settings, file descriptions, type section values and meta data, each with the fallback to the base language.

#### Scenario: Node without a translation
- **WHEN** a visible node has no English values and a guest opens its English address
- **THEN** the page is served with the base-language content

#### Scenario: Node with a partial translation
- **WHEN** a node has an English title and no English body
- **THEN** the English page shows the English title and the base body

#### Scenario: Invisible node
- **WHEN** a node is disabled and a guest opens its English address
- **THEN** the response is the site 404 page

### Requirement: Document language and interface strings
The `lang` attribute of the document SHALL be the code of the reading language (the saved base language code for unprefixed addresses). The interface strings of the theme (such as "Address", "Schedule", the pagination and the 404 text) SHALL follow the reading language when the application has interface translations for that language, and otherwise those of the base language. On a site without multilingual mode the interface language of the public site SHALL stay the application locale.

#### Scenario: English page
- **WHEN** a guest opens an English address and the application has English interface translations
- **THEN** the document has `lang="en"` and the theme strings are English

#### Scenario: Language without interface translations
- **WHEN** the reading language is `pl` and the application has no Polish interface translations
- **THEN** the document has `lang="pl"` and the theme strings are those of the base language

#### Scenario: Single-language site
- **WHEN** multilingual mode is off
- **THEN** the document language and the strings are as before this feature

### Requirement: Internal links stay in the language
Every internal link that the site generates or prints SHALL carry the language prefix of the page when the page is in an additional language: the menu items with an internal address, breadcrumbs, cards and lists, pagination, the search form and its result links, references to nodes and types, the links of the sitemap page, the language-independent links of a `url` field and internal links of rich text. Absolute addresses and links to service paths and to files SHALL NOT get a prefix. Pages in the base language SHALL contain no prefix.

#### Scenario: Menu on an English page
- **WHEN** a menu item has the address `/about-us` and the page is English
- **THEN** its link is `/en/about-us`

#### Scenario: External and service links
- **WHEN** an item has the address `https://example.org` or a link to `/storage/file.pdf`
- **THEN** the link is unchanged

#### Scenario: Pagination
- **WHEN** a list on an English page has several pages
- **THEN** the page links keep the `/en` prefix

#### Scenario: Rich text
- **WHEN** a rich text value on an English page links to `/about-us`
- **THEN** the rendered link is `/en/about-us`

### Requirement: Language switcher data and default markup
Every public template SHALL receive the list of languages (the base language first, then the active languages by weight) with their code, name, whether it is the current one, whether the page has a translation in it, and the address of the same page in it. The default theme SHALL print a language switcher from this data in its header when more than one language is available. For a page that does not exist in a language, the address SHALL still lead to the fallback page in that language.

#### Scenario: Data
- **WHEN** a guest opens `/en/about-us` on a site with the base language `uk` and the additional languages `en` and `pl`
- **THEN** the templates receive three languages with `en` as current and addresses `/about-us`, `/en/about-us`, `/pl/about-us`

#### Scenario: Single-language site
- **WHEN** multilingual mode is off
- **THEN** the languages list is empty and the default theme prints no switcher

### Requirement: Alternate and canonical addresses
The `<head>` of a public page SHALL carry `rel="alternate"` links with `hreflang` for the base language and for every language in which the page has a translation, plus `hreflang="x-default"` pointing at the base-language address. The canonical address (`rel="canonical"`) of a page that has a translation in the current language SHALL be its own address; the canonical address of a page served in a language without a translation SHALL be the base-language address, and such a page SHALL NOT be listed among the alternates. A node has a translation in a language when it has at least one stored value in it. Pages without a single node (the home page, lists, search, sitemap) SHALL be self-canonical and listed in every language. On a site without multilingual mode no alternate or canonical links SHALL be added.

#### Scenario: Translated page
- **WHEN** a node has an English translation and a guest opens its English address
- **THEN** the head has the canonical link to the English address, alternates for the base language and for English, and `x-default` pointing at the base address

#### Scenario: Page without a translation
- **WHEN** a guest opens the English address of a node with no English value
- **THEN** the canonical link points at the base-language address and English is not among the alternates

#### Scenario: Search page
- **WHEN** a guest opens `/en/search`
- **THEN** the canonical link is `/en/search` and alternates exist for every language

#### Scenario: Single-language site
- **WHEN** multilingual mode is off
- **THEN** the head has no alternate links and no language canonical rules apply
