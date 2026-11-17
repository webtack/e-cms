# Changelog

All notable changes to `webtack/e-cms` are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/); until `1.0.0` the API and the fixed admin prefix may change in a minor version.

## [Unreleased]

## [0.1.0] - 2026-11-17

First release: the CMS that ran as the code of a Laravel application, extracted into a package.

### Added

- The CMS core under the `Webtack\Ecms` namespace: content model with 15 field types, translations, node queries, media library and image styles, menus, taxonomy, roles and rights, application and site settings.
- The Inertia admin panel with a compiled bundle in `dist/` (no Node needed in the host), published with `vendor:publish --tag=ecms-assets`.
- The public site: routes with a language prefix, search, sitemap, SEO metadata and the theme system with the shipped `default` theme.
- The service provider with package discovery, the `ecms.public_site` and `ecms.auth_routes` switches and the `ecms.locales` interface language list.
- `php artisan ecms:install`, `theme:check` and `image-styles:flush`; the `AdminSeeder`.
- The publish tags `ecms-config`, `ecms-lang`, `ecms-views`, `ecms-assets`, `ecms-theme` and `ecms-theme-tooling`.
- The `en` and `uk` admin translations, shipped by the package and overridable by the host.
- A PHPUnit suite on Orchestra Testbench, and CI for PHP 8.3 and 8.4, code style and the freshness of `dist/`.
- Documentation (`docs/`), including installation, integration, configuration and theming guides, and the OpenSpec history of the project.
