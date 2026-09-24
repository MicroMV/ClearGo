-- Remove the technical Admin role and establish the school-account password policy.

SET @initial_password_hash = '$2y$10$txroUUwRau9/iGrltloQdOvJyM/m9yVD1MxuTKRY48WY/jrw5FlXq';

-- Existing development addresses are converted to the school GSuite domain.
UPDATE users
SET email = CONCAT(SUBSTRING_INDEX(email, '@', 1), '@thelewiscollege.edu.ph')
WHERE email LIKE '%@school.test';

-- Admin accounts are no longer part of the application.
DELETE FROM users WHERE role = 'ADMIN';

ALTER TABLE users
    MODIFY COLUMN role ENUM(
        'STUDENT','ORG_STAFF','DEAN','SASO_STAFF','STUDENT_COUNCIL_STAFF',
        'LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF',
        'ACCOUNTING_STAFF','REGISTRAR'
    ) NOT NULL;

UPDATE users
SET password_hash = @initial_password_hash;

