-- CBE Dean requirements:
-- non-graduating students (Years 1-3) need the YBC department organization sign-off;
-- fourth-year students need OJT requirements.

UPDATE requirements
SET name = 'CBE Organization (YBC) Sign-off',
    description = 'Secure the required sign-off from YBC, the CBE department organization.',
    minimum_year_level = 1,
    maximum_year_level = 3
WHERE requirement_code = 'CBE_YBC_APPROVAL';

UPDATE requirements
SET name = 'OJT Requirements',
    description = 'Complete and verify all required OJT documents and obligations.',
    minimum_year_level = 4,
    maximum_year_level = 4
WHERE requirement_code = 'CBE_OJT_REQUIREMENTS';
