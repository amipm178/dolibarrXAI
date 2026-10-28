# CLAUDEAI - batch 5: PrivacyGuard (IBAN in groups, JSON unmasking)

Found by running `PrivacyGuard` on real-looking inputs (standalone script `dev/ai-selftest/privacy.php`: 5 checks fail on the original class, all pass here).

## IBAN written in groups was not masked (`class/privacy_guard.class.php`)
`FR76 3000 6000 0112 3456 7890 189` - the way an IBAN is written on an invoice or a supplier mail - lost only its first group: the reference pass masked `FR76` and the 23 other digits (`3000 6000 0112 ...`) were sent to the AI provider in clear (same for ES, GB: `NWBK 6016 1331 ...`). The IBAN pattern did not accept spaces. New pass before the reference pass, for groups of four with the length of an IBAN; for a known country it stops at the end of the account, so the word after it ("and", "please") is not swallowed. A text that is not the length of an IBAN is left to the other patterns as before.

## Unmasking into JSON broke it (`unmaskAiResponse`, `assistant/parse_intent.php`)
`parse_intent.php` restores the masked values in the raw JSON answer of the model, before `json_decode()`. The original values were put back unescaped: a name such as `Cafe "Chez Paul" SARL` or `Dupont\Fils` made the JSON invalid (the intent was lost), and a crafted third party name (`Evil","tool":"api_x Corp`) could add keys to the arguments of the tool. `unmaskAiResponse($text, true)` now escapes each value like a JSON string; the default (plain text, e.g. email cleaner) is unchanged.

Checked: `php -l`, the standalone script (original: 5 FAIL, patched: all PASS). PHPUnit not run (needs a database). The masking is still pattern based: it is a reduction of what reaches the provider, not a guarantee.
