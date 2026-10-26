# CLAUDEAI - MCP server fixes

Reproduced with the standalone harness in `dev/ai-selftest/` (16 checks fail on the original code, all pass here).

- System tools (`respond_to_user`, `ask_for_confirmation`, `ask_for_clarification`, `reject_general_question`) were listed by `tools/list` and callable by `tools/call`, even with `AI_MCP_SERVER_ALLOWED_TOOLS=NONE`. Hidden and refused in the MCP server context only; the assistant UI is unchanged.
- `catch (Exception)` did not take `TypeError`: a scalar `params`, a non-string tool name or array `arguments` ended as a PHP fatal with an HTML body. `Throwable` is caught and bad params answer JSON-RPC `-32602`.
- Unknown resource / prompt: `-32602`, name no longer echoed.
- Prompt `inventory_health`: `system` role is not allowed by MCP, and `analyze_stock_forecast` does not exist. Now uses `api_products_list`, `api_products_stock`, `api_stockmovements_list`.
- `resources/read` read an undefined property when the company name was unset.
- Invalid UTF-8 in a tool result made `json_encode` return `false` as the `text` of the result.
- Notifications (single or batch) answer `202`; invalid batch members get `-32600`; parse / size / internal errors get a real HTTP status.
- An audit-log failure after the answer was sent appended a second JSON document.
- Shared key: a disabled `AI_MCP_USER_ID` account is refused.
- `?key[]=x` was cast to the credential `"Array"`.
- Tool argument values are no longer written to the syslog (names only).
- Confirmation state comes from `random_bytes()`; a non-string `requestState` is refused.

Left as is: `normalizeInputSchema()` turns an array `const` into an `enum` of its items (an upstream test asserts it); the MCP request log still stores raw bodies.
