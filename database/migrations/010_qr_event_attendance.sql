-- Semester-scoped, authenticated QR attendance for department organizations.

ALTER TABLE events
    ADD COLUMN academic_period_id BIGINT UNSIGNED NULL AFTER organization_id,
    ADD COLUMN check_in_token_hash CHAR(64) NULL AFTER status,
    ADD COLUMN check_in_opens_at DATETIME NULL AFTER check_in_token_hash,
    ADD COLUMN check_in_closes_at DATETIME NULL AFTER check_in_opens_at,
    ADD KEY idx_events_period_org_status (academic_period_id, organization_id, status),
    ADD UNIQUE KEY uq_events_check_in_token (check_in_token_hash),
    ADD CONSTRAINT fk_events_academic_period
        FOREIGN KEY (academic_period_id) REFERENCES academic_periods (id)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Preserve any legacy events by assigning them to the currently active semester.
UPDATE events event
INNER JOIN academic_periods period ON period.status = 'ACTIVE'
SET event.academic_period_id = period.id
WHERE event.academic_period_id IS NULL;

ALTER TABLE event_attendance
    ADD COLUMN check_in_method ENUM('MANUAL','QR') NOT NULL DEFAULT 'MANUAL' AFTER status;
