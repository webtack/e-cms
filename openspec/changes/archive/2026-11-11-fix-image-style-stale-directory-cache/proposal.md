# Proposal

## Why

After the directory of a style (for example `storage/app/public/styles/thumb`) is deleted, by hand or by `image-styles:flush`, the first requests for its files can answer 404 instead of re-creating them, for up to two minutes. A long-lived PHP-FPM worker keeps a stale realpath cache (`realpath_cache_ttl` = 120 s), so `is_dir()` still returns `true` for the removed directory. Flysystem then skips creating it, the write fails, and Laravel (`'throw' => false`) returns `false` silently: `ImageStyles::create()` returns `null`, the controller aborts with 404, and nothing is logged. The result depends on which worker serves the request (reproduced as `200 404 404 200 404 200`). The spec already requires lazy re-creation after cleaning, so this is a defect, not a new feature.

## What Changes

- `ImageStyles::create()` resets PHP's stat/realpath cache (`clearstatcache(true)`) on the creation path, before the existence check and the write, so a directory removed behind the worker's back is created again.
- A failed write or move of a style file (the disk returns `false` without an exception) is logged as a warning with the disk, the original path and the style, like the existing failure branch. The response is still 404 and no partial file is left.
- A scenario is added to the lazy-generation requirement: the first request after the style directory was removed creates the file.
- The image-styles docs describe the stale-cache case and the new log entry.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `image-styles`: the "Lazy generation of a style" requirement gains the guarantee that creation works right after the style directory was removed, regardless of what the PHP worker remembers about it; failures to write the file are logged.

## Impact

- Code: `app/Files/ImageStyles.php` (`create()`); `ImageStyleController` and `FileStorage` call it and stay unchanged.
- Tests: `tests/Feature` for image styles (re-creation after the style directory is removed, warning on a failed write). A stale realpath cache cannot be simulated in a test; the real-FPM behavior is checked manually.
- No migrations, no configuration, no API changes.
