# CLAUDEAI - batch 6: email tools and navigation link

## Navigation tool: the model could add an action to the link (`tools/navigation.class.php`)
`generate_navigation_url` merged the `params` object chosen by the model into the query string without any filter. The model reads data typed by third parties (mails, notes, names): a poisoned record could make it return a link with `action=delete&confirm=yes`, a `token`, `massaction`... or replace the `id` the permission check was done on. The user only has to click, and on an instance where the CSRF token is not enforced the page runs the operation. Only parameters that narrow a list or a search are kept now (`search_*`, `sall`, `sortfield`, `sortorder`, `page`, `limit`, `mode`, `socid`, `contextpage`, `type`, `status`, `viewstatut`, `leftmenu`, `mainmenu`, `optioncss`), scalar values only. Checked on the key list: `action`, `confirm`, `token`, `massaction`, `id`, `toselect` dropped; `search_thirdparty`, `search_status`, `sortfield`, `socid` kept.

## Email cleaner tool: filter on collector 5 also returned 50, 51, 512... (`tools/email_cleaner.class.php`)
The collector id was looked up with `raw_request_payload LIKE '%"collector_id":5%'`: no end to the number. Now `...:5,%` or `...:5}%` (compact JSON written by `json_encode`). The `message_id` / `context_profile_code` filters now go through `escapeforlike()` (a `%` or `_` typed there matched everything).

## Email cleaner class: unmasking into JSON (`class/emailcleaner.class.php`)
Same defect as PrivacyGuard in PR #17: masked values were put back unescaped in the JSON answer of the model before decoding, so a name with a double quote made the whole answer undecodable. Uses `unmaskAiResponse($text, true)` from PR #17 (until #17 is merged the extra argument is ignored: no error, behaviour as before).

Checked: `php -l`. PHPUnit not run (needs a database).
