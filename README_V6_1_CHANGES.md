# VashaSetu V6.1 — free-text translation restored, unreliable word list demoted

Built on V6 (64 districts, in-app Learn, isolated per-language datasets). Tested here against a real MySQL server
(setup.php runs clean, all tables load).

## What was wrong in V6
- With default settings, **almost any sentence returned an empty translation**: V6 only translated sentences found
  word-for-word in a reviewed list (~6,800 exact pairs). Typing a normal new sentence gave "No unambiguous eligible sentence match".
- The BD-Dialect **Words** file (2,295 rows) was labelled "publication reviewed", but it pairs words by *meaning*
  (synonyms, formal terms, some misaligned rows such as Sylheti কোটি → বাড়ি), so it fed wrong words into the dictionary,
  Word of the day and the quiz.
- Mymensingh future verbs were stored as করমু / যামু; native Mymensingh speech uses -বাম (করবাম, যাইবাম).

## What changed
1. **Approximate word-by-word translation is ON by default** (the checkbox stays, now checked). It works for the six Bangla
   regional varieties and Standard Bangla. It uses **only the chosen dialect's** learned dictionary (no pooling across
   dialects), chooses the word sense from the neighbouring words, labels the result "approximate / unverified" and lists
   the words it is least sure about. Exact reviewed sentences are still used first, and every word's own meaning is
   shown separately in the breakdown. Indigenous languages are still never approximated (no lexicon exists for them).
2. **Auto-detect no longer stops on plain Standard Bangla**: if nothing in any dialect matches and the classifier clearly
   prefers Standard, it is treated as Standard.
3. **BD-Dialect Words demoted** to "needs native review" (kept, visible only with the candidates toggle) and removed from the
   lexicon used for translation.
4. **Mymensingh -বাম forms** added as `native_speaker_reported` (17 verbs); the old -মু entries were removed. Add more
   verified pairs to `database/curated.csv` (no reload needed) and to `database/languages/<language>/words.csv`.
5. **Word of the day / quiz** fall back to `database/showcase.csv` (a strict learned list) for varieties with too few
   reviewed words (Sylheti, Rajshahi). Block wrong ones in `database/showcase_block.csv`.
6. Service worker cache `vashasetu-shell-v20`.

## Upgrade steps (XAMPP)
Back up first. Copy these files over your V6 folder, then open `setup.php?refresh_words=1` once (reloads only the word table)
or run `setup.php?reset=1` if you want everything reloaded (your users and progress tables are not touched by either).
Hard-refresh with Ctrl+F5.

## Still true
Nothing here is native-speaker verified except the 42 curated pairs and the 17 Mymensingh forms. The machine-learned
dictionary will still contain mistakes (e.g. Sylheti "খাব" may come out as "খাইতাম"); they are flagged as less sure.


## V6.2: full MELD merged
Chakma 797, Marma 290, Garo 312 sentence pairs (MELD, Bengali transliteration) loaded into database/languages/<lang>/sentences.csv and indigenous_pairs.csv. All marked research_corpus_not_reaudited (needs community review). Ambiguous source phrases were dropped. No word-level pairs were invented. Run setup.php?reset=1 (or --reset) once. Check the Mendeley licence before publishing.
