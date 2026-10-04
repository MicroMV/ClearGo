-- Registrar is the final clearance office. Its only requirements are a complete
-- document set and approval from every other active office.

UPDATE requirements requirement_record
INNER JOIN stations station
    ON station.id = requirement_record.station_id
   AND station.code = 'REGISTRAR'
SET requirement_record.requirement_code = 'REGISTRAR_COMPLETE_DOCUMENTS',
    requirement_record.name = 'Complete Documents',
    requirement_record.type = 'DOCUMENT',
    requirement_record.description = 'Present all required documents to the Registrar for final review.',
    requirement_record.is_required = 1,
    requirement_record.status = 'ACTIVE'
WHERE requirement_record.name = 'Required Documents'
  AND requirement_record.requirement_code <> 'REGISTRAR_COMPLETE_DOCUMENTS';

INSERT INTO requirements
    (station_id, requirement_code, name, type, description, is_required, status)
SELECT station.id,
       'REGISTRAR_ALL_OFFICES_SIGNED',
       'Signed by All Offices',
       'APPROVAL',
       'Every active clearance office must approve before the Registrar can complete clearance.',
       1,
       'ACTIVE'
FROM stations station
WHERE station.code = 'REGISTRAR'
  AND NOT EXISTS (
      SELECT 1
      FROM requirements existing_requirement
      WHERE existing_requirement.requirement_code = 'REGISTRAR_ALL_OFFICES_SIGNED'
  );

INSERT IGNORE INTO requirement_records (clearance_station_id, requirement_id, status)
SELECT clearance_station.id, requirement_record.id, 'NOT_STARTED'
FROM clearance_stations clearance_station
INNER JOIN stations station
    ON station.id = clearance_station.station_id
   AND station.code = 'REGISTRAR'
INNER JOIN requirements requirement_record
    ON requirement_record.station_id = station.id
   AND requirement_record.requirement_code = 'REGISTRAR_ALL_OFFICES_SIGNED';
