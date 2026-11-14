# Compatibility and support policy

## What is promised

Every public `Xoops\ModuleTools\*` API in the 1.x line — and every lazy
`XoopsModules\Mtools\*` alias to it — stays available for the **whole XOOPS 2.8 lifetime**.
A module written against ModuleTools 1.x, or against the mTools 1.x module, keeps running on
every 2.8.x release without code changes.

"Available" means the class, its public methods, their parameters and their observable
behaviour. The compatibility gate in this repository (`composer api:compat`) rejects any change
to that surface that is not explicitly allowlisted with a reason.

## What is not promised

- **`Xoops\ModuleTools\Internal\*`** (marked `@internal`) may change in any release.
- Method `@deprecated` in the docblock keeps working but will not receive fixes beyond
  security; the docblock names the replacement (for example `queryFAndCheck()` →
  `queryAndCheck()` / `$db->exec()`).
- Behaviour that was a **bug** is fixed, not preserved: the 1.5.0 changelog lists the cases
  (for example `Helper::getDirname()` — a method that never existed — now `dirname()`).

## Removal rules

A public surface may be removed only at a major boundary of the *next* platform line, and
only when **all** of the following hold:

1. its replacement is stable and its behavioural test corpus passes;
2. executable migration tooling, or a verified manual recipe, has shipped;
3. the maintainers' consumer audit reports zero remaining use;
4. an ecosystem search for third-party use has been done and recorded;
5. the published compatibility period has expired; and
6. a removal notice has shipped at least one release before removal.

Zero known use is a prerequisite, never permission by itself.

## Release checkpoints

| Checkpoint | What happens | Removal? |
|---|---|---|
| Every XOOPS 2.8.x release | API snapshot and compatibility gate re-run; parity corpora run | No |
| First XOOPS 4 compatibility release | A `moduletools-compat` package, migration recipes, open gaps and the support duration are published | No |
| Later XOOPS 4 releases | Ecosystem re-scan; newly eligible candidates announced | Only after the notice above |
| A XOOPS 4 major boundary | Candidates meeting all six rules removed; everything else retained | Per symbol |

The support duration ("N releases") is announced with the first XOOPS 4 compatibility release.
Until it is announced, the period is open-ended and nothing is removed.

## Versioning

ModuleTools 1.6.0 provides API 1.2.0. Consumers needing its added public surface
(such as `Common\Output::navigationTarget()`) must explicitly request
`minimumApiVersion: '1.2.0'` in `ConsumerRuntime::guard()`.
Existing consumers requiring API 1.0.0 or 1.1.0 remain supported.

- **1.x** — XOOPS 2.8 line. Minor releases add; they do not remove. PHP floor: 8.4 since 1.5.0.
- The library's `Bootstrap::API_VERSION` is the compatibility contract a module can assert
  with `ConsumerRuntime::guard()`; it changes only when a minor release adds API.

## Reporting a compatibility break

Open an issue with the module dirname, the ModuleTools version (`Bootstrap::VERSION`), the
call that changed, and the observed vs expected behaviour. A confirmed break in a promised
surface is treated as a release blocker.
