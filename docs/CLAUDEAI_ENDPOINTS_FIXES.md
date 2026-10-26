# CLAUDEAI - AI web endpoints fixes

- `ajax/generate_content.php`: the (billed) provider call ran before the empty-prompt check; no CSRF check on the raw JSON body; reachable by external users; provider error text printed unescaped.
- `ajax/chat_history.php` `pin`: `UPDATE ... JOIN ... SET` is MySQL-only, and on MySQL pinning an already pinned message answered "Message not found" (0 affected rows). Oversized messages are cut to the column size.
- `assistant/download_pdf.php`: malformed data (keyed `details`, scalar rows, JSON scalar) raised an uncaught TypeError; input size capped.
- `class/ai.class.php`: `catch (Exception)` -> `Throwable`.

Note: `generate_content.php` does not require `ai/assistant/use` (not granted by default; it would cut the AI button of the editors). It refuses external users and checks the CSRF token sent by `html.formai.class.php`.
