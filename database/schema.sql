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
