# CLAUDEAI - batch 9: substitution array printed in an inline script

`core/class/html.formai.class.php`, `getAjaxAICallFunction()` writes `let substit = <json>;` in an inline `<script>`. `$this->substit` is the result of `complete_substitutions_array()`: third party names, addresses, contact names, references... typed by users. `json_encode()` with its defaults escapes the slash of `</script>` but not `<`: a third party named `<!--<script>` (a value only another user needs to have saved) changes how the browser parses the script element on every page that shows an AI-enabled editor for that record, and breaks it or lets what follows run as script. Same fix as PR #20 (`class/actions_ai.class.php`): `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`; the value decodes to the same array (checked in PR #20).

One line, no behaviour change for ordinary data. `php -l` only; PHPUnit not run.

Reviewed, nothing to change: the output of `ai/ajax/generate_content.php` is sanitized (`dolPrintHTML`, `dol_string_onlythesehtmltags`) before it reaches `.html(response)`; the error path is escaped by PR #14.
