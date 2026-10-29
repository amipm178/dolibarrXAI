# CLAUDEAI - Zapier module review

Files: `zapier/class/api_zapier.class.php`, `core/triggers/interface_95_modZapier_ZapierTriggers.class.php`. Checked with stub scripts (REST `POST /zapier/hook`: 10 checks fail on the original, all pass here; user payload: 11 checks pass). `php -l` on both files. PHPUnit not run (needs a database), not tested against a real Zapier account.

## 1. The REST subscription dropped `module`, `action`, `event` and `caller` - the webhooks never fired
`post()` did `$request_data = $this->validate($request_data, $fields)`. `validate()` returns the mandatory fields only (`url`), so the loop that fills the Hook ran on `['url' => ...]` alone. The hook was saved with `module` and `action` NULL, and the trigger looks hooks up with `WHERE module = 'company' AND action = 'create'`: **a Zap subscribed through the API was never called**. The `caller` branch (loop protection) was dead too. `validate()` is now only a check, and the loop reads the request.

## 2. Mass assignment (same loop, once fixed)
Once the loop reads the request, any property sent would be assigned (`entity`, `rowid`, `import_key`, `date_creation`...). Only `url`, `module`, `action`, `event`, `status` (and `caller` for the context) are taken; `module` / `action` are normalised to lowercase `[a-z0-9_-.]` like the trigger does; non scalar values and a non object body answer 400; `url` must be an http(s) URL (`fk_user` is still forced to the API user). The server side post already refuses local addresses (`getURLContent(..., 0)`).

## 3. USER_CREATE / USER_MODIFY posted the whole user to an external service
The payload was `cleanObjectDatas(user)`: password hashes (`pass_crypted`, `pass_indatabase*`, `pass_temp`), `api_key`, `clicktodial_password`, `openid`, salary (`salary`, `thm`, `tjm`...) went to Zapier. The users REST API never returns them whatever the permissions. New `cleanUserDatas()` removes the same list (plus `rights`, `conf`, which are large and useless there); ordinary fields (id, login, email, names) are kept.

## 4. Small
- The webhook URL is the secret that lets anyone post to a Zap: the syslog line of `post()` printed the whole request (`print_r`); it now logs the field names only.
- Trigger: `$actions[1]` undefined index for an action code without `_`.

## Not changed (to decide)
- `getModulesChoices()` returns `invoices / orders / thirdparties / contacts / users` while the trigger compares the prefix of the code (`company`, `order`, `user`...). I cannot see what the Zapier app sends, so I did not touch it: if the app sends the plural keys, no hook ever matches either.
- The trigger query does not filter on the entity (multicompany): a hook of one entity is called with the data of another. I could not confirm which entity `Hook::create()` stores, so I left it.
- Hooks are readable (`GET /zapier/hook`) by anyone with the `zapier read` right, whoever created them.
