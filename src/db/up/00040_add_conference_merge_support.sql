ALTER TABLE conference
  ADD COLUMN inactive_on DATETIME NULL AFTER url,
  ADD COLUMN merged_into VARCHAR(8) NULL AFTER inactive_on,
  ADD COLUMN merged_on DATETIME NULL AFTER merged_into,
  ADD COLUMN merge_note TEXT NULL AFTER merged_on,
  ADD KEY fk_conference_merged_into (merged_into),
  ADD CONSTRAINT fk_conference_merged_into FOREIGN KEY (merged_into) REFERENCES conference(id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE school_conference_history (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  school VARCHAR(10) NOT NULL,
  conference VARCHAR(8) NOT NULL,
  start_season MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  end_season MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  source ENUM('bootstrap', 'merge', 'manual') NOT NULL DEFAULT 'bootstrap',
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sch_school (school),
  KEY idx_sch_conference (conference),
  KEY idx_sch_start_season (start_season),
  KEY idx_sch_end_season (end_season),
  KEY idx_sch_school_window (school, start_season, end_season),
  CONSTRAINT fk_school_conference_history_school FOREIGN KEY (school) REFERENCES school(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_school_conference_history_conference FOREIGN KEY (conference) REFERENCES conference(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_school_conference_history_start_season FOREIGN KEY (start_season) REFERENCES season(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_school_conference_history_end_season FOREIGN KEY (end_season) REFERENCES season(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE conference_alias (
  alias_id VARCHAR(16) NOT NULL,
  conference VARCHAR(8) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_updated_on TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (alias_id),
  KEY idx_conference_alias_conference (conference),
  CONSTRAINT fk_conference_alias_conference FOREIGN KEY (conference) REFERENCES conference(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO school_conference_history (school, conference, start_season, end_season, source)
SELECT school.id, school.conference, NULL, NULL, 'bootstrap'
FROM school;