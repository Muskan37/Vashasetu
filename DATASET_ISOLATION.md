# VashaSetu V3 — Language dataset isolation

Each language has its **own source dataset folder** under `database/languages/<language>/`, containing `sentences.csv` and `words.csv` (empty with headers if no verified source exists). `database/languages/manifest.csv` supplies the importer with allowed paths and claimed counts. The installer checks every row's `language` against the manifest before loading it.

The MySQL tables `parallel_sentences` and `word_meanings` use the `language` column. Every exact-match lookup and individual word lookup has `WHERE language=?`. **Selecting Noakhali does not query Chattogram, Sylheti, Marma or any other variety.** Standard Bangla is a shared neutral pivot, not a dialect source. An explicit selection bypasses automatic dialect classification.

Only the two explicitly named datasets may be used for source-to-target dialect transfer (through Standard Bangla), and both must have unambiguous exact pairs. `auto` is the only mode that examines varieties for *language detection*, and it should not be confused with a locked selection. English and Standard Bangla translation are separate shared-language services.

The old raw `lexicon` and mixed-corpus `bigrams` are **not used** for explicitly selected dialect translation. Enable approximate mode only to show an isolated word-by-word gloss when every individual word is attested; its output is labelled approximate and is not a reliable sentence translation.

Each source row contains `source_dataset`, `review_status`, and a train/validation/test split. Counts are counts of source candidates, **not** counts of independently verified translations. The published research may contain transcription and semantic errors. MELD examples currently bundled are only a small sample, NOT the full corpus; see `tools/import_full_meld.py`.

For a new language, add its language ID to `api/word_evidence.php` or `api/matching_engine.php` as appropriate, enter its real source data in its directory, update `manifest.csv` (or regenerate with `tools/split_datasets.py`), and install with `setup.php?refresh_parallel=1&refresh_words=1`. Do not invent unavailable translations or label provisional labels verified.

## Important limitation
A physical per-language table per dialect is **not required** for isolation. Language predicates at every query boundary and input validation are the important safeguards. Dataset isolation is a correctness policy, not a linguistic quality guarantee. Existing unverified research and word-pair candidates need native-speaker assessment before being presented as teaching truths.
