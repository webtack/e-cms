# Tasks

Verification for theme tooling (per project rules): no PHP or e2e tests; each task is checked on the host with `theme:dev`, `curl` and the browser DevTools. Node/npm run on the host.

## 1. Dev server proxy

- [x] 1.1 In `vite.theme.config.js` add a dev `server.proxy` for `/app` to `APP_URL` (read from `.env` with `loadEnv`, `changeOrigin: true`), with English JSDoc and an `@example`; verify with `npm run theme:dev -- dcculture` that `curl -s -o /dev/null -w "%{http_code}" http://localhost:5174/app/dcculture/fonts/inter-latin-wght-normal.woff2` returns 200 (it returned 404 before) and that `http://localhost:5174/@vite/client` is still answered by the dev server
- [x] 1.2 Verify in the browser with `theme:dev` running that on `http://localhost:8080/` the Network tab shows no failed request for `/app/dcculture/…` (the `.site-header.ornament::before` background image and the fonts load), and that `npm run theme:build -- dcculture` output is unchanged (same files in `public/themes/dcculture/`)
- [x] 1.3 Update `docs/site-themes.md` ("Commands" and "Static files (fonts, images)") in English: the dev server forwards `/app/…` to `APP_URL`, so the application must be running; verify the links in the edited sections still resolve
