# CLAUDEAI - batch 4: API bridge limit, page context visibility

Found while reviewing `assistant/parse_intent.php` and `tools/api_bridge.class.php`.

## API bridge: `limit=0` removed the cap (`tools/api_bridge.class.php`)
The bridge caps an explicit `limit` with `min((int) $limit, BRIDGE_MAX_LIMIT)`. In the Dolibarr REST API a limit of 0 (or less) means "no limit", so `min(0, 100)` = 0 let the model - or any client of the MCP server - pull a whole table with one call (the comment above says one call must not pull thousands of full objects). A limit <= 0 (or not a number) now falls back to the default (25), above 100 is capped to 100. Checked: 0, -5, "abc" -> 25; 7 -> 7; 500 -> 100; "50" -> 50.

## Page context: visibility rules not applied (`assistant/parse_intent.php`)
The chat posts the card being viewed (element + id). The code checks the whitelist, the read right and the entity, then `fetch()`es the record and puts its ref and third party name in the system prompt. `fetch()` does not apply the visibility rules the lists apply, and the POST is client-controlled: a forged context handed the ref / third party name of any record to the model, hence to the answer. New `aiUserMaySeeContextObject()`: an external user sees its own company only; an internal user without "see all customers" (`societe/client/voir`) only the third parties they follow (`societe_commerciaux`). Real card pages are unaffected (Dolibarr already refuses to show them).

Checked: `php -l`. PHPUnit not run (needs a database).

## Reviewed, nothing to fix
`parse_intent.php` does not execute tools (only returns the intent; execution goes through `execute_tool.php`, already gated by right, CSRF, allow-list and confirmation). `api_bridge.class.php`: whitelist of endpoints and methods, flat write schemas, definition cache keyed on the security settings: consistent.
