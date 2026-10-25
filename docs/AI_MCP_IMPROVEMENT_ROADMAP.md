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
- [PR #12 — HTTP envelope error handling](https://github.com/amipm178/dolibarrXAI/pull/12): malformed JSON, oversized bodies, valid non-object JSON, and uncaught endpoint failures were not mapped to distinct transport/JSON-RPC errors. The endpoint now returns appropriate bounded errors, catches `Throwable`, and avoids appending a second response if audit logging fails.

### Validation status

- Isolated PHP checks passed for the schema-normalization logic, path-boundary behavior, and MCP log-sanitization behavior.
- A namespace-escaping issue in several newly added PHPUnit files was corrected during review. The exact repository test files have not been linted or executed locally; the earlier syntax checks covered locally recreated structures only.
- PHPUnit and full Dolibarr integration tests remain unexecuted because the test runner/Dolibarr environment is unavailable here. GitHub Actions has not returned a workflow run for the draft PR heads.
- PRs #1–#11 were squash-merged into `XCODE`; the HTTP-envelope changes from PR #12 were applied directly to `XCODE` alongside the already-integrated audit sanitizer and shared-key authorization. PR #12 was closed to avoid a conflicting parallel patch.
- All changes are now consolidated in the [`XCODE` branch](https://github.com/amipm178/dolibarrXAI/tree/XCODE). This is not a release sign-off: run the full PHPUnit suite and Dolibarr integration/security tests before deploying.


### Follow-up audit (2026-10-25)

- **JSON-RPC null IDs:** the protocol handler previously used `id === null` as the notification test, conflating a request with an explicit `"id": null` and a notification with no `id` field. It now tracks field presence separately. Regression coverage was added for null-ID success responses, null-ID errors, and genuine notifications.
- **Audit metadata privacy:** MCP argument logging now retains only recognized argument names. Unknown keys are replaced by a fixed `[redacted-key]` marker, because client-controlled object keys can themselves contain personal or business data. Regression coverage includes email/name-like keys and keys containing control characters.
- **Exception logging:** MCP tool execution and API-bridge hook failures now log the exception class instead of raw exception messages, which can include user input or business data.
- **Verification still required:** these latest regression tests have not been executed in a compatible Dolibarr/PHPUnit environment. Add HTTP-level tests for batch members, empty batches, notifications, transport-header mismatches and status codes; then run PHP lint, the focused PHPUnit files and the full supported suite before deployment.
- **Scope:** this follow-up audits the fork-specific XCODE changes and adjacent AI/MCP paths. It is not a line-by-line security audit of the entire upstream Dolibarr codebase.


### Continued audit updates (2026-10-25)

- **Untrusted identifier reflection:** unknown JSON-RPC methods, resource URIs, prompt names and unsupported protocol-version values are no longer echoed verbatim in error responses. This avoids reflecting arbitrary client input into responses and keeps error payloads bounded. Focused regression cases were added for these paths.
- **Audit metadata minimization:** string JSON-RPC IDs are now replaced with a fixed marker in both request and response audit records; only numeric/null IDs are retained for correlation. The logged method is restricted to known MCP methods, and the request parameter name is retained only when it matches a tool-like identifier pattern. Regression coverage checks email-like IDs/names and unknown methods.
- **Validation status remains unchanged:** source files and regression-test presence were re-fetched from GitHub after the commits, but PHP lint and PHPUnit execution have not been performed in a compatible Dolibarr environment. No workflow run was returned by the available commit-run lookup. Do not treat the branch as production-certified.

- **API bridge exception boundary:** unexpected non-REST exceptions now return a generic error to the caller and log only the exception class; invalid `sqlfilters` diagnostics no longer put the rejected filter into server logs. Deliberate `RestException` messages remain available for normal API validation and access-denial feedback. This change still requires focused tests against the supported Dolibarr API exception classes.

- **Assistant request boundary:** `parse_intent.php` now rejects non-object/malformed JSON bodies, validates the query type, caps the raw request body at 2 MiB before decoding, and returns HTTP 413 for oversized payloads. Page-context element/ID and follow-up flags are type-checked instead of loosely coercing arrays. The top-level assistant exception log now records only the exception class, not raw exception text, file paths or line numbers.

- **Write-preview error handling:** the generic write-confirmation path now catches `InvalidArgumentException` from argument normalization and returns a bounded validation message, matching the existing update-preview path. This prevents malformed or ambiguous write arguments from escaping the confirmation flow as an uncaught exception. Regression execution remains pending.

- **Write payload validation:** `request_data` no longer silently degrades malformed JSON to an empty payload or ignores scalar/list-shaped inputs. It now requires a valid JSON object or named-field array and raises a validation error before preview/confirmation. This prevents malformed write payloads from being mistaken for intentional empty input; regression tests and PHP lint still need to run.

- **Fail-closed write target access:** `userCanReach()` previously returned `true` when a write target class had no explicit permission mapping. It now denies access in that case, so adding a new target cannot silently bypass the MCP-side visibility check. Current unmapped targets include `WebsitePage`, `Fichinter` and `Contrat`; writes to those targets will be refused by this precheck until their Dolibarr object-access rules are explicitly mapped and tested. This is a deliberate security-over-availability guardrail.

- **Confirmation-storage log minimization:** database-driver error details are no longer appended to logs when a pending write confirmation cannot be stored. Driver diagnostics can include SQL fragments or values, so the log now records a stable generic failure label. The user-facing failure remains unchanged.

- Write-confirmation token entropy: replace timestamp/`mt_rand()`-derived bearer tokens with 32 bytes from PHP's cryptographically secure `random_bytes()`; token issuance fails closed if secure randomness is unavailable. Runtime tests and PHP lint remain pending.

- Bridge logging: creator-contact failure logs no longer include the business object's raw error string, which may contain SQL fragments or record data; logs retain only stable class/record/role identifiers and the result code. Runtime tests and PHP lint remain pending.

- Bridge logging: unexpected output captured from an in-process REST API call is no longer copied (even truncated) into logs, since it may contain customer data, tokens, SQL errors, or server paths. Logs now record only that unexpected output occurred. Runtime tests and PHP lint remain pending.

- AI debug logging: prompts, user queries, conversation history, cleaned model output and parsed intent/arguments are no longer written verbatim to logs when `AI_DEBUG` is enabled; metadata-only diagnostics remain. Runtime tests and PHP lint remain pending.

- MCP authentication logging: a failed API-key lookup no longer writes the database driver's raw error to logs, avoiding accidental disclosure of SQL or credential-derived values.

- MCP tool-call audit logs: argument keys are now passed through the existing allowlist-based sanitizer before logging, preventing attacker-controlled key names (including unusual text) from being copied into logs.

- **Write-confirmation argument fingerprinting:** the confirmation hash previously depended on `json_encode()`, which returns `false` for invalid UTF-8. Casting that failure to an empty string could collapse distinct malformed argument payloads to the same fingerprint. The hash now uses recursively key-sorted `serialize()` output, preserving string bytes and PHP value types. PHP lint, targeted tests, and runtime validation remain pending.

- **Authentication log minimization:** MCP API-key authentication logs no longer include user login names. They retain the numeric user row ID and the event outcome, preserving useful incident correlation while reducing unnecessary personal data in application logs. Runtime and privacy-policy validation remain pending.

- **Unknown tool-name reflection:** direct MCP tool calls with an unknown name previously returned that client-supplied name in the error response, and alias recovery logs included the raw requested name. The handler now returns a generic `Tool not found` error and logs only that an alias was recovered to the known canonical tool. PHP lint and protocol-level regression tests remain pending.

- **User-sentinel confirmation mismatch:** `execute()` resolved the literal `me` on user-reference fields to the acting user's numeric ID, but the write preview and precheck did not. For assignment tools this could display an unassignment preview while actually assigning the record to the caller, and could skip the intended assignee validation. Preview and precheck now apply the same sentinel resolution after normalization. Regression/runtime tests remain pending.
