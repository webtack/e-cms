# Spec Delta

## ADDED Requirements

### Requirement: Вибір e2e-спеків для змін спільного коду
For changes in a shared component of the application (for example, `DataTable`) the development rules SHALL require running only those e2e specs that depend on this component, not the full set. The dependent specs SHALL be determined by the use of the component in the application and by the correspondence "shared code → specs" documented in `docs/e2e-testing.md`. The full set SHALL be required only for changes of `playwright.config.js`, `e2e/global-setup.js`, authentication and the shell/sidebar.

#### Scenario: Change of a shared component
- **WHEN** `DataTable` is changed, and not all specs use it
- **THEN** only the specs that use it are run, and the rest (for example, `smoke`, `admin-i18n`) is skipped

#### Scenario: A change that touches all specs
- **WHEN** `playwright.config.js`, `e2e/global-setup.js`, authentication or the shell/sidebar is changed
- **THEN** the full `npm run test:e2e` is run

#### Scenario: The boundary of the impact cannot be determined from the code
- **WHEN** from the code and the table it cannot be determined which specs depend on the change, or the chosen set almost coincides with the full one
- **THEN** instead of a silent choice by the developer, they are asked whether to run the full set

### Requirement: Звіт про запущені та пропущені e2e-спеки
At the end of a task or an edit the report SHALL name which e2e specs were run and which were skipped, and the reason for each skip. The choice of specs SHALL NOT require confirmation before the run.

#### Scenario: Selective run
- **WHEN** an edit is finished for which a part of the specs was run
- **THEN** the report lists the run specs and the skipped ones with a reason (for example, "does not use `DataTable`")

#### Scenario: e2e was not run
- **WHEN** the edit does not touch the browser behaviour
- **THEN** the report says that e2e was skipped, and why

### Requirement: Підтримка відповідності «спільний код → спеки»
Adding a new e2e spec or a new consumer of a shared component SHALL be accompanied by an update of the corresponding rows in the tables of `docs/e2e-testing.md`.

#### Scenario: A new consumer of the component
- **WHEN** a new page starts using `DataTable`, and its spec is added
- **THEN** the spec appears both in the table "area → spec" and in the list of specs dependent on `DataTable`
