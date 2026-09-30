ALTER TABLE clinic_medical_schedules
    ADD COLUMN end_time TIME NULL AFTER schedule_time;

UPDATE clinic_medical_schedules
SET end_time = CASE
    WHEN schedule_time < '23:00:00' THEN ADDTIME(schedule_time, '01:00:00')
    ELSE '23:59:59'
END
WHERE end_time IS NULL;

ALTER TABLE clinic_medical_schedules
    MODIFY end_time TIME NOT NULL;
