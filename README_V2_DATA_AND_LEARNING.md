# VashaSetu v2 — updated app ZIP, database, and learning mode

**Prepared October 2026.** This is a patch of your uploaded `vashasetu_app (12).zip`, not a connection to or migration of any live site. The app still uses **PHP and MySQL**. The ZIP contains CSV seed files and a MySQL schema; it does not contain a running MySQL database or a trained AI model.

## What was added

- `database/schema.sql`: new `language_catalog`, `regional_exact_pairs`, `indigenous_pairs`, `learning_units`, `learning_items`, `learning_progress`, and `learning_localizations` tables. Your original tables are preserved.
- `database/language_registry.csv`: **24 language/variety records** distinguishing regional Bangla from Indigenous languages, with scripts and coverage status.
- `database/regional_exact_pairs.csv`: **6,771** unambiguous pairs extracted from the supplied `BD-Dialect`-source review subset. Status `publication_reviewed_not_independently_reaudited`, **not independently validated**.
- `database/training/regional_{train,validation,test}.csv`: the previously prepared source-preserving **67,585 regional sentence pairs total**, ready for future model training and evaluation, not automatically certified. The conflicting-meaning review queue is retained in `database/training/regional_conflicting_meanings.csv` and excluded from the three splits.
- `database/indigenous_pairs.csv`: **20 research-derived sample sentence pairs** from the public MELD project's Chakma, Garo, Marma CSV. These are **Bengali-script transliterations, not original Chakma/Marma/Garo scripts**, and need validation for educational use. A dubious sample with incomplete Garo meaning was omitted. This is a sample seed, **not** the full corpus or a trained translator.
- `tools/import_full_meld.py`: imports **the complete MELD CSV** from its public GitHub repository (or a manually downloaded local CSV), preserving original language labels; excludes contradictory source-target entries; outputs `database/indigenous_pairs.csv`. A source row can still be incorrect even if noncontradictory; this is why community review remains necessary.
- `database/learning_units.csv` (10 ordered mini-units), `database/learning_items.csv` (58 beginner exercises): **sounds → letters → phonetics/syllables → words → sentences**.
- `learn.php`, `api/learn.php`, `assets/js/learn.js`, `assets/css/learn.css`: a working beginner-learning screen; choice questions with feedback, listen buttons, unit unlocking, and progress. Logged-in progress saves in MySQL; guest progress stays in the browser only.
- `database/learning_localizations.csv`: an **empty template** for first-language instructions with `review_status=community_verified`. It is deliberately not populated with invented translations.
- `api/matching_engine.php`: no longer pools word meanings from other regional varieties when uncertain. Uses the exact-match BD-Dialect candidate table before falling back to the original word-level translator. Explicit source-variety selection is encouraged; language recognition remains uncertain.
- `api/quiz.php`: legacy dialect quizzes now require a `verified` mapping rather than relying only on a high statistical probability.

## Install in XAMPP / update on your host

1. **Back up** your existing `vashasetu` MySQL database and existing app folder (especially user data and approved contributions).
2. Extract the ZIP; copy its inner `vashasetu_app` folder into your web root. If your previous deployment is named `vashasetu`, keep that folder name and replace the contents, not the directory path used by the app.
3. Edit `api/config.php` if your database server/credentials are not the XAMPP defaults.
4. Open `http://localhost/vashasetu/setup.php` **without** `?reset=1` on an existing installation. It creates the new tables and loads them, without truncating existing dictionaries, users, or history. On a new install, the same URL creates everything.
5. Open `http://localhost/vashasetu/learn.php` (or click **Learn Bangla** from the welcome/Translate screen).
6. For a cached PWA, reload the page; the service worker cache version was bumped.

`?reset=1` is **not required** for installing the new learning features. It reloads original dictionary data and may take time; use a backup first. Learning-progress rows are preserved by not truncating their parent tables on reset.

## Add the full research dataset — no fabricated translations

The whole public MELD research CSV could not be bundled automatically in this environment. On your own Windows laptop with internet access:

```powershell
cd C:\xampp\htdocs\vashasetu
python tools\import_full_meld.py --download
```

Or download the CSV from https://github.com/AnzirRahman/MELD and run:

```powershell
python tools\import_full_meld.py "C:\Downloads\Language_Dataset_Chakma_Garo_Marma.csv"
```

Then open `http://localhost/vashasetu/setup.php?refresh_indigenous=1` to replace **only** the Indigenous phrase table. The importer needs standard Python only. Review a representative sample with Chakma, Marma, and Garo educators **before showing samples as correct learning prompts**.

To add community-reviewed teaching instructions, put rows in `database/learning_localizations.csv` with these columns:

`language_id,unit_key,item_order,prompt_native,hint_native,script,audio_path,review_status,reviewer`

Set `review_status` to `community_verified` **only after a bilingual reviewer checks them**. Open `setup.php?refresh_localizations=1`. Currently there are no such rows.

## Teaching design and current limitations

- **Sounds**: listen and distinguish vowel/consonant sounds. `speechSynthesis` plays the device's installed `bn` voice when available. **No recorded native audio** was included, and speech synthesis is not reliable for all Bengali phonetic contrasts.
- **Letters**: connect heard sounds and IPA approximations to Bengali letters.
- **Phonetics**: discover vowel marks such as `ক + া = কা` and syllable combinations; IPA is approximate and should be reviewed by a phonetics educator.
- **Words**: family/home/everyday vocabulary, taught using short meaning-to-word exercises.
- **Sentences**: basic statements and greetings using Standard Bangla.
- **Progress**: instant feedback, retry after mistakes, and unlocked units after all current questions have been answered correctly. It is a beginner prototype, not a complete spaced-repetition or personalized pronunciation course.
- **Indigenous-language adaptation**: learners can select their first language and view attributed sample phrase equivalents where available; **graded explanations are not localized** until native-speaker-verified instructions are provided. The native examples are not read aloud through Bengali TTS.
- **Real translation model**: this ZIP does **not** contain trained weights or a server for free-form Chakma/Marma/Garo translation; the current translator remains a rule/statistical word-based system. Building high-quality native-language-to-Bangla translation requires substantially more speaker-validated parallel data, recordings, and held-out evaluation.
- **Region naming**: the existing physical map locations are not language boundaries; language and district should not be treated as interchangeable.

## Original data attribution and cautions

- MELD: Mahi, M. H., Khan, A. R., Anik, M. H., Mojumdar, M. U. et al. (2025), *MELD: A multilingual ethnic dataset of Chakma, Garo, and Marma in Bengali script with English and standard Bengali translation*, Data in Brief / Mendeley Data DOI: 10.17632/dy5dyfygbp.4 — **CC BY 4.0**. Repository: https://github.com/AnzirRahman/MELD
- BD-Dialect: original dataset source in prior preparation kit: https://data.mendeley.com/datasets/k769s4vk5z/2 . Native-speaker review is reported by dataset authors; individual items are not reverified here.
- Original app includes other datasets, including potentially noncommercial/share-alike corpora. Review existing `DATA_SOURCES.txt` and original licenses before a commercial/Play Store release.

## What I verified / did not verify

- Checked CSV structural integrity, option/answer consistency, and PHP and JavaScript syntax. **No live MySQL server or browser-based end-to-end run was available in the build environment.** Import correctness should be checked on XAMPP before deploying.
- No Indigenous phoneme inventory, native orthography, audio recording, or curriculum explanation was invented.
