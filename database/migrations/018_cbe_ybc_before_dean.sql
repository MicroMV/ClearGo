-- Restore YBC as the required first step for non-graduating CBE students.
UPDATE requirements
SET status='ACTIVE', is_required=1, minimum_year_level=1, maximum_year_level=3
WHERE requirement_code='CBE_YBC_APPROVAL';

INSERT INTO requirement_records (clearance_station_id, requirement_id, status)
SELECT dean_station.id, requirement.id, 'NOT_STARTED'
FROM clearance_stations dean_station
INNER JOIN stations station ON station.id=dean_station.station_id AND station.code='DEAN'
INNER JOIN clearances clearance ON clearance.id=dean_station.clearance_id
INNER JOIN students student ON student.id=clearance.student_id AND student.year_level BETWEEN 1 AND 3
INNER JOIN organizations organization ON organization.id=student.organization_id AND organization.code='CBE'
INNER JOIN requirements requirement ON requirement.station_id=station.id AND requirement.requirement_code='CBE_YBC_APPROVAL' AND requirement.status='ACTIVE'
LEFT JOIN requirement_records record ON record.clearance_station_id=dean_station.id AND record.requirement_id=requirement.id
WHERE record.id IS NULL;
