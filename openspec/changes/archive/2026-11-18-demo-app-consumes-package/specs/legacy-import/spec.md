# Spec Delta

## MODIFIED Requirements

### Requirement: Import command and its options
The system SHALL provide the console command `legacy:import` that reads the legacy database through a dedicated connection configured in `.env` and the legacy files from a configurable directory (by default `storage/app/public/_old`). The command SHALL accept `--only` (a comma-separated list of steps: `users`, `types`, `files`, `nodes`), `--type` (a comma-separated list of content type keys), `--dry-run`, `--fresh`, `--limit` (a maximum number of nodes per kind, for trial runs) and `--source` (the files directory). Without `--only` all steps SHALL run in this order: users, types and fields, files, blocks and pages, then content nodes. The command SHALL fail with a clear message, writing nothing, when the legacy connection cannot be opened or the files directory does not exist (except for steps that need no files).

#### Scenario: Whole import
- **WHEN** `legacy:import` runs against a reachable legacy database and an existing files directory
- **THEN** users, types, fields, files and nodes are imported, and a report is printed

#### Scenario: A single step
- **WHEN** `legacy:import --only=types` runs
- **THEN** only the missing types and fields are created, and no node or file is touched

#### Scenario: Legacy database unavailable
- **WHEN** the legacy connection fails
- **THEN** the command stops with a message naming the connection, and nothing is written

#### Scenario: Missing files directory
- **WHEN** the files directory does not exist and the `files` or `nodes` step is requested
- **THEN** the command stops with a message naming the directory, and nothing is written

#### Scenario: Trial run limited by count
- **WHEN** `legacy:import --only=nodes --limit=20` runs
- **THEN** at most 20 nodes of each kind are imported

#### Scenario: Works against the packaged CMS
- **WHEN** the CMS classes come from the `webtack/e-cms` package and `legacy:import` runs
- **THEN** the command behaves exactly as before: same steps, options (including `--type` and the add-only behaviour), data written and report
