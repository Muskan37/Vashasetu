# VashaSetu V3 — regional and Indigenous dataset update

### Install
1. Back up your current VashaSetu folder and MySQL database.
2. Unzip this project into XAMPP `htdocs/vashasetu` (do not remove your current database unless you intend to).
3. Start MySQL and Apache. Open `http://localhost/vashasetu/setup.php`.
4. For previously installed V3 tables, use `http://localhost/vashasetu/setup.php?refresh_parallel=1&refresh_words=1` **after a backup** to replace the two evidence tables with the isolated files.
5. Open the app and select the source and target languages explicitly; `auto` deliberately runs language detection across known dialects.
6. Open `learn.php` for sound → letters → phonetics → words → phrases lessons. Localized Indigenous instructions and native audio require separately verified recordings and teaching content.

### Dataset contents
* 67,605 source-specific parallel sentence candidates (not all independently speaker-reviewed). Their CSVs are in `database/languages/<language>/sentences.csv`, with provenance, split, review status, and eligible-exact-match flags.
* 7,607 independent single-word meaning candidates in `database/languages/<language>/words.csv`. Never derive word meanings by aligning sentence words by position. Definitions that lack evidence show `Meaning not verified`.
* 23 language folders; some intentionally have only headers because their datasets are not yet available.
* The old `lexicon`, `corpus`, `bigrams` are preserved for archival/backward compatibility but are NOT used when a language is explicitly selected for translation. The word quiz now uses language-isolated evidence.
* Research MELD sample is only 20 entries. To obtain complete MELD: download from https://data.mendeley.com/datasets/dy5dyfygbp/4, import with `python tools/import_full_meld.py /path/to/meld.csv`, then run `python tools/build_corpus_v3.py` to repartition and rebuild evidence. Back up SQL before reimporting. Cite MELD CC BY 4.0 appropriately.

### Isolation behavior
When Noakhali → Standard Bangla is chosen, the query has `WHERE language='Noakhali'`; no other dialect sentence or lexicon records are used. Standard Bangla acts as a shared pivot. If a source and target are two distinct dialects, only those two datasets are used in an explicit two-step mapping; failure returns unavailable instead of consulting a third dialect. The optional approximation is an isolated, warned word-for-word gloss, not a proper sentence translation. Word-level meanings and full sentence translations are computed separately.

### Important caveats
* Completeness is impossible for natural language, so the dataset contains **all currently available compatible source candidates in the project**, not all possible human sentences. The large regional research portion is labelled `needs_native_review` and not enabled by default for reliable exact translation.
* The MELD dataset includes Bengali-script transcriptions of Indigenous-language speech; it does not substitute for the community's preferred orthography or vetted native-language instruction.
* Syntactic/static checks and dataset partition validation do not replace a real XAMPP/MySQL integration test or a native-speaker evaluation. Live accuracy is NOT guaranteed.
