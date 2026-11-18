# Tasks

## 1. Documentation

- [x] 1.1 In `docs/e2e-testing.md` (in Ukrainian), section «Коли запускати e2e»: describe the three levels of running, add the table «Спільний код → спеки» (`DataTable`, i18n, shell/sidebar etc.), having checked it in advance against the real consumers by searching the imports in `resources/js` and against what each spec checks; add the requirement of the report "run/skipped and why" and the conditions when to ask; check: each row of the table is confirmed by the code, the relative links are valid, the table covers all specs from `e2e/`.

## 2. Rules

- [x] 2.1 Update the section «Playwright (e2e)» in `CLAUDE.md`: for a shared component — only the dependent specs (by the table and the search of uses), the full run only for `playwright.config.js`, `e2e/global-setup.js`, authentication, the shell/sidebar; the report with the run and skipped specs; ask only when the boundary cannot be determined from the code or the set is almost full; check: the wording matches `docs/e2e-testing.md`.
- [x] 2.2 Update `rules.tasks` in `openspec/config.yaml`: replace the item about the full run for shared code with the choice of dependent specs by the table; the full one — only for the config/`global-setup`/authentication/shell; a new spec or a consumer of the component updates the tables; check: the YAML is valid, `openspec instructions tasks --change selective-e2e-for-shared-code --json` shows the new rule.

## 3. Check

- [x] 3.1 Compare the three places (`CLAUDE.md`, `openspec/config.yaml`, `docs/e2e-testing.md`) for contradictions about the list of "full run"; check: `grep -n "повний\|full"` in these files, the same list everywhere.
