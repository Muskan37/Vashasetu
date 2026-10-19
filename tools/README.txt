Scripts used to build the dictionary files in ../database (optional; the app does not need them).
build.py  - IBM Model 1 word alignment + held-out evaluation (python3 build.py)
gen.py    - writes corpus.csv, lexicon.csv, vocab.csv   gen2.py - writes bn_words.csv, en_bn.csv
det.py    - dialect-detection accuracy experiment
They expect the Unified-Bangla dataset and the BengaliDictionary repository cloned under /tmp.

Extra data: 7,645 Bangla-Chittagonian sentence pairs from github.com/sihabsafin/bangla-chittagonian-translation
(Safin & Ara, MIT licence) are merged into corpus.csv (source "Safin-2025") by gen.py.

All extra datasets are read by tools/extra_sources.py (Mendeley zips unpacked under /tmp/claude-0/work/mend; set MEND=... to change). See ../DATA_SOURCES.txt.
