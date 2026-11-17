# Design

## Context

`ImageStyles::create()` (`app/Files/ImageStyles.php`) is the single place that writes a style file: it is called by the lazy route (`ImageStyleController`) and by the upload-time presets (`FileStorage`). It checks `$storage->exists($target)`, then writes `{target}.{random}.tmp` with `$storage->put()` and renames it with `$storage->move()`. The `public` disk has `'throw' => false`, so `put()` and `move()` return `false` on failure instead of throwing, and `create()` turns that into `null` (404 in the controller) without any log entry. Only exceptions are logged.

Measured behavior (see proposal.md - Why): in PHP-FPM, `is_dir()` of a removed directory keeps returning `true` for up to `realpath_cache_ttl` (120 s) in a worker that already resolved it. Flysystem's local adapter calls `is_dir()` and skips `mkdir`, so the write fails. In the CLI the cache does not outlive the process, so the problem is invisible there (tinker, artisan, tests). Documented in `docs/image-styles.md` (Lazy generation).

## Goals / Non-Goals

**Goals:**
- The first request after a style directory is removed creates the file in any worker.
- A write failure leaves a trace in the log.

**Non-Goals:**
- Changing `realpath_cache_ttl` or any PHP/Docker ini setting.
- Changing the global `throw` setting of the disks, or the 404 answer on failure.
- Touching the other places that write to disks (uploads, media) unless the same symptom is shown there.

## Decisions

**1. Call `clearstatcache(true)` at the start of `create()`, on the creation path only.**
The parameter `true` clears the realpath cache as well as the stat cache. It runs after the cheap early checks (valid style, valid extension) and before `exists()`, so that both the existence check and the later directory check see the real disk. The hot path is unaffected: files that exist are served by the web server and never reach PHP; a request that reaches `create()` is already about to decode and encode an image, so one cache reset is negligible.
Alternatives: (a) retry once after a failed write, which is more code and still needs the same reset; (b) create the directory explicitly with `makeDirectory()`, which uses the same stale `is_dir()` and does not help; (c) lower `realpath_cache_ttl` in `docker/php`, which fixes only the local environment and leaves production, whose configuration we do not control, exposed.

**2. Log a warning when `put()` or `move()` return `false`.**
Same message style, level and context keys (`disk`, `path`, `style`) as the existing `catch` branch, so one search finds both. The result stays `null`, so the response stays 404. A failed `move()` keeps the current clean-up of the temporary file.

**3. Verification.**
The stale realpath cache cannot be reproduced in a test (the cache is per worker process). Feature tests cover the observable contract: a style directory removed from a faked/temporary disk is re-created on the next request, and a failing disk produces the warning and 404. The FPM behavior is confirmed manually: repeat `rm -rf storage/app/public/styles/thumb` followed by an immediate request several times and expect 200 every time (before the fix: a mix of 200 and 404).

## Risks / Trade-offs

- [`clearstatcache(true)` is process-wide and affects other code in the same request] → It only drops cached lookups; the cost is a few extra `stat` calls, and it runs on a rare, already expensive path.
- [A genuine write failure (permissions, full disk) still gives 404] → Now it is logged, so it can be diagnosed; the answer is unchanged on purpose.
- [The automated tests cannot prove the cache fix] → Manual FPM check in tasks; the warning scenario and the re-creation scenario guard the rest.
