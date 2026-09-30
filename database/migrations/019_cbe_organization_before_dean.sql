-- CBE clearance begins with its Organization/YBC sign-off, then proceeds to the Dean.
-- Retire the obsolete Dean-side YBC requirement; Organization approval is the
-- authoritative sign-off for this step.
UPDATE requirements
SET status='INACTIVE', is_required=0
WHERE requirement_code='CBE_YBC_APPROVAL';

-- Move only unstarted CBE applications into the Organization-first sequence.
-- Applications already acted on remain intact for auditability.
UPDATE clearance_stations organization_record
INNER JOIN stations organization_station
    ON organization_station.id=organization_record.station_id AND organization_station.code='ORGANIZATION'
INNER JOIN clearances clearance ON clearance.id=organization_record.clearance_id
INNER JOIN students student ON student.id=clearance.student_id
INNER JOIN organizations organization ON organization.id=student.organization_id AND organization.code='CBE'
INNER JOIN clearance_stations dean_record ON dean_record.clearance_id=clearance.id
INNER JOIN stations dean_station ON dean_station.id=dean_record.station_id AND dean_station.code='DEAN'
SET organization_record.status='PENDING', dean_record.status='LOCKED'
WHERE organization_record.status='LOCKED' AND dean_record.status='PENDING';
