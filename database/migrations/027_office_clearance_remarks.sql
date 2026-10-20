-- Persistent student-facing office comments, independent of approval and return reasons.
ALTER TABLE clearance_stations ADD COLUMN office_remarks VARCHAR(1000) NULL AFTER returned_reason;
