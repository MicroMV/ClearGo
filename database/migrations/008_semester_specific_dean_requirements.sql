-- Fourth-year CCS students complete Faculty Evaluation in First Semester.
-- Their separate Application for Graduation requirement applies in Second Semester only.

ALTER TABLE requirements
    ADD COLUMN applicable_semester ENUM('1ST','2ND','SUMMER') NULL AFTER maximum_year_level,
    ADD KEY idx_requirements_semester (applicable_semester, status);

UPDATE requirements
SET applicable_semester = '2ND'
WHERE requirement_code = 'CCS_GRADUATION_APPLICATION';

INSERT INTO requirements
    (station_id, requirement_code, name, type, description,
     applicable_organization_id, minimum_year_level, maximum_year_level,
     applicable_semester, is_required, status)
SELECT station_id, 'CCS_FOURTH_YEAR_FACULTY_EVALUATION', name, type, description,
       applicable_organization_id, 4, 4, '1ST', is_required, status
FROM requirements
WHERE requirement_code = 'CCS_FACULTY_EVALUATION';

-- Reconcile only the active preparation cycle. Archived semester records remain unchanged.
DELETE record
FROM requirement_records record
INNER JOIN clearance_stations clearance_station ON clearance_station.id = record.clearance_station_id
INNER JOIN clearances clearance ON clearance.id = clearance_station.clearance_id
INNER JOIN academic_periods period ON period.id = clearance.academic_period_id
INNER JOIN requirements requirement ON requirement.id = record.requirement_id
WHERE period.status = 'ACTIVE'
  AND period.clearance_status = 'PREPARING'
  AND requirement.applicable_semester IS NOT NULL
  AND requirement.applicable_semester <> period.semester;

INSERT IGNORE INTO requirement_records (clearance_station_id, requirement_id, status)
SELECT clearance_station.id, requirement.id, 'NOT_STARTED'
FROM clearances clearance
INNER JOIN academic_periods period ON period.id = clearance.academic_period_id
INNER JOIN students student ON student.id = clearance.student_id
INNER JOIN clearance_stations clearance_station ON clearance_station.clearance_id = clearance.id
INNER JOIN requirements requirement ON requirement.station_id = clearance_station.station_id
WHERE period.status = 'ACTIVE'
  AND period.clearance_status = 'PREPARING'
  AND requirement.status = 'ACTIVE'
  AND requirement.is_required = 1
  AND (requirement.applicable_organization_id IS NULL
       OR requirement.applicable_organization_id = student.organization_id)
  AND (requirement.minimum_year_level IS NULL
       OR student.year_level >= requirement.minimum_year_level)
  AND (requirement.maximum_year_level IS NULL
       OR student.year_level <= requirement.maximum_year_level)
  AND (requirement.applicable_semester IS NULL
       OR requirement.applicable_semester = period.semester);
