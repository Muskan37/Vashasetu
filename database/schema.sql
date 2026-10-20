-- Vashasetu database schema (MySQL / MariaDB, utf8mb4)
-- You normally do NOT import this by hand: open  http://localhost/vashasetu/setup.php
-- which creates the database, these tables, and loads the dictionary.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  preferred_dialect VARCHAR(50) DEFAULT 'Chattogram',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translation_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  session_id VARCHAR(100),
  original_text VARCHAR(500) NOT NULL,
  translated_text VARCHAR(500) NOT NULL,
  dialect_type VARCHAR(80),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session (session_id),
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Words suggested by users. An admin sets status='approved' (phpMyAdmin) and the
-- translator starts using them immediately, ahead of the learned dictionary.
CREATE TABLE IF NOT EXISTS contributions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  dialect_word VARCHAR(255) NOT NULL,
  meaning VARCHAR(255) NOT NULL,
  dialect_type VARCHAR(50) NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  submitted_by VARCHAR(100) DEFAULT 'anonymous',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Learned dialect <-> Standard word pairs (from the Vashantor parallel corpus)
CREATE TABLE IF NOT EXISTS lexicon (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dialect VARCHAR(30) NOT NULL,
  dialect_word VARCHAR(120) NOT NULL,
  standard_word VARCHAR(120) NOT NULL,
  p_std FLOAT NOT NULL DEFAULT 0,
  p_dia FLOAT NOT NULL DEFAULT 0,
  cnt INT NOT NULL DEFAULT 0,
  verified TINYINT NOT NULL DEFAULT 0,
  INDEX idx_d2s (dialect, dialect_word),
  INDEX idx_s2d (dialect, standard_word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Whole aligned sentences (translation memory)
CREATE TABLE IF NOT EXISTS sentences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  variety VARCHAR(30) NOT NULL,
  text VARCHAR(300) NOT NULL,
  standard_text VARCHAR(300) NOT NULL,
  INDEX idx_text (variety, text(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Word frequency per variety (used to guess which dialect a text is)
CREATE TABLE IF NOT EXISTS vocab_freq (
  variety VARCHAR(30) NOT NULL,
  word VARCHAR(120) NOT NULL,
  cnt INT NOT NULL,
  PRIMARY KEY (variety, word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Big Standard Bangla word list with phonetic key (used to understand Banglish typing)
CREATE TABLE IF NOT EXISTS bn_words (
  word VARCHAR(100) NOT NULL PRIMARY KEY,
  fkey VARCHAR(40) NOT NULL,
  freq INT NOT NULL DEFAULT 0,
  INDEX idx_fkey (fkey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- English -> Bangla dictionary (word lookup / English input)
CREATE TABLE IF NOT EXISTS en_bn (
  english VARCHAR(100) NOT NULL PRIMARY KEY,
  bangla VARCHAR(255) NOT NULL,
  INDEX idx_bn (bangla(60))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Which Bangla words follow which (from the aligned sentences); used to choose between same-sounding Banglish spellings
CREATE TABLE IF NOT EXISTS bigrams (
  w1 VARCHAR(100) NOT NULL,
  w2 VARCHAR(100) NOT NULL,
  cnt INT NOT NULL,
  PRIMARY KEY (w1, w2),
  INDEX idx_w2 (w2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- VashaSetu Learning v2.0: language != administrative district; scripts and status are explicit.
CREATE TABLE IF NOT EXISTS language_catalog (
  language_id VARCHAR(60) PRIMARY KEY,
  display_name VARCHAR(140) NOT NULL,
  language_type VARCHAR(50) NOT NULL,
  existing_app_key VARCHAR(50) NOT NULL DEFAULT '',
  scripts VARCHAR(180) NOT NULL DEFAULT '',
  app_status VARCHAR(70) NOT NULL DEFAULT 'data_needed',
  geography_nonexclusive VARCHAR(255) DEFAULT '',
  notes TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publication-reviewed candidates, never automatically certified as individually correct.
-- Exact phrase matches only; avoid pooling dialects or training-split leakage.
CREATE TABLE IF NOT EXISTS regional_exact_pairs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  variety VARCHAR(50) NOT NULL,
  source_text VARCHAR(300) NOT NULL,
  standard_bangla VARCHAR(300) NOT NULL,
  source_dataset VARCHAR(100) NOT NULL,
  review_status VARCHAR(90) NOT NULL,
  INDEX idx_regional_phrase (variety,source_text(140))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Indigenous languages are separate from regional Bangla. Script is preserved.
CREATE TABLE IF NOT EXISTS indigenous_pairs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  language_id VARCHAR(60) NOT NULL,
  source_text TEXT NOT NULL,
  standard_bangla TEXT NOT NULL,
  english TEXT NULL,
  script VARCHAR(100),
  source_dataset VARCHAR(140) NOT NULL,
  review_status VARCHAR(100) NOT NULL DEFAULT 'pending',
  source_url VARCHAR(500),
  INDEX idx_indigenous_language (language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS learning_units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  unit_key VARCHAR(60) NOT NULL UNIQUE,
  sort_order INT NOT NULL UNIQUE,
  stage VARCHAR(30) NOT NULL,
  title_en VARCHAR(160) NOT NULL,
  title_bn VARCHAR(160) NOT NULL,
  description_en VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS learning_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  unit_key VARCHAR(60) NOT NULL,
  item_order INT NOT NULL,
  exercise_type VARCHAR(40) NOT NULL,
  prompt VARCHAR(255) NOT NULL,
  display_bn VARCHAR(300) NOT NULL,
  ipa VARCHAR(100),
  romanization VARCHAR(200),
  options_json TEXT NOT NULL,
  correct_answer VARCHAR(300) NOT NULL,
  gloss_en VARCHAR(255),
  speech_bn VARCHAR(300) NOT NULL,
  review_status VARCHAR(100) NOT NULL,
  UNIQUE KEY unq_learning_item (unit_key, item_order),
  INDEX idx_learning_unit (unit_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answers for logged-in users; guests may use browser-only progress.
CREATE TABLE IF NOT EXISTS learning_progress (
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  correct_count INT NOT NULL DEFAULT 0,
  last_correct TINYINT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id,item_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES learning_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Future community-approved first-language instructions, never machine-guessed.
-- A blank table is intentional until bilingual educators provide verified localizations.
CREATE TABLE IF NOT EXISTS learning_localizations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  language_id VARCHAR(60) NOT NULL,
  unit_key VARCHAR(60) NOT NULL,
  item_order INT NOT NULL,
  prompt_native TEXT NOT NULL,
  hint_native TEXT,
  script VARCHAR(100) NOT NULL,
  audio_path VARCHAR(255) DEFAULT NULL,
  review_status VARCHAR(80) NOT NULL DEFAULT 'pending',
  reviewer VARCHAR(120) DEFAULT NULL,
  UNIQUE KEY unq_locale_item (language_id,unit_key,item_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- V3 parallel sentence evidence. Every row has language, exact phrase, status, origin,
-- train/test split. Unverified training candidates are NOT silently enabled for live translation.
CREATE TABLE IF NOT EXISTS parallel_sentences (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  pair_hash CHAR(64) NOT NULL UNIQUE,
  language VARCHAR(60) NOT NULL,
  source_text VARCHAR(300) NOT NULL,
  source_key VARCHAR(300) NOT NULL,
  standard_bangla VARCHAR(300) NOT NULL,
  standard_key VARCHAR(300) NOT NULL,
  source_dataset VARCHAR(200) NOT NULL,
  review_status VARCHAR(80) NOT NULL,
  eligible_exact TINYINT NOT NULL DEFAULT 0,
  split_name VARCHAR(12) NOT NULL DEFAULT 'train',
  script VARCHAR(80) NOT NULL DEFAULT 'Bengali',
  source_count INT NOT NULL DEFAULT 1,
  INDEX ix_sentence_lookup (language, source_key(130), eligible_exact),
  INDEX ix_sentence_reverse (language, standard_key(130), eligible_exact),
  INDEX ix_sentence_review (review_status, split_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- V3 one-token meaning evidence. ONLY from single-token parallel items or the app's
-- own tiny curated list. A sentence's word order is NEVER treated as an alignment.
CREATE TABLE IF NOT EXISTS word_meanings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  pair_hash CHAR(64) NOT NULL UNIQUE,
  language VARCHAR(60) NOT NULL,
  source_word VARCHAR(120) NOT NULL,
  standard_word VARCHAR(120) NOT NULL,
  meaning_status VARCHAR(80) NOT NULL,
  source_dataset VARCHAR(200) NOT NULL,
  evidence_count INT NOT NULL DEFAULT 1,
  INDEX ix_word_lookup (language, source_word),
  INDEX ix_word_reverse (language, standard_word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- V4: isolated progress for a chosen teaching language -> target language.
-- Do not reuse learning_progress, which is global to Standard Bangla items.
CREATE TABLE IF NOT EXISTS course_progress (
  user_id INT NOT NULL,
  source_language VARCHAR(60) NOT NULL,
  target_language VARCHAR(60) NOT NULL,
  item_kind VARCHAR(12) NOT NULL,
  item_id INT NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  correct_count INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id,source_language,target_language,item_kind,item_id),
  INDEX ix_course_progress_pair (user_id,source_language,target_language),
  CONSTRAINT fk_course_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Native-speaker/educator approved, pair-specific course questions.
-- Blank on first install: research candidate corpora are NOT pedagogically verified.
CREATE TABLE IF NOT EXISTS pair_course_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_language VARCHAR(60) NOT NULL,
  target_language VARCHAR(60) NOT NULL,
  unit_key VARCHAR(60) NOT NULL,
  stage VARCHAR(30) NOT NULL,
  item_order INT NOT NULL,
  exercise_type VARCHAR(50) NOT NULL,
  prompt_native TEXT NOT NULL,
  target_text VARCHAR(300) NOT NULL,
  ipa VARCHAR(120) DEFAULT NULL,
  romanization VARCHAR(220) DEFAULT NULL,
  options_json TEXT NOT NULL,
  correct_answer VARCHAR(300) NOT NULL,
  gloss_native TEXT DEFAULT NULL,
  audio_path VARCHAR(255) DEFAULT NULL,
  source_dataset VARCHAR(180) NOT NULL,
  reviewer VARCHAR(150) NOT NULL,
  review_status VARCHAR(40) NOT NULL DEFAULT 'draft',
  UNIQUE KEY uq_pair_course_item (source_language,target_language,unit_key,item_order),
  INDEX ix_pair_course_items (source_language,target_language,unit_key,review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
