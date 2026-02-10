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

-- =============================================================================
-- Add minutes_md column (markdown content used for AI summary)
-- Run this once. If you get "Duplicate column", the column already exists.
-- =============================================================================
ALTER TABLE meetings
    ADD COLUMN minutes_md MEDIUMTEXT NULL
    COMMENT 'Markdown content used for AI summary'
    AFTER description;

-- =============================================================================
-- Add ai_summary column (AI-generated summary from background job)
-- Run this once. If you get "Duplicate column", the column already exists.
-- =============================================================================
ALTER TABLE meetings
    ADD COLUMN ai_summary TEXT NULL
    COMMENT 'AI-generated summary'
    AFTER minutes_md;

-- =============================================================================
-- Add FULLTEXT index for search with relevance scoring
-- Run this once. Requires minutes_md and ai_summary to exist.
-- Optional: include pasted_text in the index if you want to search raw paste:
--   ADD FULLTEXT INDEX ft_search (ai_summary, minutes_md, pasted_text)
-- =============================================================================
ALTER TABLE meetings
    ADD FULLTEXT INDEX ft_search (ai_summary, minutes_md);
