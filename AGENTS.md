# Instructions for AI coding agents

RefreshGlobal is a FreeScout module (`RefreshGlobal/`) that adds an “All mailboxes” page and works with the optional
Refresh module. Read `README.en.md`, `COMPATIBILITY.md` and `RefreshGlobal/INTEGRATION_MAP.md` before changing code.

## Hard rules

- **Never modify FreeScout core files or the Refresh module.** Integrate only through hooks (Eventy filters /
  actions), the module's own files, CSS and JavaScript.
- **Never invent hooks, classes, methods or routes.** Every element of FreeScout, Refresh or another module the code
  relies on must be cited with `file:line` in `RefreshGlobal/INTEGRATION_MAP.md` and declared as an `RG-xxx` check in
  `RefreshGlobal/Config/integration.php` (with its texts in the 7 languages of `Resources/lang/*/compat.php`).
- **Rights are enforced server-side** (FreeScout's own policies, `Services/MailboxAccess.php`); an id coming from a
  request is never trusted.
- **Degrade explicitly, never crash**: a missing optional element disables only its feature, with a clear message.
- **Interface strings in 7 languages**: en, fr, de, es, it, nl, pt-BR (`RefreshGlobal/Resources/lang/*`). Do not change
  existing translations without a reason.
- **Code must run on PHP 7.1** (FreeScout's minimum): check with `php -l` under PHP 7.1.
- **No personal or confidential data** in the repository, commits or release notes (no real names, e-mail addresses,
  host names or domains of an installation; test data uses `example.test`).

## Every change that is published

1. **New version number** (SemVer): `RefreshGlobal/module.json` (`version`) and `install.sh` (`SCRIPT_VERSION`). Never
   publish changes under an existing version: FreeScout's Modules page and UpdateGuard only offer an update when the
   version changes.
2. **Update both changelogs, in the same commit:**
   - `CHANGELOG.md` — **English**, the reference: its section becomes the GitHub release notes;
   - `CHANGELOG.fr.md` — **French**, same content.
   Both must have a `## [X.Y.Z] — YYYY-MM-DD` section (`tools/build-release.sh` and the CI refuse a release otherwise).
   Compatibility notes, breaking changes, migration instructions and new features must always be in the English
   changelog. Use the sections Added / Changed / Fixed / Security / Compatibility / Migration / Documentation.
3. **Tests**: PHPUnit tests in `RefreshGlobal/Tests/Feature/` for new behaviour; list them in
   `RefreshGlobal/Tests/README.md`; record what was verified in `tests/RESULTS.md`.
4. **Documentation** as needed: `README.md` (French) and `README.en.md` (English), `COMPATIBILITY.md`,
   `RefreshGlobal/INTEGRATION_MAP.md`, `docs/api-tags.md` (API for other modules).
5. **Build and publish**: `bash tools/build-release.sh`, commit, tag `vX.Y.Z`, push the branch and the tag; the CI
   (`.github/workflows/ci.yml`) runs the tests and attaches `RefreshGlobal.zip`, `SHA256SUMS`, `version.txt`,
   `module.json` and `install.sh` to the GitHub release.

## Useful commands

```bash
php artisan refreshglobal:check          # integration checks (RG-xxx) on an installation
php artisan refreshglobal:health --json  # health check used by UpdateGuard
bash tools/build-release.sh              # release archive in dist/
```
