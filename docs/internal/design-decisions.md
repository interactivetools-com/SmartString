# SmartString Design Decisions

Settled decisions with their rationale. Check here before proposing a feature,
a rename, or a refactor: if it's listed below, it was already debated.
Decisions can be reopened, but reopen them against the reasons recorded here,
not from scratch.

Only the road-not-taken half lives here; current behavior is self-documenting
in signatures, docblocks, the changelog, and tests.

- **`map()` runs its callback on null** - skip-on-null was implemented and
  reversed (2026-07-14). The recipe for strict builtins is
  `->map('strval')->map(...)` (updated 2026-08-04: the old `ifNull('')`
  recipe only covered null, not numeric values).
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
- **The news-article page mix has no clean-body, numbers, or empty fields** -
  articles have apostrophes, so the 10 KB body is the quoted variant, and
  sub-microsecond fields would not move the page total. The main table still
  measures numbers, empties, and clean text at every size as their own rows.
- **Coverage is ~92% on purpose** - every uncovered line was dispositioned
  (2026-07-14): exit paths are subprocess-tested, and `SharedHelpers.php`
  keeps its unused branch because it is a twin of SmartArray's copy (identical
  except the namespace line) - never prune or edit one without the other.
- **HTML composition stops at the two terminal exits `appendHtml()`/`wrapHtml()`**
  (shipped 2026-07-14, ruled final 2026-08-10). A SmartHtml type,
  encode-on-append, and entity-sniffing were rejected: if richer safe-HTML
  composition is ever needed, the design is a dedicated safe-HTML type, not
  name suffixes or content sniffing. Further `*Html()` variants were rejected
  too - the presence-guard-wrapper idiom from the 2026-07 pattern sweep is
  fully served by these two, and both are terminal (plain-string return) so
  markup can't be re-encoded downstream. No prepend-side method:
  `wrapHtml($before, '')` covers it.
- **The `@` on `trigger_error()` stays** (2026-08-10). `logDeprecation()` sends
  notices as `@trigger_error(...)`, and the `@` mutes PHP's own display *and*
  its logging, so only a `set_error_handler()` ever sees them. That is the
  intent: notices are for handlers that collect them (CMS Builder's developer
  log), never for page output or PHP's default error log. Don't remove the `@`
  to make PHP log them. The rule is stated at the call site in
  `SharedHelpers.php`, a twin of SmartArray's copy, so any change goes to both.
