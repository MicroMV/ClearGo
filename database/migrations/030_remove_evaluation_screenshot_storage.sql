-- Permanently remove the retired CCS screenshot submission/review feature.
-- User explicitly requested deletion of its stored data. Apply after migration 029.
-- Current Excel completion records, requirements, signatures and audit history remain.
DROP TABLE IF EXISTS evaluation_submission_images;
DROP TABLE IF EXISTS evaluation_submissions;
DROP TABLE IF EXISTS evaluation_verifications;

DELETE FROM notifications
WHERE title IN ('Anonymous evaluation submitted', 'Faculty Evaluation screenshots returned',
                'Evaluation proof verified', 'Evaluation proof requires action',
                'Evaluation verification unavailable')
   OR (title='Faculty Evaluation verified' AND message='Your Faculty Evaluation screenshots were verified by the CCS Dean.');

UPDATE requirements
SET description='Completion is confirmed by the CCS Dean through the Requirements Completion Excel list. No screenshot upload is needed.'
WHERE requirement_code IN ('CCS_FACULTY_EVALUATION','CCS_FOURTH_YEAR_FACULTY_EVALUATION');
