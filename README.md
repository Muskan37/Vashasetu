# Vashasetu (ভাষাসেতু) — Regional Bangla Dialect Translator & Learning App

Course project: Software Engineering (CSE 355), UITS.
Team: Sadia Akter Liza, Shobnom Sultana Muskan.
Stack: PHP 8 + MySQL (XAMPP) with a vanilla-JS installable web app (PWA).

This README describes the **current full build**. Older notes are in `docs/`; the change history is `docs/CHANGELOG_V6.md`.

---

## 1. Install on your computer (XAMPP)
1. Open the XAMPP Control Panel and **Start** Apache and MySQL.
2. Copy this folder to `C:\xampp\htdocs\` and name it `vashasetu`.
3. Open **http://localhost/vashasetu/setup.php?reset=1** once (15–30 seconds). Green lines ending in "Done" mean success.
4. Open **http://localhost/vashasetu/**.

`api/config.php` is already set for XAMPP (user `root`, blank password, database `vashasetu`).

### Updating to a newer build
1. **Back up** `database/user_edits.csv` and `database/curated.csv` (they hold dictionary edits).
2. Unzip the new build over the folder, replacing files.
3. Put your backed-up `user_edits.csv` back into `database/`.
4. Open `setup.php?reset=1` again. It reloads the data and re-applies every saved edit.

## 2. Use it on a phone (free, HTTPS)
1. Start XAMPP (Apache + MySQL).
2. In PowerShell: `cd C:\tunnel` then `.\cloudflared.exe tunnel --url http://localhost`
   (download cloudflared with `curl.exe -L -o cloudflared.exe https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe`).
3. Open the printed `https://….trycloudflare.com/vashasetu/` link on the phone and choose **Add to Home Screen**.

The link works only while your computer, XAMPP and the tunnel window are on, and it changes every time you restart the tunnel.
After updating the app, remove it from the phone's home screen and add it again so the new version loads.

## 3. What the app does
| Feature | Notes |
|---|---|
| Translate | Input: Bangla script, Banglish (Roman letters), English, voice, or a photo. Output: Standard Bangla, English, or a dialect. |
| Dialects (6) | Barishal, Chattogram, Mymensingh, Noakhali, Rajshahi, Sylheti. |
| Dialect → dialect | Word by word through Standard Bangla (needs the "Also translate word by word" box ticked). |
| Indigenous languages | Chakma, Marma, Garo (MELD data, exact sentences only) plus placeholders for others. |
| Word boxes | Every word of a result shows its meaning; tap for the word card. |
| Dictionary ("Words") | Lists all words per language; Chakma/Marma/Garo also list the MELD phrases. |
| Edit the dictionary | ✎ opens one form with the dialect word and its meaning; **＋ Add a word**; Delete. |
| Learn | Standard Bangla starter lessons (sounds, letters…), plus a **Start quiz** for Words and Sentences of any language pair that has data. Login needed to save progress. |
| Voice | Mic with Stop button. Listen uses the phone's Bangla voice if it has one; otherwise the app's server makes the sound (`api/tts.php`), so nothing has to be installed. |
| Map / districts | Tap a region or choose from all 64 districts. |
| History, streak, word of the day, quiz, contribute | Included. |

## 4. How dictionary edits work
* An edit changes the live word list (database) **and** is appended to `database/user_edits.csv`.
* For the six Bangla dialects it also updates `database/curated.csv`, the trusted list the translator reads, so the next translation uses it immediately.
* `setup.php?reset=1` reloads everything and then re-applies `user_edits.csv`. Keep a copy of that file.
* To stop strangers editing through a public link, set `EDIT_PASSCODE` in `api/edit_word.php`.

## 5. Data and honesty about quality
* Dialect pairs come from public research corpora (Vashantor / Unified-Bangla, BanglaDial, BD-Dialect, MELD). See `DATA_SOURCES.txt` for sources and licences.
* **Most word meanings are learned automatically and are NOT checked by native speakers.** The dictionary shows them so it is complete, but some are wrong. Chattogram forms for common verbs especially need review.
* Only `database/curated.csv` and words marked "native speaker reported" are trusted.
* **The whole Noakhali word list (537 words, `database/languages/noakhali/words.csv`) was reviewed by a native speaker on 1 Nov 2026**; 17 words were corrected. Where one Standard word has several Noakhali spellings (আমি: আইঁ / আঁই / আই), the first is used in translations and the others are kept as approved alternatives.
* **The Barishal word list (`database/languages/barishal/words.csv`) was reviewed by a native speaker in Nov 2026.** 654 pairs are marked "native speaker reported"; 76 pairs whose meaning looked doubtful are kept as "needs native review" until the reviewer confirms them.
* **The Garo word list (`database/languages/garo/words.csv`) was written and reviewed by a native speaker** (115 words, Nov 2026). For indigenous languages only these reviewed words are used; a word not in the list is left unchanged.
* MELD (Chakma, Marma, Garo) is research data in Bengali-script transliteration; it has not been independently re-audited. Check its licence on the Mendeley page before publishing.
* Word lists for Chakma/Marma/Garo are generated from sentence pairs and may contain mistakes; fix them with ✎.
* Sounds, letters and phonetics lessons exist only for Standard Bangla. There is no audio for minority languages yet.

## 6. Folder guide
| Path | What |
|---|---|
| `index.php`, `learn.php`, `manifest.json`, `sw.js` | Pages, PWA manifest, offline cache |
| `api/` | PHP endpoints (translate, dictionary, edit_word, learn, auth…) |
| `assets/` | JavaScript, CSS, images |
| `database/` | CSV data loaded by `setup.php`; `languages/<name>/` holds each language's own data |
| `tools/` | Python helpers that built the data (not needed to run the app) |
| `docs/` | Older READMEs and the change log |

## 7. Troubleshooting
| Problem | Fix |
|---|---|
| "Database not ready" | Start MySQL in XAMPP, then open `setup.php` once. |
| Words screen empty or "Database unavailable" | Open `setup.php?reset=1`. |
| Old screen after an update | Hard-refresh (Ctrl+F5); on the phone remove and re-add the app. |
| Voice writes nothing | Allow the microphone for the site; check the internet; try the keyboard microphone. |
| Listen is silent | The computer running XAMPP needs internet the first time a sentence is spoken (after that the clip is kept in `database/tts_cache/`). For an official voice service, put a Google Cloud Text-to-Speech key in `GOOGLE_TTS_KEY` in `api/tts.php`. |
| Setup stops halfway | Run it again with `?reset=1`, or from the command line: `C:\xampp\php\php.exe setup.php --reset`. |

## 8. Automated tests (31 Oct 2026)
Start the app on XAMPP (Apache + MySQL), then run from this folder. Results go to `tests/results/` (JSON and screenshots); the full write-up is the separate Software Test Report.

| Command | What it checks | Result |
|---|---|---|
| `phpunit` | PHPUnit 9.6 suite in `tests/phpunit/` (Unit, Data, Integration, HttpFlows): equivalence partitioning and boundary values for the core functions, word-list quality, translation/database flows, and the account, translate, dictionary, history and profile APIs (needs the app running at `BASE` for the Http suite) | 405/405 pass |
| `python tests/mutation_check.py` | Plants 24 small bugs one at a time and checks that the Unit and Integration tests catch them | 23/24 caught (1 has no visible effect) |
| `bash tests/run_coverage.sh` | Runs PHPUnit and the Python suites with code coverage switched on and merges everything into `tests/results/coverage/html/index.html` | see the lab report |
| `php tests/unit_tests.php` | 19 unit tests of the PHP functions (text clean-up, input checks, Noakhali rules, English verb forms, address detection) | 19/19 pass |
| `python tests/code_scan.py` | 9 code checks: syntax of every PHP/JS/Python file, no dangerous functions, no SQL built from user input, escaped output, no passwords in the code, file locking | 9/9 pass |
| `python tests/api_tests.py` | 20 core behaviours (translation rules, dictionary, login, learn, setup lock) | 20/20 pass |
| `python tests/system_tests.py` | 115 server test cases: translation (including English grammar), input checks, dictionary editing and admin undo, accounts, history, learning, quiz, voice, security, speed | 115/115 pass |
| `python tests/ui_tests.py` | 40 browser test cases on a phone-sized Chrome: every screen, English input, voice, Listen, Scan, Words editing, Learn, quiz, layout at 360–414 px, install and offline | 40/40 pass |
| `python tests/security_tests.py` | 39 attack tests: password guessing (brute force, 2,000-attempt flood, spoofed addresses), flooding, SQL injection, XSS, CSRF, clickjacking, sessions, hidden files, headers, fuzzing | 39/39 pass |
| `python tests/load_tests.py` | 10 load tests: normal load, 50–300 users, 500-request spike, 3-minute soak, memory, recovery, MySQL connections (about 7 minutes) | 10/10 pass |
| `python tests/reliability_tests.py` | 9 tests: 40 people editing at the same moment, edits surviving a reset, MySQL stopping and starting, sessions after an Apache restart | 9/9 pass |
| `python tests/a11y_perf_tests.py` | 11 tests: accessibility (WCAG with axe-core) on 10 screens, keyboard, 200% zoom, page speed on fast and slow 4G, 6 screen sizes, dark mode, reduced motion | 11/11 pass |

PHPUnit: `composer require --dev phpunit/phpunit:^9.6` (or the PHPUnit 9.6 .phar), then `vendor/bin/phpunit` from this folder. For coverage on XAMPP enable PCOV or Xdebug in `php.ini` and follow the comments in `tests/phpunit/coverage/prepend.php`.
Needs `pip install requests playwright` and `python -m playwright install chromium`. Set `BASE=http://localhost/vashasetu` for the XAMPP copy.
* Accessibility test: run `npm install axe-core` once in any folder and set `AXE_JS` to `node_modules/axe-core/axe.min.js` in it.
* Security test BF-07 uses a second address of the same computer: set `ALT_BASE=http://<your-LAN-IP>/vashasetu` (it is skipped otherwise).
* Reliability test stops MySQL: on Windows set `APP_DIR=C:/xampp/htdocs/vashasetu`, `MYSQL_STOP="C:/xampp/mysql/bin/mysqladmin -uroot shutdown"` and `MYSQL_START="start C:/xampp/mysql_start.bat"`, or `SKIP_DB_STOP=1` to skip that part.
* Run the load and security tests on your own computer only, never against someone else's server.

Bugs found by these tests and fixed: Noakhali → another dialect gave Standard Bangla instead of the other dialect; unknown languages or wrong data types gave an empty "500" error (now a clear 400 message); very long words ran out of their boxes; Learn used pop-up alerts; after opening the app offline a logged-in person was treated as a guest until reload; English sentences were translated word by word in English word order.
Found and fixed by the PHPUnit lab (2 Nov 2026): Bangla place/possessive endings after a vowel letter (বইে → বইয়ে, ভাইের → ভাইয়ের); "I am lying" stopped PHP with an error; -ie verbs (dies, died) not recognised; Bangla passwords counted in bytes, not letters; an email longer than 150 characters gave a 500 error; "Mr."/"Dr." ended a sentence (মি। Karim); ো typed as ে + া was not recognised; Banglish full stops stayed "." instead of "।"; 34 typing mistakes, 2 invalid spellings and one duplicate in the word lists (`tools/clean_word_lists.py`).
Found and fixed in the second round (security, load and reliability): no limit on edits, sign-ups or contributions; the tunnel address header could be faked; another website could log a user out (CSRF); pages could be framed; very weak passwords were accepted; unknown session ids were accepted; `tests/` and `.github/` could be downloaded; a huge Words page number gave a 500 error; persistent MySQL connections used up all 151 connections under load; 40 edits at the same moment could wipe most of the trusted word list (81 rows → 5); PHP warnings showed file paths; Noakhali পড়বো না was not changed to হইড়তান ন; three accessibility problems (map role, unlabelled dialect list, low-contrast text in Learn).
English input now uses grammar rules (`api/lib/en_grammar.php`): Bangla word order and the right verb form for the tense and person, e.g. "I will eat rice" → আমি ভাত খাবো, "Do you eat fish?" → তুমি কি মাছ খাও?, "I did not go" → আমি যাইনি. English can also be translated straight into a dialect (through Standard Bangla). Sentences the rules cannot read still fall back to word by word.

## 8b. Accuracy results (22 Oct 2026)
Word-by-word accuracy on 150 held-out corpus sentences per dialect (Rajshahi 64), counting a word correct only if it matches the reference spelling exactly:

| Dialect | Standard → dialect (words) | Dialect → Standard (words) |
|---|---|---|
| Barishal | 79% | 87% |
| Chattogram | 55% | 76% |
| Mymensingh | 79% | 90% |
| Noakhali | 66% | 85% |
| Rajshahi | 89% (small set) | 93% |
| Sylheti | 61% | 80% |

Many "errors" are spelling variants (আঁই / অ্যাঁই, যাইয়ুম / যাইয়্যুম), so real accuracy is higher, but these numbers are not a native-speaker evaluation.
Old trusted entries were checked against the corpus; 28 that the corpus contradicts were removed (14 from curated.csv, 14 from lexicon.csv) (e.g. Chattogram করমু, হানি; Noakhali খামু, যামু).
Mymensingh -বাম verb forms and Chattogram খাইয়ুম are kept because a native speaker confirmed them.

## 9. Adding word lists (checked first)
Put lists in `database/reviewed/` and run `python tools/import_checked_lists.py`, then open `setup.php?reset=1`.
Before anything is loaded, every entry is checked against that language's own data (parallel corpora, BanglaDial word counts, MELD):
* **Confirmed** — the corpus pairs that Standard word with that dialect form. Only confirmed forms that are also common (at least a quarter of the corpus uses) change translations.
* **Contradicted / Not found** — kept in the dictionary as "needs native review", never used for translation.
* Phrases and sentences become exact translations only if every dialect word occurs in that dialect's data and nothing a native speaker entered disagrees.
The result for every entry is in `database/reviewed/check_report.xlsx`.
Checked so far: `chattogram_native_dataset.csv`, `bangladesh_languages_and_dialects.xlsx`, and the real part of the "complete mega dataset" (`mega_dataset_real_items.csv`; its copied sheets and generated sentences were discarded).

## 10. Exact sentence translations from the research corpora
`tools/enable_exact_matches.py` lets about 43,000 sentence pairs from Kothon, Dwadash, Vashantor, ONUBAD, SylhetiDS and ChatgaiyyaAlap
give whole-sentence translations. A pair is used only if: no dataset gives a different translation for either side, its words line up
with known word pairs, it has 2–25 words in Bangla script only, and it does not overrule a word a native speaker confirmed.
About 24,000 pairs were left out for these reasons. When a sentence is matched exactly, the word boxes still show each word's own meaning
(taken from the matched sentence where the words line up, otherwise from the word-by-word engine).
Order of the data scripts: `import_checked_lists.py`, then `enable_exact_matches.py`, then `setup.php?reset=1`.

## 11. Native-speaker rules
* `database/reviewed/native_confirmations.csv` — words a native speaker confirmed (column `default`: yes = the translator uses it, no = real but only shown in the dictionary).
* `database/reviewed/native_sentences.csv` — whole sentences a native speaker gave; always used as the exact translation.
* Noakhali "will not" (confirmed: আমি ভাত খাবো না = আইঁ ভাত খাইতান ন): the verb takes -ইতান and না becomes ন after it,
  e.g. যাবো না → যাইতান ন, করবো না → কইরতান ন, দেখবো না → দেইখতান ন, বলবো না → কইতান ন. Irregular verbs (দেবো, নেবো) are left unchanged.

## 12. Learning a dialect from an indigenous language
When the language to learn is any Bangla variety (Standard or a dialect such as Noakhali), the path starts with the Bangla-script
lessons (sounds, letters, phonetics — 36 exercises, unlocked one after another), because every dialect is written with the same letters.
Then come the Words and Sentences quizzes for that dialect, built from its own data and shown next to the learner's language where a match exists.
Every page links the app manifest and service worker, so the browser itself offers "Install app" / "Add to Home screen".

## 13. Security
* Passwords are stored only as bcrypt hashes (`password_hash`); all database queries use prepared statements.
* New passwords need at least 8 characters, must not be a very common password (12345678, password…) and must not be the email address.
* Login: at most 8 wrong passwords per address or account in 15 minutes; while blocked even the right password is refused. A new session id is issued at login and registration, and unknown session ids are rejected (strict mode).
* Rate limits per address: 60 dictionary edits and 30 contributions per 10 minutes, 10 new accounts per hour. Someone typing on the XAMPP computer itself is not limited.
* The visitor's address is taken from the Cloudflare tunnel header only when the request really comes through the tunnel, so it cannot be faked to get round the limits.
* Changes sent by another website (a hidden form or script on a different site, CSRF) are refused; pages cannot be shown inside another website's frame (clickjacking).
* Session cookie: HttpOnly, SameSite=Lax, and Secure when opened through HTTPS (also through the Cloudflare tunnel).
* `setup.php` only works on the computer running XAMPP (http://localhost/...), never through the public tunnel link.
* Editing the dictionary is open to anyone with the link for now (`REQUIRE_LOGIN = false` in `api/edit_word.php`). Every edit is saved with the editor's internet address and what the word was before: all edits in `database/user_edits.csv`, and separately per address in `database/edits_by_ip/<address>.csv`. Edits are written under a file lock, so many people editing at once cannot damage the files.
  Open http://localhost/vashasetu/admin_edits.php on the laptop to see edits grouped by address and undo all edits from one address. Set `REQUIRE_LOGIN = true` (and optionally `EDITOR_EMAILS`) to allow only logged-in editors.
* `.htaccess` files stop the `database/`, `tools/`, `docs/`, `tests/` and `.github/` folders and .md/.csv/.py/.sql/.xlsx files from being downloaded.
* PHP warnings and error details are never shown to visitors (they go to the PHP error log), even though XAMPP has display_errors on.
* When MySQL is briefly full under heavy load, requests wait and try again instead of failing; if it stays full the visitor sees "The server is busy. Please try again in a moment."
* Keep regular backups of the database and `database/user_edits.csv`.

## 14. All of Dwadash (12 Bengali varieties)
`tools/add_dwadash_full.py path/to/Dwadash.xlsx` adds the Dwadash columns the first build skipped:
Rajshahi (+885 sentence pairs, now about 2,100) and five new dialects — Rangpur (1,156), Narail, Kishoreganj, Narsingdi and Tangail (about 500 each).
Each gets its own sentence data, a learned word list and dialect-recognition words; they appear in the translator, dictionary, quiz and Learn.
With only a few hundred sentences their word-by-word translations are rough; whole sentences from the dataset are matched exactly.
Order of the data scripts: `import_checked_lists.py`, `add_dwadash_full.py`, `enable_exact_matches.py`, then `setup.php?reset=1`.
