## 1. Hook: choose a random video

- [x] 1.1 Create `themes/dcculture/hooks.php` returning a function with `preprocess('home')`: load the `video` type, list its visible nodes with `ContentNodeQuery` (`onlyVisible: true`, `perPage: 0`, fields `title` and `youtube`), drop nodes with a blank `youtube` value, pick one at random, trim `$values['youtube']` to the first non-blank item (a one-item list for a multiple field), and set `$vars['homeVideo']` to `node`, `type`, `values`, `fields` (the `youtube` field by key) or `null` when nothing qualifies. Add English comments and a docblock with `@example`, including the note that caching the home page would freeze the choice. Verify: `docker compose exec app php artisan theme:check` passes for `dcculture` and `default`.
- [x] 1.2 Verify the hook by hand with `docker compose exec app php artisan tinker` or a request to `http://localhost:8080/`: the `video` records that are disabled, scheduled, expired or without a video never appear; the variable is `null` when the `video` section has no qualifying record.

## 2. Template and styles

- [x] 2.1 In `themes/dcculture/views/partials/home/about-city.blade.php` print the card inside `.about-city__media` from `homeVideo` (set `$node`, `$type`, `$fields`, then `@field($values, 'youtube')`) and keep the static `<img>` as the fallback when `homeVideo` is `null`; keep the plate, ring and badge; update the Blade comment (the variable replaces "Future data: video link").
- [x] 2.2 In `themes/dcculture/assets/components/about-city.css` give `.about-city__media .video-previews` and `.video-preview` the position and size of the former picture (520 × 292 with `rounded-3xl`; the percentage sizes below `lg`), above the plate and below the badge and the ring. Run `npm run build` on the host. Verify visually at 360, 768, 1024 and 1440 px, with a record and with none (static picture), that a click opens the modal player and that the page does not scroll horizontally.

## 3. Documentation

- [x] 3.1 Update `docs/theme-dcculture.md`: add `themes/dcculture/hooks.php` to the templates/files tree, describe the `preprocess('home')` hook and the `homeVideo` variable (selection rules, first video, `null` fallback, no caching), change the row `home/about-city` in "Data seams" (the video is done; the intro text remains), and note in "Video card and modal" that the card is also used on the home page. Keep `docs/README.md` links valid.
- [x] 3.2 Verify the change: `openspec validate home-random-video --strict` passes; per the project rule for theme frontend no PHP or e2e tests are added or run, and the final report says so.
