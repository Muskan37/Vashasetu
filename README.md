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
| Voice | Mic with Stop button; Listen needs a Bangla voice installed on the phone. |
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
| Listen is silent | Install the Bengali voice: Settings → Languages → Text-to-speech output → Google → Install voice data. |
| Setup stops halfway | Run it again with `?reset=1`, or from the command line: `C:\xampp\php\php.exe setup.php --reset`. |

## 8. Test results (22 Oct 2026)
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
