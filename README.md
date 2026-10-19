# Vashasetu (ভাষাসেতু) — Regional Bangla Dialect Translator

Runs on your own computer with **XAMPP** (PHP + MySQL). No hosting needed.
Course: Software Engineering (CSE 355). Team: Sadia Akter Liza, Shobnom Sultana Muskan.

## Set it up in 4 steps (XAMPP)
1. Install XAMPP, open the **XAMPP Control Panel**, and press **Start** next to **Apache** and **MySQL**.
2. Copy this whole folder into `C:\xampp\htdocs\` and rename it **`vashasetu`**
   (so you have `C:\xampp\htdocs\vashasetu\index.php`).
3. Open **http://localhost/vashasetu/setup.php** once. It creates the database `vashasetu`, all tables, and
   loads the dictionary (about 107,000 dialect word pairs, 35,000 example sentences, 120,000 Bangla words
   and 93,000 English words). It takes about 15–30 seconds. (After you replace the files with a newer version, open
   `setup.php?reset=1` once to reload the data.) You will see green lines ending in "Done".
4. Open **http://localhost/vashasetu/** — that is the app.

`api/config.php` is already set for XAMPP (`localhost`, database `vashasetu`, user `root`, blank password),
so you do not edit anything. Use `?reset=1` on setup.php (`setup.php?reset=1`) to wipe and reload the dictionary.

## What the translator does (Google-Translate style, dialect dictionary)
| Feature | How it works here |
|---|---|
| **Detect language** | "Detect language" in the From box. Tells Bangla script, Banglish (Roman letters) and English apart, then guesses the dialect (Standard, Chattogram, Sylheti, Noakhali, Barishal, Mymensingh) with a confidence %. |
| **Banglish** | Type `tumi kemon acho` or `ami bhat khaisi`. A phonetic matcher finds the Bangla words (shows "Understood as Bangla"; tap a word to try another spelling). |
| **Any direction** | Dialect → Standard, Standard → dialect, dialect → dialect (through Standard), plus English (word-by-word and common phrases). |
| **Swap** | The ⇄ button swaps the languages and moves the result into the input box. |
| **Live translation** | Translates as you type; the history entry is saved when you leave the box or press "Translate & save". |
| **Romanisation** | A pronunciation line in Roman letters under each Bangla result. |
| **Listen / Copy** | Bangla text-to-speech (needs a Bengali voice on the device) and one-tap copy. |
| **Word card** | Tap any word: meaning, how it is said in each dialect, English meaning, example sentences. |
| **Dictionary** | The "Words" tab: browse/search each dialect in Bangla, Banglish or English. |
| **Voice + image** | Speak (Chrome) or scan a photo (offline OCR), as before. |
| History, login, Contribute, quiz, word of the day, streak | unchanged. |

## Where the dictionary comes from (and how good it is)
* **Dialect pairs & sentences:** learned from the **Vashantor / Unified-Bangla** parallel corpus
  (Standard Bangla aligned with Barishal, Chattogram, Mymensingh, Noakhali and Sylheti; 2,500 sentences),
  extended with ONUBAD, Kothon, Dwadash, ChatgaiyyaAlap, a Sylheti dataset, a combined CC-BY corpus and a
  Bangla→Chittagonian corpus (about 40,000 more pairs; full credits in `DATA_SOURCES.txt`).
  Translation is always built word by word from these pairs, using neighbouring words to pick the meaning;
  it never looks up a stored sentence.
  Licence CC BY 4.0 — https://github.com/tasfiaisrat/Unified-Bangla . Word pairs were produced with
  statistical word alignment (IBM Model 1), so they are **data-driven, not hand-written**.
* **Verified phrases:** a few Chittagonian words checked against the Wikivoyage phrasebook (CC BY-SA) are
  marked ✓ verified.
* **Bangla word list and English→Bangla dictionary:** MinhasKamal/BengaliDictionary (GPL-3.0),
  https://github.com/MinhasKamal/BengaliDictionary .
* **Measured earlier on the original 2,500 sentences only** (trained without the test sentences), word error rate vs the Standard Bangla reference,
  copying the text unchanged → using Vashasetu: Barishal 0.68 → 0.22, Mymensingh 0.44 → 0.12,
  Sylheti 0.57 → 0.36, Noakhali 0.73 → 0.41, Chattogram 0.86 → 0.50 (lower is better).
  Dialect guessing: about 87% correct on held-out sentences across the six varieties (Sylheti is the hardest).
  Banglish: the right Bangla word is the top suggestion about 87% of the time and in the top five 99%
  (tested on automatically generated casual spellings).
* **Rangpur, Dhaka, Rajshahi, Khulna** have no public data yet; they show "coming soon" on the map.

## Honest limitations
* Output is word-level. It will not rearrange grammar like a neural model. Standard Bangla → English reorders
  simple subject–verb sentences (about 16 common verbs); other English output is word-by-word, plus a
  built-in list of common phrases.
* On sentences the app has never seen, about 50–75% of words come out right (dialect → Standard) and 50–70%
  (Standard → dialect), depending on the dialect; only 5–45% of whole sentences are exactly right.
* Chattogram and Noakhali are the weakest (their spelling varies a lot in the corpus).
* Short inputs (1–2 words) make dialect detection uncertain, and the app says so.
* Voice recognition uses Chrome's general Bengali model, which mishears heavy dialect.
* Admin approval of Contribute entries is done in phpMyAdmin: table `contributions`, set `status` to
  `approved` and the translator uses the word immediately.

## What's inside
* `index.php` — the whole interface (one page). `assets/` — CSS, JS, fonts, images, offline OCR engine.
* `api/` — `translate.php`, `lookup.php`, `dictionary.php`, `word.php`, `quiz.php`, `history.php`, `contribute.php`,
  `auth.php`, `config.php`, `matching_engine.php`, `lib/text.php` (Banglish phonetics + romanisation).
* `database/` — `schema.sql` plus the CSV data files that `setup.php` loads.
* `sw.js`, `manifest.json`, `icons/` — installable app (PWA). `privacy-policy.html`.

## Accounts & login
Create an account to keep history and a preferred dialect (passwords are hashed with `password_hash`). Guests
can use everything; their history is tied to the browser session.

## Installable as an app (PWA)
Vashasetu is set up as a Progressive Web App, so visitors on phones can install it like a
real app: open it in Chrome/Safari, tap the browser menu → **"Add to Home Screen"** (or
Chrome may prompt automatically), and it launches full-screen with its own icon — no
browser address bar, no app store needed. This works from the same URL, temporary a hosted domain included.

This does **not** make it a native app (still a website under the hood — see the app vs.
web-app distinction), but it closes the "feels like an app" gap: home-screen icon,
full-screen launch, and the shell (UI) still loads even with a flaky connection, since it's
cached by a small service worker (`sw.js`). Live data — translations, history, contributions
— always goes straight to the server and is never cached, so results are always accurate.

## Image to text (new)
On the Translate screen, the **camera button** (next to the mic) lets you pick a photo or take
one, read the Bengali text in it, and translate it:

1. Tap the camera button, then choose a photo (phones also offer "take photo")
2. The text it finds appears in the text box, **where you can correct it**
3. Press **Translate** as usual

How it works: OCR (Tesseract.js with the Bengali model) runs **inside the browser**. The image is
never uploaded, nothing needs an API key, and because the engine and language data are bundled in
`assets/ocr/`, it works offline too (good for a demo with no wifi).

Honest limits (say this if asked):
- Works best on **printed/typed** Bengali, in good light, in focus. **Handwriting is unreliable.**
- OCR can misread letters, which is why the text is shown for checking before translating.
- If the reader fails to start, the `assets/ocr/` folder was not uploaded (the whole folder must go
  next to `index.php`).

## Voice not working? Check these
| What you see | Cause / fix |
|---|---|
| "Voice input needs Chrome or Edge" | Use Chrome or Edge (Firefox has no speech recognition) |
| "Voice needs a secure page" | Open via `http://localhost/...` or `https://`. A plain `http://192.168.x.x` address is blocked by browsers |
| "Microphone is blocked" | Click the lock icon in the address bar and allow Microphone |
| "needs internet" | Chrome's speech recognition sends audio to Google, so it needs a connection |
| "No Bengali voice is installed" (speaker button) | Add a Bengali voice in your device's Speech settings. Android: Google Text-to-speech. Windows: Settings, Time and language, Speech |

Speech recognition uses Chrome's general Bengali model, so heavy dialect speech is often written
in standard spelling or misheard. That is a real limit of any general speech engine. Typing or
scanning an image are the fallbacks.

## Updating the app (important)
`sw.js` was fixed: the old version cached `index.php` and kept showing the old page even after you
uploaded a new one. The new version always fetches the newest page and only uses its cache when
you are offline. After uploading new files, if the old page still shows once, reload with
**Ctrl+Shift+R**. Always replace the **whole folder**, not just one file.

## Publishing to the Google Play Store
Vashasetu is a PWA, so it can't be submitted as-is — it needs to be wrapped as a **TWA
(Trusted Web Activity)**, Google's official way to publish web apps to Play. This requires
your site to be **live at a real HTTPS URL first** (your Hostinger temporary domain works).

1. **Deploy the app** (Steps 1–5 above) and confirm it works at your live URL
2. **Edit `privacy-policy.html`** — replace `REPLACE_WITH_YOUR_EMAIL` with a real contact
   email, then re-upload that one file
3. Go to **[pwabuilder.com](https://www.pwabuilder.com)** → paste your live URL → it scans
   your manifest/service worker automatically → click **"Package for Stores"** → **Android**
4. PWABuilder generates a `.aab` file plus a **signing key** and a **package name**
   (e.g. `com.vashasetu.app`) — save these, you'll need them again for every future update
5. PWABuilder also gives you the exact content for `assetlinks.json` — copy the real
   `package_name` and `sha256_cert_fingerprints` values into
   `.well-known/assetlinks.json` in this project (replacing the placeholders), then
   re-upload just that one file to your live site at exactly
   `https://yourdomain.com/.well-known/assetlinks.json`
6. Create a **Google Play Developer account** ([play.google.com/console](https://play.google.com/console)) — one-time $25 fee
7. In Play Console: **Create app** → fill in the store listing (title, description,
   screenshots, feature graphic — ask for help generating these) → upload the `.aab` from
   step 4 → link your `privacy-policy.html` URL → submit for review
8. Google typically reviews within 1–7 days

## Updating the app (important)
`sw.js` always fetches the newest page and only uses its cache when you are offline. After copying new
files, if you still see the old page once, press **Ctrl+Shift+R**. Replace the **whole folder**.

## Publishing to the Google Play Store
Vashasetu is a PWA, so it can't be submitted as-is — it needs to be wrapped as a **TWA
(Trusted Web Activity)**, Google's official way to publish web apps to Play. This requires
your site to be **live at a real HTTPS URL first** (XAMPP on localhost is not enough for Play).

1. **Host the app** on any PHP + MySQL host over HTTPS (edit `api/config.php` for that host) and confirm it works
2. **Edit `privacy-policy.html`** — replace `REPLACE_WITH_YOUR_EMAIL` with a real contact
   email, then re-upload that one file
3. Go to **[pwabuilder.com](https://www.pwabuilder.com)** → paste your live URL → it scans
   your manifest/service worker automatically → click **"Package for Stores"** → **Android**
4. PWABuilder generates a `.aab` file plus a **signing key** and a **package name**
   (e.g. `com.vashasetu.app`) — save these, you'll need them again for every future update
5. PWABuilder also gives you the exact content for `assetlinks.json` — copy the real
   `package_name` and `sha256_cert_fingerprints` values into
   `.well-known/assetlinks.json` in this project (replacing the placeholders), then
   re-upload just that one file to your live site at exactly
   `https://yourdomain.com/.well-known/assetlinks.json`
6. Create a **Google Play Developer account** ([play.google.com/console](https://play.google.com/console)) — one-time $25 fee
7. In Play Console: **Create app** → fill in the store listing (title, description,
   screenshots, feature graphic — ask for help generating these) → upload the `.aab` from
   step 4 → link your `privacy-policy.html` URL → submit for review
8. Google typically reviews within 1–7 days

## If something doesn't work
* **"Database not ready" / blank dictionary** → MySQL is not started in XAMPP, or setup.php was not run. Start
  MySQL, then open http://localhost/vashasetu/setup.php.
* **Bangla shows as ???** → run setup.php again with `?reset=1`; do not import the CSV files by hand.
* **Nothing happens on translate** → open the browser console (F12). The folder must be named exactly
  `vashasetu` inside `htdocs`, so that `api/` is reachable at http://localhost/vashasetu/api/translate.php.
* **Port 80 busy** → change Apache's port in XAMPP and open http://localhost:PORT/vashasetu/.

## Adding corrections without touching the database
Open `database/curated.csv` (columns `dialect,dialect_word,standard_word`) and add a line such as
`Chattogram,হান,পান`. Save it — it takes effect immediately in both directions (dialect → Standard and
Standard → dialect), with no `setup.php` reload. Dialect names: Chattogram, Sylheti, Noakhali, Barishal, Mymensingh.

## Rajshahi (added)
Rajshahi is the sixth dialect. Its only parallel data is BD-Dialect (950 words + 950 clauses), so it is much
thinner than the other five: expect it to know everyday words and phrases, not long sentences. Auto-detect
uses a small penalty for Rajshahi so plain Standard Bangla is not mistaken for it. After updating, open
`setup.php?reset=1` once to reload the dictionary.
