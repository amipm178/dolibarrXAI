# CLAUDEAI - batch 8: page context printed in an inline script, attachments check

## `window.aiPageContext` built from the URL (`class/actions_ai.class.php`)
On list pages the hook prints the user's filters (`search_*`, `sall`, `sortfield`...) taken from `$_GET` / `$_POST` inside an inline `<script>` with a plain `json_encode()`. That encoding escapes the slash of `</script>` but not `<`: a link such as `...list.php?search_ref=<!--<script>` changes how the browser parses the script element (double escaped state) and lets what follows run as part of it or break the page. The three prints now use `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. Checked: `<!--<script>alert(1)</script>` is output without any `<` (`\u003C!--...`) and decodes back to the same value; the page JS reads it as before (valid JSON).

## `ai_validate_attachments()` (`lib/ai.lib.php`)
The attachments come from the browser. An entry that was not `{mime: string, data: string}` (a string, a missing key, an array for `data`) raised a TypeError on `strlen()` or an undefined index. It is now refused with the usual "type not allowed" message.

Checked: `php -l`, the JSON encoding check above. PHPUnit not run (needs a database).
