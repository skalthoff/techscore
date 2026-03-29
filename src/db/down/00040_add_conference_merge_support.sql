DROP TABLE IF EXISTS conference_alias;
DROP TABLE IF EXISTS school_conference_history;

ALTER TABLE conference
  DROP FOREIGN KEY fk_conference_merged_into,
  DROP KEY fk_conference_merged_into,
  DROP COLUMN merge_note,
  DROP COLUMN merged_on,
  DROP COLUMN merged_into,
  DROP COLUMN inactive_on;
