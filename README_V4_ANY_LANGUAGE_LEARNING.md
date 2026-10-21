# VashaSetu V4 — choose ANY teaching language → ANY target language

This is a modification of the user's V3 ZIP, not a separate mockup. The original translator and separate V3 language datasets remain intact.

## Start / upgrade

1. Back up the complete existing `vashasetu` XAMPP directory and MySQL database.
2. Extract this ZIP and copy `vashasetu_app` contents into `htdocs/vashasetu`.
3. Start Apache and MySQL in XAMPP.
4. Open `http://localhost/vashasetu/setup.php` **once** to create `course_progress` and `pair_course_items`. It does not delete existing users or their old progress. **Previous V3 scores stay in `learning_progress`, but cannot be assigned to a particular teaching-language pair, so V4 course counters begin fresh.**
5. Open `http://localhost/vashasetu/learn.php`.

Do **not** use `?reset=1` for a normal upgrade. It resets other content tables. If the old V3 data was not installed, run the standard setup normally and allow it to populate the V3 datasets.

## What is implemented

- Separate **I speak** and **I want to learn** selectors across all entries of the V3 language catalog, including Standard Bangla, six existing dialects, other regional varieties, Chakma, Marma, Garo and others.
- Swap direction. Changing either language refreshes only that pair's course and data.
- Individual course progress per `(user ID, source language, target language, item kind, item ID)` for signed-in users; isolated browser progress per language pair for guests.
- Standard Bangla target uses the existing ten-unit starter path: sounds, letters, phonetics, words and sentences. Explanations remain in English unless that particular native-language localization is *community_verified* in `learning_localizations`.
- Targets other than Standard Bangla display the **six-stage course path**: sounds, letters, phonetics, words, sentences, conversations. Only community-verified material can become a scored lesson.
- Optional, **ungraded research flashcards** for words and sentences in regional or Indigenous language datasets. This previews independently attested target-language records and attempts a *source-language gloss* only through **the same Standard Bangla meaning**, from the chosen source language. If it cannot find a unique source match, the interface explicitly displays Standard Bangla as a reference instead of pretending it translated into the learner's language.
- A separate `pair_course_items` database table for reviewed pair-specific lessons with phonetics and optional native audio. The content must be imported by an educator using a CLI-only tool; end users cannot mark research data as verified.
- Standard Bangla speech uses an installed Bengali browser voice where available. Nonstandard dialects/Indigenous languages do **not** pretend the Bengali speech engine speaks them; they require native recordings.
- Source and target languages must be distinct. Every learning query uses exactly the two chosen IDs, plus Standard Bangla as a labeled reference bridge; it never searches other dialect datasets.

## Quality limitations (important)

- Merely selecting languages does **not** mean a complete course for that pair exists.
- The V3 67,605 sentence pairs and 7,607 word records contain a large amount of unverified or publication-reviewed-but-not-reaudited data. **This release does not relabel these records as correct or use them as graded lesson answers.**
- Most source-target pairs have **no community-reviewed native-language instruction or actual native audio** yet. Accordingly, their sounds/letters/phonetics stages display *Awaiting verified lessons*. Where samples exist, browse labeled **research flashcards** instead of scored lessons.
- Even aligned phrases need manual review. A Standard Bangla bridge is not proof of a perfectly equivalent regional-language expression. Non-standard scripts such as Chakma/Marma require approved orthography and audio.
- V4 does **not** train a new neural network; it adds course selection, pair-specific tracking, vetted-content infrastructure and research browsing to the PHP/MySQL app.

## How to publish a true pair course

1. Recruit educators fluent in **both** selected languages (and respect Indigenous communities' script preferences and consent for audio).
2. Copy `database/reviewed/verified_pair_lesson_TEMPLATE.csv` to `database/reviewed/approved_lessons.csv`.
3. Fill out reviewed exercises. `source_language` and `target_language` must be exact `language_catalog.language_id` strings: e.g. `noakhali-bn` → `barishal-bn`; **not** names like `Noakhali` or `Barishal`. `unit_key` and `stage` must both be `sounds`, `letters`, `phonetics`, `words`, `sentences`, or `conversations`.
4. `prompt_native` is written in the learner's **selected source language**, with `target_text` in the target language; `options_json` is a JSON array of 2–8 different possible answers. `correct_answer` must appear in the JSON options. Each item must be independently checked by an identified reviewer and attributed to its source.
5. Use `review_status=community_verified` **only after actual review**. Blank or unverified materials must not be marked verified.
6. For listening lessons, obtain a licensed, consented native recording and place it under `assets/audio/` with a `.mp3`, `.ogg`, or `.wav` extension; put its relative path in `audio_path`. Do not use generic Standard Bangla TTS to fake a dialect.
7. From the app directory run `php tools/import_verified_lessons.php database/reviewed/approved_lessons.csv`. Requires the MySQL PDO extension available in normal XAMPP PHP.
8. Refresh the learning page. Verified questions appear only for the selected source-target combination and never for another pair.

There are *no made-up translated examples* in the verified lesson import template. One target can be taught from many source languages, each with its own vetted localized wording.

## Database changes

- `course_progress`: per-user + per-language-pair tracking; existing `learning_progress` table preserved for legacy compatibility.
- `pair_course_items`: pair-specific lessons, stage, QA options, reviewer, original source, validated status, optional pronunciation and native audio path.
- No change to V3 `parallel_sentences`, `word_meanings`, their isolated folders, or the core translation engine.

## Testing performed

- PHP syntax lint, JavaScript parser syntax check, schema/table existence and CSV integrity checks.
- Mocked-browser interface test for source/target selection, swapping, Standard Bangla quiz, guest progress and research previews (where browser policy allows).
- **Not tested against an actual running MySQL server in this environment**, so back up the database and test setup in XAMPP before production deployment.

## Suggested next stage

For each target variety: collect native audio and script review, record contrastive phonemes and syllables, verify basic words and aligned everyday sentence/dialogue pairs with **two native reviewers**, then publish approved exercises pair-by-pair. Preserve provenance, dialect specificity, regional variation, and speaker consent.

### Importer safety note

Sounds and listening-choice questions must include a native `audio_path`; otherwise the importer refuses the row rather than silently asking the learner to guess. For other stages, audio is optional but recommended. For full pronunciation support, add consented recordings with original speaker metadata in a separate project-maintained registry.
