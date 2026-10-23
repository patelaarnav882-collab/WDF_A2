-- =====================================================
-- STUDENTHUB - PRACTICAL 12 MIGRATION
-- Adds event_time, venue, poster, status and updated_at
-- to the existing events table. Existing rows are kept;
-- new columns are NULL/'upcoming' by default.
--
-- Safe to run ONCE. If columns already exist, the
-- script will fail harmlessly (check DESCRIBE first).
-- =====================================================

ALTER TABLE events
    ADD COLUMN event_time TIME NULL AFTER event_date,
    ADD COLUMN venue VARCHAR(150) NULL AFTER location,
    ADD COLUMN poster VARCHAR(255) NULL AFTER venue,
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'upcoming' AFTER poster,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL
        ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Optional: index for search/filter performance.
CREATE INDEX idx_events_status ON events(status);
CREATE INDEX idx_events_category_date ON events(category, event_date);
