-- MeetingMinutesSystem: database schema
-- Use CREATE TABLE for new production DB; use ALTER TABLE to add new columns to existing table.

-- =============================================================================
-- Option A: Create table from scratch (e.g. new production DB)
-- =============================================================================

CREATE TABLE IF NOT EXISTS meetings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    chair_first_name VARCHAR(255) NOT NULL,
    chair_last_name VARCHAR(255) NOT NULL,
    chair_email VARCHAR(255) NOT NULL,
    campus_name VARCHAR(255) NOT NULL,
    ministry VARCHAR(255) NOT NULL,
    pastor_in_charge VARCHAR(255) NOT NULL,
    attendees TEXT NULL,
    meeting_type VARCHAR(20) NOT NULL DEFAULT 'in_person',
    description TEXT NOT NULL,
    document_type VARCHAR(20) NOT NULL,
    file_path VARCHAR(512) NULL,
    document_url VARCHAR(1024) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    meeting_date_time DATETIME NULL COMMENT 'Meeting date and time (for future use)',
    comments VARCHAR(1000) NULL COMMENT 'Comments (for future use)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- Option B: Alter existing table to add new columns (future use)
-- =============================================================================

ALTER TABLE meetings
    ADD COLUMN meeting_date_time DATETIME NULL COMMENT 'Meeting date and time (for future use)' AFTER updated_at,
    ADD COLUMN comments VARCHAR(1000) NULL COMMENT 'Comments (for future use)' AFTER meeting_date_time;
