# SmartString Design Decisions

Settled decisions with their rationale. Check here before proposing a feature,
a rename, or a refactor: if it's listed below, it was already debated.
Decisions can be reopened, but reopen them against the reasons recorded here,
not from scratch.

Only the road-not-taken half lives here; current behavior is self-documenting
in signatures, docblocks, the changelog, and tests.

- **`map()` runs its callback on null** - skip-on-null was implemented and
  reversed (2026-07-14). The recipe for strict builtins is
  `->ifNull('')->map(...)`.
- **`percent()` keeps its `$ifZero` parameter** - deprecating it was reversed
  (2026-07-13): a chained `ifZero()` can't see zero inside a formatted
  `"0.00%"`.
- **`ifZero()` does not recognize formatted zeros** like `'$0.00'` - parse-back
  coercion misfires and couples conditionals to display settings.
- **`wrap()` requires both sides** - a one-sided `wrap($before)` default was
  rejected: every real case is two-sided, and a required `$after` catches
  forgotten closing tags in the IDE.
- **The benchmark baseline is plain `htmlspecialchars()` without SmartString's
  extra flags** (2026-07-20). Adding the flags to the baseline was rejected: it
  would slow the baseline ~50% on long strings (ENT_DISALLOWED's per-char
  check) and benchmark a call nobody writes. Dropping SmartString's extra
  flags was also rejected: the tier scans check disallowed chars in the same
  pass, so the hardening is nearly free.
- **The performance table has no clean-article or numbers/empty rows** -
  removed after seeing real data: articles have apostrophes, and
  nanosecond-vs-nanosecond ratios mislead. The raw generator output still
  measures them.
- **Coverage is ~92% on purpose** - every uncovered line was dispositioned
  (2026-07-14): exit paths are subprocess-tested, and `SharedHelpers.php`
  keeps its unused branch because it is a twin of SmartArray's copy (identical
  except the namespace line) - never prune or edit one without the other.
