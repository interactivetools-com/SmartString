#!/usr/bin/env bash
# Scan library code with Codex Security. Maintainer tooling: runs locally, not
# in CI, and needs the codex-security CLI installed. Results go to the CLI's
# state dir; view them with: codex-security scans list
# The defaults (gpt-5.6-sol, xhigh effort, stop-after-no-new 4) are already the
# strongest settings; extra flags only lower them. Run scans one repo at a time:
# concurrent scans share a sandbox dir in /tmp and kill each other's workers.
# Full pre-release scan:
#   .github/scripts/codex-security-scan.sh --mode deep
set -euo pipefail
cd "$(dirname "$0")/../.."

# There's no exclude flag, so build the path list here: everything except tests,
# gitignored files (vendor, caches, .idea) and __* scratch notes. Skipping those
# keeps the scan on shipped code; the scratch notes also get quoted back as
# evidence, which we don't want steering the results.
shopt -s dotglob
paths=()
for entry in *; do
    if [[ $entry == .git || $entry == tests || $entry == __* ]] || git check-ignore -q "$entry"; then
        continue
    fi
    paths+=(--path "$entry")
done

# The scan prompt lives here (written to a temp file at runtime) so the repo
# needs no scratch file. It states the real contract: raw VALUE access for
# logic is documented API; only raw MARKUP output is restricted to the four
# named methods. Without that split, every documented raw accessor gets
# reported as an encoding bypass.
prompt_file=$(mktemp)
trap 'rm -f "$prompt_file"' EXIT
cat > "$prompt_file" <<'PROMPT'
This is a whole-library pre-release scan of SmartString, a PHP 8.1+ string
wrapper whose core contract is automatic HTML-encoding on output. tests/ and
vendor/ are excluded on purpose; everything else is the shipping code. Treat
findings as release blockers, not incremental review notes.

Core security promise to verify: whenever a SmartString is converted to a
string for output (echo, interpolation, __toString(), htmlEncode()), the
value is HTML-encoded. Unencoded markup can only reach output through the
named methods nl2br(), rawHtml(), appendHtml(), and wrapHtml().

Intended API, not findings: raw value access for logic is documented
behavior. value(), string(), getRawValue(), and their deprecated aliases
return the original value for math, comparisons, and SQL parameters.
jsonSerialize() returns the raw value so json_encode() produces transport
JSON; jsonEncode() is the HTML/script embedding path. map() and apply()
callbacks receive the raw value and their result is rewrapped. __debugInfo()
shows the stored value. int(), float(), bool(), and the if*() conditionals
use documented PHP cast and loose-comparison semantics. Only flag these if a
concrete in-repo path renders their result into HTML without encoding.

Prioritize, in order:

1. XSS: any path where the original unencoded value reaches output without one
   of the named raw methods, including __toString(), the JSON and URL
   encoding methods, and format or transform methods that rebuild strings.
2. Encoding gaps: double-encoding assumptions, invalid UTF-8 handling, and
   htmlspecialchars flag choices.
3. Type juggling in numeric conversions and comparisons.
4. Unbounded work on attacker-controlled text: loops whose pass count depends
   on input syntax.
PROMPT

codex-security scan . "${paths[@]}" \
    --knowledge-base docs/encoding-and-html.md \
    --scan-prompt-file "$prompt_file" \
    "$@"
