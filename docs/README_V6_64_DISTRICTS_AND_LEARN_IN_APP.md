# VashaSetu V6 — 64-district account preference and learning inside the app

This update is based on the prior **VashaSetu V5** app. It preserves the prior dictionary, translation datasets, pronunciation/starter lessons, and login/session implementation.

## Features

- **64 administrative districts in 8 divisions**, available on the Home screen, at registration, and on the Profile screen. Administrative membership cross-checked against the [Bangladesh National Portal district list](https://bangladesh.gov.bd/views/district-list/District-List/).
- Users choose their **current district**. An optional `users.current_district` column stores it in their account; guests can also choose a district, locally in their browser.
- The app displays **approximate regional-language candidates** for a district. This is NOT an authoritative or comprehensive map of district-wide language distribution. Village, community, family, and Indigenous-language identities may differ; no language is automatically assigned based on address. All candidate groupings are marked unverified in `database/districts.json` and should be independently reviewed with regional experts before publication as facts.
- Users separately select their **spoken language** (24 catalog entries, including regional Bangla and separate Indigenous languages), saved as `users.spoken_language`.
- The preexisting **preferred map dialect**, **dictionary language**, **translator output**, **district**, and **spoken language** remain distinct settings. Changing one does not silently change another.
- **Learn** is now a bottom navigation tab inside the same app/mobile shell, not an external page. The existing course UI is presented in an embedded same-origin page without its old website header.
- Clicking **Learn** as a guest takes the user to Login/Register, then returns to learning after successful authentication. Direct access to `learn.php` also requires a session.
- The existing server-managed `course_progress` remains stored per signed-in user, source language, target language, and item. Spoken language can prefill the course source the *first* time, but never overrides a previously saved course choice.
- Standard Bangla starter lessons remain **educator-review pending**. Some other language pair courses are **research previews only**, not a complete verified curriculum. No new translations or recorded audio were invented for the 64 districts.

## XAMPP installation / non-destructive upgrade

**First resolve any MySQL/InnoDB recovery issue and export important databases. Do not replace or reset MySQL's data folder to update the app.**

1. Back up `C:\xampp\htdocs\vashasetu\` and export your existing `vashasetu` database using phpMyAdmin or mysqldump.
2. Extract the V6 ZIP and place the `vashasetu_app` **contents** in `C:\xampp\htdocs\vashasetu\` (so `index.php` is directly inside that folder).
3. Start Apache and MySQL in XAMPP.
4. Open `http://localhost/vashasetu/setup.php` **without any reset or refresh query string**. This preserves existing users and user progress and adds two optional fields (`current_district`, `spoken_language`) to existing user records. The new schema also works for fresh installs.
5. Open `http://localhost/vashasetu/index.php`. Refresh with **Ctrl+F5**. Cache changed to `vashasetu-shell-v19`.
6. Choose a district on Home or Profile. Use **Profile → Language I speak** to specify your own language.
7. Sign in or register and open the new **Learn** tab on the bottom navigation. Courses are source→target, and progress is stored in MySQL for your account.

**Do not use `setup.php?reset=1`** when upgrading; it can wipe datasets. Do not delete or overwrite important MySQL files during recovery.

## What is where?

| File | Purpose |
| --- | --- |
| `database/districts.json` | All 64 districts, divisions, Bengali names and approximate, explicitly unverified language candidates |
| `api/district_registry.php` | Validates district IDs and spoken-language IDs with local registries |
| `api/districts.php` | Read-only source of district and spoken language dropdown options |
| `database/schema.sql` | Adds `current_district` and `spoken_language` fields for new installs |
| `setup.php` | Idempotent additive migration for existing installs |
| `api/auth.php` | Register, read and update account district + spoken language |
| `index.php` / `assets/js/app.js` / `assets/css/app.css` | Home picker, profile, registration, in-app Learn navigation |
| `learn.php` / `assets/css/learn.css` | Account-gated, mobile-friendly embedded learning UI |
| `assets/js/learn.js` | Saved course choices and source-language suggestion on first use |

## Quality and test limits

- Official administrative district count/names/divisions verified against the government list. **The region→language candidate associations have not been linguistically confirmed district by district.** They are not automatic translation source selection.
- Existing regional and Indigenous translations and learning content remain subject to native-speaker/educator validation.
- PHP CLI lint, Node JS syntax check, JSON count/ID consistency and live PHP endpoint checks were performed. Local session authentication to `learn.php` was smoke-tested. A complete **live XAMPP/MySQL login and grading flow** was not possible in this environment. Take a backup and test in your installation.
