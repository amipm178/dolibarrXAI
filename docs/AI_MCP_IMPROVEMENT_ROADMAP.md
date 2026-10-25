# XAI / MCP improvement roadmap (2026)

This roadmap tracks upstream Dolibarr work relevant to an AI-assisted Dolibarr fork. It is deliberately additive: upstream fixes should be reviewed and adapted to this fork rather than copied blindly.

## Upstream signals reviewed

- [Issue #38356 — AI MCP Server and chat assistant](https://github.com/Dolibarr/dolibarr/issues/38356): MCP is experimental in the 24.x line. The community roadmap highlights agent workflows, scheduled tasks, page context, additional tools, privacy, and protocol alignment.
- [PR #42210 — pdftotext argument injection](https://github.com/Dolibarr/dolibarr/pull/42210): user-controlled filenames must be passed as a single shell argument using escapeshellarg, rather than relying on escapeshellcmd.
- [PR #42211 — dynamic-price admin output escaping](https://github.com/Dolibarr/dolibarr/pull/42211): HTML output must be escaped in attributes as well as text; admin-only pages can still have stored-XSS exposure.
- [PR #42165 — bookkeeping triggers on transaction changes](https://github.com/Dolibarr/dolibarr/pull/42165) and [PR #42189 — trigger on draft validation](https://github.com/Dolibarr/dolibarr/pull/42189): module hooks must not be bypassed by bulk operations, cloning, or draft validation.
- [PR #42184 — customer deposit accounting](https://github.com/Dolibarr/dolibarr/pull/42184): accounting changes need explicit coverage for credit notes, fixed discounts, deposit invoices, and invoice variants.
- [PR #42213 — PHPStan CI on 23.0](https://github.com/Dolibarr/dolibarr/pull/42213): keep static-analysis configuration and baselines aligned with the PHP version and target branch.

## Priority plan

### P0 — Security and data integrity
- Audit shell command construction involving uploaded filenames and external tools. Prefer argument-safe process APIs or escapeshellarg for each argument.
- Audit HTML output contexts (text nodes, attributes, URLs, JavaScript) and apply context-appropriate escaping.
- Audit CSRF protection and authorization on every state-changing admin action.
- Ensure bookkeeping hooks execute for direct edits, clones, mass actions, and draft-to-ledger transitions. A trigger refusal must roll back the transaction cleanly.

### P1 — MCP safety and reliability
- Keep tools read-only by default; explicitly classify write tools and require a clear confirmation step before mutation.
- Enforce the current Dolibarr user's permissions server-side for every tool invocation. Never treat prompt instructions as authorization.
- Validate tool arguments against strict schemas and cap result sizes, execution time, and multi-step loop counts.
- Avoid sending unnecessary customer, employee, financial, or document data to external AI providers; redact sensitive fields and document data flows.
- Add regression tests for permission-denied calls, malformed arguments, rejected writes, and failures midway through a workflow.

### P2 — Useful MCP capabilities
- Implement or remove the dangling inventory_health prompt/tool reference so advertised capabilities always exist.
- Add server-computed receivables ageing and top-entity aggregates, with entity permissions and bounded date ranges.
- Support document metadata first; make file reads/downloads/uploads explicit, permission-checked operations with confirmation for writes.
- Improve page-context actions (summarize, translate, draft) without silently sending or saving content.
- Treat scheduled and agentic workflows as a separate design: durable task state, cancellation, audit trail, retries, and a confirmation gate for each write.

### P3 — Quality gates
- Add focused PHPUnit tests for every security or accounting fix and keep them deterministic.
- Run the repository's supported PHP static analysis and test jobs on the target branch before opening a PR.
- Prefer small, single-purpose branches and upstream-compatible patches; record the upstream issue/PR and local adaptation in each commit.
- Do not mark an upstream item complete until its implementation and regression tests are verified in this fork.

## Working checklist

- [ ] Compare this fork's implementation with the current upstream develop branch before backporting a fix.
- [ ] Confirm the target file and affected release branch; similar upstream fixes can differ between maintenance branches.
- [ ] Add a regression test that fails before the fix.
- [ ] Run the narrow test first, then the relevant full test/static-analysis job.
- [ ] Record test commands and results in the pull request.
- [ ] Review privacy and authorization implications for all AI/MCP features.

## Scope note

This document is an initial engineering triage, not a claim that each upstream issue affects this fork. The code-level audit and test results must establish applicability before implementation.

## Code-level verification (2026-10-25)

The first comparison against upstream `develop` confirms that the fork's core MCP implementation is currently byte-for-byte identical in these files:

- `htdocs/ai/assistant/parse_intent.php` — SHA `66e467004c27dda3fce5ebdd50586aa39538f164`
- `htdocs/ai/class/privacy_guard.class.php` — SHA `19e2b072c8fc7ea84320ef15a986f4b20a4ecea7`
- `htdocs/ai/class/mcp.class.php` — SHA `93f1cc04a340dfd89f678d38ec6b2663087cd934`
- `htdocs/ai/class/mcptool.class.php` — SHA `284aadf26b569568715e0a607d8a368cc6cbc018`
- `htdocs/ai/tools/api_bridge.class.php` — SHA `8799d8323e435d784d2fab57c913c63985b08d58`

This means these areas do not currently show fork-specific drift; it does **not** mean a runtime or security audit has passed.

### Verified upstream fixes already present

- [Issue #41827 / PR #41996](https://github.com/Dolibarr/dolibarr/pull/41996) — MCP schema normalization converts `const` to `enum` for Gemini compatibility. The `McpTool::normalizeInputSchema()` implementation is present in the fork and matches upstream.
- [PR #42106](https://github.com/Dolibarr/dolibarr/pull/42106) — clearer MCP write confirmations and validation/precheck improvements; merged upstream.
- [PR #42086](https://github.com/Dolibarr/dolibarr/pull/42086) — supplier reception and shipment validation behind the write-confirmation gate; merged upstream.

### Backport cautions

- [PR #42210](https://github.com/Dolibarr/dolibarr/pull/42210) and [PR #42211](https://github.com/Dolibarr/dolibarr/pull/42211) target maintenance branch `20.0`, not `develop`. Review their exact affected files and branch applicability before considering any backport; do not assume they are missing from this fork's target branch.
- No application-code change or test run is claimed by this comparison. Next verification should exercise schema normalization (including nested schemas), write-confirmation denial paths, permission checks, and privacy-redaction behavior with focused PHPUnit tests.



## Follow-up implementation

A focused fix now updates `McpTool::normalizeInputSchema()` to preserve array-valued `const` values as one enum entry, infer empty-array constants as arrays, and recurse only through schema-bearing keywords. PHPUnit regression cases were added in `test/phpunit/McpToolSchemaTest.php`. The tests have been committed but not executed in a Dolibarr/PHPUnit environment; no CI result is available yet.


Additional hardening: MCP execution logs now record the tool name and argument keys, not argument values, to reduce the risk of sensitive business data being copied into application logs.
