-- MeetingMinutesSystem: database schema
-- Run the ALTER below once if the meetings table exists but is missing document_text.

-- =============================================================================
-- Add document_text column (for paste-from-editor support)
-- Run this once. If you get "Duplicate column", the column already exists.
-- =============================================================================
ALTER TABLE meetings
    ADD COLUMN document_text MEDIUMTEXT NULL
    COMMENT 'Pasted document content when document_type is paste'
    AFTER document_url;
