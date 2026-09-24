-- One Dean account and assignment for each college.
SET @default_password_hash = '$2y$10$uQswrLolO/5TDzUEnUIO6OK.Qamb4Qc5EU9NObepBzip7zIGdLsAi';

UPDATE users
SET email = 'dean.ccs@thelewiscollege.edu.ph',
    password_hash = @default_password_hash,
    status = 'ACTIVE'
WHERE role = 'DEAN' AND email = 'dean@thelewiscollege.edu.ph';

INSERT INTO users (email,password_hash,role,status)
VALUES
    ('dean.ccs@thelewiscollege.edu.ph',@default_password_hash,'DEAN','ACTIVE'),
    ('dean.cbe@thelewiscollege.edu.ph',@default_password_hash,'DEAN','ACTIVE'),
    ('dean.cte@thelewiscollege.edu.ph',@default_password_hash,'DEAN','ACTIVE')
ON DUPLICATE KEY UPDATE
    password_hash=VALUES(password_hash), role='DEAN', status='ACTIVE';

DELETE assignment
FROM staff_assignments assignment
INNER JOIN users dean ON dean.id=assignment.user_id AND dean.role='DEAN';

INSERT INTO staff_assignments (user_id,station_id,organization_id,status)
SELECT dean.id,station.id,organization.id,'ACTIVE'
FROM users dean
INNER JOIN organizations organization
    ON organization.code=UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX(dean.email,'@',1),'.',-1))
INNER JOIN stations station ON station.code='DEAN'
WHERE dean.email IN (
    'dean.ccs@thelewiscollege.edu.ph',
    'dean.cbe@thelewiscollege.edu.ph',
    'dean.cte@thelewiscollege.edu.ph'
);
