# Changelog — fs-interop

## [1.0.0] — unreleased (first release of the shared repository)

### Added
- `window.FreeScoutInterop`, protocol 1.0.0: `modules` (`register` / `get` / `list` / `when`), `supports()`,
  `events` (`on` / `off` / `once` / `emit`), `context` (conversation origin, kept for the browser tab), `surfaces`
  (`register` / `owner`), registries `navigation`, `toolbar`, `swipe` (`register` / `get` / `list` / `run`), `semver`.
- Idempotent bootstrap; a newer copy of the same major adds the missing members wired to the existing event bus and
  reports its protocol.
- Tests with Node only (`tests/fs-interop.test.js`), runnable against any module's copy (`FS_INTEROP_FILE`).
