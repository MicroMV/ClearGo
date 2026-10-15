ALTER TABLE notifications
    ADD COLUMN academic_period_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD KEY idx_notifications_user_period_created (user_id, academic_period_id, created_at, id),
    ADD KEY idx_notifications_user_period_unread (user_id, academic_period_id, is_read),
    ADD KEY fk_notifications_period (academic_period_id),
    ADD CONSTRAINT fk_notifications_period FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Preserve existing alerts using the recorded creation boundaries of activated semesters.
-- Alerts predating all known semesters remain unassigned rather than being guessed.
UPDATE notifications notification
SET notification.academic_period_id = (
    SELECT period.id FROM academic_periods period
    WHERE period.status IN ('ACTIVE','CLOSED') AND period.created_at <= notification.created_at
    ORDER BY period.created_at DESC, period.id DESC LIMIT 1
)
WHERE notification.academic_period_id IS NULL;
