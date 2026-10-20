-- Repair only untouched, current CBE clearances created with the old Dean-first initializer.
-- Preserve completed/archived clearances and any application with office decisions.
UPDATE clearance_stations organization_record
INNER JOIN stations organization_station
    ON organization_station.id=organization_record.station_id AND organization_station.code='ORGANIZATION'
INNER JOIN clearances clearance ON clearance.id=organization_record.clearance_id
INNER JOIN academic_periods period ON period.id=clearance.academic_period_id
INNER JOIN semester_enrollments enrollment
    ON enrollment.student_id=clearance.student_id AND enrollment.academic_period_id=period.id
INNER JOIN students student ON student.id=clearance.student_id
INNER JOIN organizations organization ON organization.id=student.organization_id AND organization.code='CBE'
INNER JOIN clearance_stations dean_record ON dean_record.clearance_id=clearance.id
INNER JOIN stations dean_station ON dean_station.id=dean_record.station_id AND dean_station.code='DEAN'
LEFT JOIN clearance_stations decided_record
    ON decided_record.clearance_id=clearance.id
   AND (decided_record.status NOT IN ('LOCKED','PENDING')
        OR decided_record.approved_by IS NOT NULL OR decided_record.approved_at IS NOT NULL
        OR decided_record.returned_by IS NOT NULL OR decided_record.returned_at IS NOT NULL
        OR decided_record.returned_reason IS NOT NULL)
SET organization_record.status='PENDING', dean_record.status='LOCKED'
WHERE organization_record.status='LOCKED' AND dean_record.status='PENDING'
  AND clearance.status IN ('NOT_STARTED','IN_PROGRESS') AND clearance.completed_at IS NULL
  AND period.status='ACTIVE' AND period.clearance_status IN ('PREPARING','OPEN')
  AND enrollment.status='ENROLLED' AND student.status='ACTIVE'
  AND decided_record.id IS NULL;
