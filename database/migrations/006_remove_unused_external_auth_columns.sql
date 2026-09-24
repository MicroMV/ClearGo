-- ClearGo uses local GSuite-address/password authentication.
-- These external identity provider fields are not read or written by the application.
ALTER TABLE users
    DROP INDEX external_auth_id,
    DROP COLUMN external_auth_id,
    DROP COLUMN auth_provider;
