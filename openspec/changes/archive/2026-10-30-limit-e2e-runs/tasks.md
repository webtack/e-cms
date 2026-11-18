# Tasks

## 1. Run rules

- [x] 1.1 Update the section "Playwright (e2e)" in `CLAUDE.md`: the selective run during work (`npm run test:e2e -- e2e/<спек>`), the full run only after changes of shared code (the list of "shared code" in the same section); check: the section reads unambiguously, the commands match `docs/e2e-testing.md`
- [x] 1.2 Add to `openspec/config.yaml` in `rules.tasks` the rule: the tasks check only the spec of the changed area, the full `npm run test:e2e` — only in the task that touches shared code; a new spec adds a row to the correspondence table; check: `openspec instructions tasks --change limit-e2e-runs --json` shows the new rule, the YAML is valid

## 2. Check and documentation

- [x] 2.1 Make sure the selective run works with `globalSetup`: set the administrator's `users.locale` to `uk` and run `npm run test:e2e -- e2e/smoke.spec.js` (the language becomes `en`), then `npx playwright test --list e2e/admin-page-types.spec.js` shows only its tests; check: both commands give the expected result
- [x] 2.2 Add to `docs/e2e-testing.md` (in Ukrainian) the section "Коли запускати e2e": two levels of running, the commands of the selective run, the table "area → spec", an explanation of why not `--only-changed`; check: the relative links are valid, the table covers all specs from `e2e/`
