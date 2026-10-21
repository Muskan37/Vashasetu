# VashaSetu V5 — independent dictionary and output-first translation

This release builds on **VashaSetu V4**. The underlying datasets, teaching modules, and MySQL installation process are otherwise retained. It does not certify or replace the regional research datasets.

## What's different?

### Dictionary

- The dictionary has its **own** language selector. Choose any listed regional variety or Indigenous language without changing the translator's output or input selection.
- The language selection is remembered in the same browser (`vs_dictionary_language`).
- The quick region chips and the full dropdown both modify **only** the dictionary.
- `api/dictionary.php` validates the selected ID and queries only `word_meanings.language = selected_language`. It no longer silently falls back to Chattogram.
- Identical-spelling words with documented per-language meaning are included rather than excluded automatically.
- A language without word-level data shows **No published word meanings yet**. Empty Indigenous-language folders are not populated with other varieties' meanings.
- Dictionary cards and examples use the selected source language only. Clicking a word from a translation breakdown uses its actual detected/selected source when available.

### Translator

- **Input:** 'Detect automatically' by default. Users may manually override it using the input button, particularly when their sentence is ambiguous.
- **Output:** Explicitly chosen by the user. The output selection is remembered in the browser (`vs_translate_target`). A dictionary or map selection never secretly rewrites it. The map action **Translate in [region]** deliberately sets that output.
- Automatic detection first checks language-scoped exact phrase evidence, and also checks whether the same phrase exists as Standard Bangla. Shared phrases cannot be assigned to one region without confirmation.
- For non-exact phrases, any statistical prediction must meet a strict multiword threshold, otherwise the user sees a source-confirmation prompt. This avoids silently mixing Noakhali and Barishal datasets, but also means some otherwise valid input will need manual source selection.
- The only stage that compares across language datasets is the **detection** stage. Following a source decision, the translation lookup still only uses the source dataset, Standard Bangla pivot, and selected target dataset. There is no global dialect word pooling.
- The translated **sentence** and source-word meanings are displayed separately. Individual definitions come from the word-level source dictionary (not translated-word position). Unknown or ambiguous words remain unverified rather than made up.
- The user can still deliberately swap translation direction or choose an input dialect manually.

## Installing on XAMPP

1. **Complete any MySQL recovery first.** If MySQL is crashing, do not run `setup.php` or overwrite its data directories to install this update.
2. Back up your existing `C:\xampp\htdocs\vashasetu\` folder and `vashasetu` MySQL database (SQL export).
3. Extract this ZIP into `C:\xampp\htdocs\vashasetu\` so that `index.php`, `api/`, `assets/`, `database/`, and `setup.php` are directly inside this folder.
4. Start Apache and MySQL, then run `http://localhost/vashasetu/setup.php` only when MySQL is healthy. Check the installer's on-page information before confirming any database-writing operation.
5. Open `http://localhost/vashasetu/index.php`, then use the **Words** and **Translate** tabs.
6. If you still see the old interface, reload with Ctrl+F5 and clear the old VashaSetu service-worker cache if needed. Cache name updated to `vashasetu-shell-v18`.

## What is not claimed

- The existing 67K+ sentence candidates are **not** all native-speaker verified.
- Auto-detection is **not** guaranteed: words can be used in multiple districts, and some Indigenous languages lack classifier training examples.
- A language with no entries cannot be translated accurately just because it appears in a dropdown.
- No data from a missing language is replaced with unrelated translations.
- The demo/mock UI was tested and PHP/JavaScript syntax was checked. This build was **not tested against the user's actual MySQL server**, which is currently being recovered. In this execution environment the PHP CLI lacks the PDO MySQL and mbstring extensions, so full database integration could not be exercised.

## Technical reference

- `assets/js/app.js`: separate `D.dialect` from `S.from` / `S.to`; language selection and response-confirmation UI.
- `api/dictionary.php`: strict dictionary language validation and query scope.
- `api/word_evidence.php`: `vs_auto_source()`, source confirmation and isolated translation.
- `index.php` / `assets/css/app.css`: dropdown and source-detection UI.
- `sw.js`: service worker cache rotation.
