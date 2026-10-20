-- Keep middle names separate without guessing how existing multiword given names split.
ALTER TABLE students
    ADD COLUMN middle_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name;
