# Constraints

Last reviewed: 2026-10-29 by the project owner

This file is the project's quality bar. Read it before writing code. Do not
weaken it to make a change pass: fix the code, or add an exception row with an
owner and an expiry date.

The numbers below were measured on this machine (PHP 8.2, XAMPP on Windows,
SQLite) before they were written down. Every row names the command that
produces the verdict.

## Floor (always enforced, no setup required)

- No new suppression comments: `@phpstan-ignore`, `@psalm-suppress`, `phpcs:ignore`, `@codeCoverageIgnore`, `nosemgrep`, `gitleaks:allow`.
- No unfinished work: `dd()`, `var_dump()`, `print_r()`, `TODO`, `FIXME`, `not implemented`, an empty `catch {}`.
- No skipped or deleted tests without a reason in the commit message.
- No secrets in source.
- A test that stays must keep its assertions.
- This file does not get weakened to make a change pass.

Checked by `node tools/floor-guard.mjs`, which reads the diff against
`origin/main` plus untracked files, so it sees work before it is committed. It
checks hand-written source only: published Filament assets under `public/` are
third-party build output and their minified bodies trip every pattern.

## Enforced with numbers

| Dimension | Rule | Checked by | Runs at |
|-----------|------|-----------|---------|
| Formatting | Zero style errors (257 files today) | `vendor/bin/pint --test` | every edit |
| Tests | Zero failures, 169 tests or more | `php artisan test` | task end |
| Queries per list page | At most 25, and the same count at 12 rows as at 60 rows | `php artisan test --filter=QueryBudgetTest` | task end |
| Built CSS | At most 790000 bytes total | `php tools/asset-budget.php` | task end |
| Built JS | At most 62000 bytes total | `php tools/asset-budget.php` | task end |
| Dependency advisories (PHP) | No high or critical, at most 0 | `composer audit --no-dev` | CI, task end |
| Dependency advisories (JS) | No high or critical, at most 0 | `npm audit --audit-level=high` | CI, task end |
| Secrets in staged files | At most 0 findings, values redacted | `gitleaks git --staged --redact --no-banner` | every edit |
| Secrets in history | At most 0 findings, values redacted | `gitleaks git --redact --no-banner` | ship |
| Floor | At most 0 violations | `node tools/floor-guard.mjs` | every edit |

`npm run build` must run before `tools/asset-budget.php`: the script reads
`public/build/assets`, not the sources.

The CSS ceiling was 660000 until Filament 5.11, which ships a heavier bundle:
708 kB of theme CSS (73.5 kB gzipped) against 577 kB before. The new number is
that measurement plus headroom, taken once, with the reason recorded here. Any
further growth is a finding, not a number to move.

The secrets scan allowlists `tests/` on purpose. The fixtures there hold fixed
placeholder passwords, and a real secret belongs in `.env`. A finding anywhere
else is a defect, not a false positive.

## Measured, not yet enforced

| Metric | Today | Direction |
|--------|-------|-----------|
| Coverage of `app/` | not measured: no xdebug or pcov on this machine | add a driver, then set the number |
| Slowest panel page (30 rows) | 21 queries on `/admin` | must not grow |
| Accessibility findings | not measured: a check needs a logged-in browser session | measure before enforcing |

## Exceptions

| ID | Rule | Path | Reason | Owner | Expires |
|----|------|------|--------|-------|---------|

## Running the checks

```
composer check:fast   # formatting, floor, staged secrets — the edit loop
composer check:task   # + the whole test suite, the query budgets, the bundle
composer check:full   # + dependency advisories, the full history scan
```

`.qoder/rules.md` carries the working rules; this file carries the numbers.
