-- Phase 1: model the six primary clearance stages and the SASO sub-checks.
-- Apply once to the lewis_clearance database.

ALTER TABLE stations
    ADD COLUMN parent_station_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_stations_parent_status_order (parent_station_id, status, sort_order),
    ADD CONSTRAINT fk_stations_parent
        FOREIGN KEY (parent_station_id) REFERENCES stations (id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE stations SET parent_station_id = NULL, sort_order = 1 WHERE code = 'DEAN';
UPDATE stations SET parent_station_id = NULL, sort_order = 2 WHERE code = 'ORGANIZATION';
UPDATE stations SET parent_station_id = NULL, sort_order = 3 WHERE code = 'SASO';
UPDATE stations SET parent_station_id = NULL, sort_order = 4 WHERE code = 'FACILITIES';
UPDATE stations SET parent_station_id = NULL, sort_order = 5 WHERE code = 'ACCOUNTING';
UPDATE stations SET parent_station_id = NULL, sort_order = 6 WHERE code = 'REGISTRAR';

UPDATE stations AS child
INNER JOIN stations AS parent ON parent.code = 'SASO'
SET child.parent_station_id = parent.id,
    child.sort_order = CASE child.code
        WHEN 'STUDENT_COUNCIL' THEN 1
        WHEN 'LIBRARY' THEN 2
        WHEN 'BOOKSTORE' THEN 3
        WHEN 'CLINIC' THEN 4
    END
WHERE child.code IN ('STUDENT_COUNCIL', 'LIBRARY', 'BOOKSTORE', 'CLINIC');

