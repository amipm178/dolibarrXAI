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

A focused fix now updates `McpTool::normalizeInputSchema()` to preserve array-valued `const` values as one enum entry, infer empty-array constants as arrays, recurse only through schema-bearing keywords, and normalize legacy tuple-style `items` schemas. PHPUnit regression cases were added in `test/phpunit/McpToolSchemaTest.php`. The PHPUnit suite has not been executed in a Dolibarr environment; an isolated PHP harness passed eight assertions. No CI result is available yet.


Additional hardening: MCP execution logs now record the tool name and argument keys, not argument values, to reduce the risk of sensitive business data being copied into application logs.


## Follow-up security audit (2026-10-25)

The continued review identified five additional security/privacy/reliability issues plus one prompt-correctness issue and opened focused draft PRs:

- [PR #2 — MCP native-tool path confinement](https://github.com/amipm178/dolibarrXAI/pull/2): the old raw prefix comparison could accept a sibling directory with a matching name prefix; resolved paths are now checked against a directory-separator boundary, including symlink resolution.
- [PR #3 — MCP audit payload redaction](https://github.com/amipm178/dolibarrXAI/pull/3): when `AI_LOG_REQUESTS` is enabled, the MCP endpoint previously persisted raw request bodies, argument values, result content, and error details. The audit record now keeps bounded metadata (tool name, up to 50 argument keys, result/error status) without storing the payload values.
- [PR #4 — Internal system tools blocked from external MCP](https://github.com/amipm178/dolibarrXAI/pull/4): `MCPServer::handleToolsList()` calls `McpHandler::getToolsSchema()`, which includes system tools for first-party assistant orchestration. The external MCP context now omits these tools from discovery and rejects direct calls, including tools marked `is_system` only at definition level.
- [PR #5 — Inventory prompt correctness](https://github.com/amipm178/dolibarrXAI/pull/5): the advertised `inventory_health` prompt referred to a nonexistent `analyze_stock_forecast` tool. It now uses the available product and stock-movement bridge tools, requires ambiguity handling, bounds the movement history, and instructs the model to report insufficient data rather than fabricate a runway estimate. Product names retain accents and non-Latin characters.
- [PR #6 — Assistant privacy-redaction edge case](https://github.com/amipm178/dolibarrXAI/pull/6): an early local disambiguation path ran before the regular privacy guard, logged the matched phrase, and stored the original query (including restored document blocks). It now logs no matched phrase and stores a generic query marker when privacy redaction is enabled.
- [PR #7 — JSON-RPC params validation](https://github.com/amipm178/dolibarrXAI/pull/7): malformed JSON-RPC IDs, scalar/null `params`, scalar `_meta`, and non-string protocol-version values could reach typed MCP handlers or be coerced during the earlier transport-header check. The router rejects malformed shapes with JSON-RPC `-32602 Invalid params`; method-specific names, URIs, and argument containers are type-checked before dispatch. The earlier transport-header check now avoids unsafe coercion without aborting malformed batch items. Invalid request IDs are never echoed, and the explicit unsupported-version response is preserved.
- [PR #8 — Runtime error containment](https://github.com/amipm178/dolibarrXAI/pull/8): the protocol dispatcher caught `Exception` only, so PHP engine failures such as `TypeError` could bypass its generic JSON-RPC error path. It now catches `Throwable`, returns a generic server error, and avoids logging exception messages that may contain sensitive values.
- [PR #9 — Unconditional API user-context restoration](https://github.com/amipm178/dolibarrXAI/pull/9): `ToolApiBridge::execute()` temporarily replaces the global and REST API user, but the old restoration ran only at the normal end of the method. The new `finally` restores both contexts even if endpoint loading, reflection, hooks, or result shaping throws.
- [PR #10 — Shared-key service-user authorization](https://github.com/amipm178/dolibarrXAI/pull/10): the legacy shared-key path loaded `AI_MCP_USER_ID` without checking that the account was active or had `ai/assistant/use`, unlike individual API-key authentication. The shared-key path now rejects disabled users and users lacking that permission.
- [PR #11 — MCP credential type validation](https://github.com/amipm178/dolibarrXAI/pull/11): the credential parser accepted request-controlled values without verifying they were strings. Array-valued headers/query parameters could trigger string-conversion warnings. The parser now accepts only string credentials while preserving legacy string query credentials.

### Validation status

- Isolated PHP checks passed for the schema-normalization logic, path-boundary behavior, and MCP log-sanitization behavior.
- PHP syntax checks passed for the locally recreated test structures. This is not equivalent to linting the exact GitHub files or running the PHPUnit suite.
- PHPUnit and full Dolibarr integration tests remain unexecuted because the test runner/Dolibarr environment is unavailable here. GitHub Actions has not returned a workflow run for the draft PR heads.
- All eleven PRs remain draft and unmerged. Each requires review and a full test run before release.
