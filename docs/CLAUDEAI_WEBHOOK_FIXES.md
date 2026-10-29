# CLAUDEAI - Webhook module: credentials of a user in the payload

`core/triggers/interface_95_modWebhook_WebhookTriggers.class.php`. The payload posted to a target URL is the trigger object turned into an array by `dol_clone_in_array()`. Only the `db` property was removed (`removeDbFromPayload()`). A target configured on a `USER_*` trigger (create, modify...), or any object holding a user (author, validator...), posted the properties of the User object: `pass` (**still in clear in the object when the trigger runs on a creation**), `pass_crypted`, `pass_indatabase*`, `pass_temp`, `api_key`, `clicktodial_password`, `openid`. The users REST API never returns them whatever the permissions; same list as the Zapier fix (PR #23).

`removeDbFromPayload()` now also drops these keys at any depth of the payload. Checked with a stub script on the private method (reflection): the credentials of the object, of a nested user and of a user inside `lines` are gone; login, names, lines and an extrafield named `options_pass` are kept; objects still lose `db`. `php -l`. Not run against a real Dolibarr (no database); PHPUnit not run.

Reviewed, nothing to change: the call is made through `getURLContent(..., 0)` (local addresses refused unless `$dolibarr_allow_localurl_for_webhooks` is set in conf.php, as documented in the code), and the history uses its own database connection.
