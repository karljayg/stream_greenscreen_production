-- ============================================================
-- Mathison Insights - derived tables
--
-- These tables are DERIVED data. They are fully rebuilt by
-- relabel.php and can be dropped + recreated at any time
-- without losing source data (Replays / PatternLearning are
-- never written to).
--
-- Applied automatically by relabel.php when the tables are
-- missing. Kept here so schema changes stay reviewable.
-- ============================================================

-- Atomic tag matches. Building blocks only — NOT the primary scouting unit.
-- Example: comment "3 hatch ling bane all in" yields three rows here
-- (3_hatch, ling_bane, all_in). Useful for secondary filters like
-- "every game that involved an all-in somewhere".
CREATE TABLE IF NOT EXISTS insight_tags (
    id INT NOT NULL AUTO_INCREMENT,
    run_id INT NOT NULL,
    source ENUM('replay','pattern') NOT NULL,
    source_id VARCHAR(50) NOT NULL,
    replay_id INT NULL,
    opponent_name VARCHAR(255) NULL,
    opponent_race VARCHAR(20) NULL,
    self_result VARCHAR(20) NULL,
    date_played DATETIME NULL,
    comment TEXT NULL,
    tag VARCHAR(50) NOT NULL,                -- e.g. 'ling_bane'
    category VARCHAR(30) NOT NULL,           -- opening | composition | intent | economy
    phase ENUM('early','late','any') NOT NULL DEFAULT 'any',
    start_workers TINYINT NULL,              -- 6 | 8 | 12 (SC2 starting worker era)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tag (tag),
    KEY idx_opponent (opponent_name),
    KEY idx_source (source, source_id),
    KEY idx_replay (replay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compound tactics. PRIMARY scouting unit.
-- Example: comment "3 hatch ling bane all in" yields ONE row:
--   tactic_key   = '3_hatch|ling_bane|all_in'
--   tactic_label = '3 hatch · ling bane · all in'
--   parts_count  = 3
-- "X to Y" comments produce one tactic per phase (early / late).
CREATE TABLE IF NOT EXISTS insight_tactics (
    id INT NOT NULL AUTO_INCREMENT,
    run_id INT NOT NULL,
    source ENUM('replay','pattern') NOT NULL,
    source_id VARCHAR(50) NOT NULL,
    replay_id INT NULL,
    opponent_name VARCHAR(255) NULL,
    opponent_race VARCHAR(20) NULL,
    self_result VARCHAR(20) NULL,
    date_played DATETIME NULL,
    comment TEXT NULL,
    phase ENUM('early','late','any') NOT NULL DEFAULT 'any',
    tactic_key VARCHAR(255) NOT NULL,        -- stable join key: tag|tag|tag
    tactic_label VARCHAR(255) NOT NULL,      -- human chain: '3 hatch · ling bane · all in'
    parts_json JSON NOT NULL,                -- ordered [{tag,label,category}, ...]
    parts_count TINYINT NOT NULL,            -- length of the chain (2+)
    start_workers TINYINT NULL,              -- 6 | 8 | 12 (SC2 starting worker era)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tactic_key (tactic_key),
    KEY idx_parts_count (parts_count),
    KEY idx_opponent (opponent_name),
    KEY idx_source (source, source_id),
    KEY idx_replay (replay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per relabeler execution. The watermark columns are
-- compared against live counts to decide when a rerun is needed
-- (see api/insights.php?action=status).
CREATE TABLE IF NOT EXISTS insight_runs (
    run_id INT NOT NULL AUTO_INCREMENT,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,               -- NULL = crashed / still running
    replays_scanned INT NOT NULL DEFAULT 0,  -- replays with a non-empty comment
    patterns_scanned INT NOT NULL DEFAULT 0, -- PatternLearning rows with a usable comment
    tags_written INT NOT NULL DEFAULT 0,
    tactics_written INT NOT NULL DEFAULT 0,
    watermark_replay_comment_count INT NOT NULL DEFAULT 0, -- COUNT of commented replays at run time
    watermark_max_replay_id INT NOT NULL DEFAULT 0,        -- MAX(ReplayId) with comment at run time
    watermark_pattern_count INT NOT NULL DEFAULT 0,        -- COUNT(*) of PatternLearning at run time
    watermark_pattern_updated_at DATETIME NULL,            -- MAX(updated_at) of PatternLearning at run time
    notes VARCHAR(255) NULL,
    PRIMARY KEY (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
