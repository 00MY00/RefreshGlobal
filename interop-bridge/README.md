# fs-interop — FreeScout Interop bridge

> **Staging folder.** This folder holds the content of the future shared repository of the bridge, agreed with
> Refresh's maintainer (2026-10-22): one reference copy, used by both modules, instead of one copy each. It lives in
> RefreshGlobal's repository only until that repository is created; then it is moved there unchanged (with its history
> if wanted) and this folder is removed.

`fs-interop.js` is the small, neutral browser bridge through which sibling FreeScout modules (Refresh, RefreshGlobal…)
cooperate without depending on each other's HTML or private code: `window.FreeScoutInterop` (module registration,
capability discovery with version ranges, event bus, conversation context, surfaces, navigation / toolbar / swipe
registries).

* Plain ES5, no dependency, no network call, no HTML produced.
* Idempotent: every module ships the same file; the first copy loaded creates `window.FreeScoutInterop`, the others
  keep it; a newer copy of the same major protocol only adds what is missing, on the existing event bus.
* Contract: `docs/INTEROPERABILITY.md` of each module, machine-readable `interop.json` of each module.

## Versions

| | Meaning |
|---|---|
| **bridge release** (`@bridge-version` in the header, git tag `vX.Y.Z`) | version of this file |
| **protocol** (`PROTOCOL` in the code) | version of the contract the file implements (1.0.0 today) |

A breaking change of the contract bumps the protocol **major**; an addition its minor (a module checks that a member
exists before using it).

## How a module uses it

FreeScout modules are installed one by one from their own archive: there is no dependency manager between modules,
and the bridge must never become a module to install. So each module **ships an unchanged copy of a tagged release**:

1. copy `fs-interop.js` of a release tag into the module (e.g. `Public/js/fs-interop.js`) and load it **first** in the
   module's `javascripts` filter;
2. record the release in a lock file next to it (`fs-interop.lock.json`: `version`, `protocol`, `sha256`, `source`);
3. in the module's CI, check that the copy's SHA-256 equals the lock file, and run this repository's tests against the
   copy:

   ```bash
   FS_INTEROP_FILE=path/to/module/copy/fs-interop.js node tests/fs-interop.test.js
   ```

RefreshGlobal does it with `tools/sync-bridge.php` (copy + lock) and `tools/validate-interop.php` (check).

**Never edit a module's copy.** Change this repository, release a new version, then update each module's copy.

## Tests

```bash
node tests/fs-interop.test.js
```

## Licence

AGPL-3.0 (`LICENSE`), the licence of Refresh and RefreshGlobal. To be confirmed by both maintainers when the
repository is created.
