# CLAUDEAI - batch 10: third party candidates ignored the user's visibility

`aiFindThirdpartiesInText()` (`lib/ai.lib.php`) documents `$user` as "User the text comes from (visibility of candidates)" but its SQL only filtered on the entity. Its candidates are shown to the user in the choice list of `parse_intent.php` and used to resolve a name to an id in `command.php` and the API bridge (`api_bridge.class.php`). So an **external user**, or an internal user **without "see all customers"**, could be offered - or have resolved - the name of a customer they cannot see anywhere else in Dolibarr (the lists and `aiRecentProducts()` already apply that rule).

- Candidates are now limited like the lists: external user -> own company; internal user without `societe/client/voir` -> third parties in `societe_commerciaux`.
- Masking is the opposite need (mask more names, not fewer): `aiThirdpartyNamesToMask()` passes the new `$ignoreVisibility = true`, so the privacy mask still covers every third party name found in the text.

Checked with a stub database that captures the SQL (5 checks: all customers visible -> no restriction; restricted rep -> `sc.fk_user = 7`; masking mode -> not restricted; external user -> `s.rowid = 5`; external in masking mode -> not restricted). `php -l`. PHPUnit not run (needs a database).

Behaviour change to be aware of: a sales representative who types the name of a customer they do not follow now gets "not found" instead of a candidate, as in the Dolibarr lists.
