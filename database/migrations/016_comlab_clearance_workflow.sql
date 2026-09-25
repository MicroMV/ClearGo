-- Add Computer Laboratory after Facilities and before Accounting.

ALTER TABLE users
    MODIFY COLUMN role ENUM(
        'STUDENT','ORG_STAFF','DEAN','SASO_STAFF','STUDENT_COUNCIL_STAFF',
        'LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF',
        'COMLAB_STAFF','ACCOUNTING_STAFF','REGISTRAR'
    ) NOT NULL;

UPDATE stations
SET sort_order = sort_order + 1
WHERE parent_station_id IS NULL AND code IN ('ACCOUNTING', 'REGISTRAR');

INSERT INTO stations (parent_station_id,name,code,description,sort_order,status)
VALUES (NULL,'Computer Laboratory','COMLAB','Computer Laboratory incident-report clearance',5,'ACTIVE');

INSERT IGNORE INTO office_period_readiness (academic_period_id,station_id,status)
SELECT period.id,station.id,'NOT_READY'
FROM academic_periods period
INNER JOIN stations station ON station.code='COMLAB' AND station.status='ACTIVE'
WHERE period.status='ACTIVE';

INSERT IGNORE INTO clearance_stations (clearance_id,station_id,status)
SELECT clearance.id,comlab.id,
       CASE WHEN facilities_record.status='APPROVED' THEN 'PENDING' ELSE 'LOCKED' END
FROM clearances clearance
INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
INNER JOIN stations comlab ON comlab.code='COMLAB' AND comlab.status='ACTIVE'
INNER JOIN stations facilities ON facilities.code='FACILITIES'
INNER JOIN clearance_stations facilities_record ON facilities_record.clearance_id=clearance.id AND facilities_record.station_id=facilities.id;

-- Accounting and Registrar wait for the new Comlab step when they are not already cleared.
UPDATE clearance_stations record
INNER JOIN clearances clearance ON clearance.id=record.clearance_id
INNER JOIN academic_periods period ON period.id=clearance.academic_period_id AND period.status='ACTIVE'
INNER JOIN stations station ON station.id=record.station_id
SET record.status='LOCKED'
WHERE station.code IN ('ACCOUNTING','REGISTRAR') AND record.status IN ('PENDING','RETURNED');
