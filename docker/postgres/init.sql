-- Creates the application's runtime role. It deliberately has NO SUPERUSER
-- and NO BYPASSRLS, and it owns the app database, so it can run migrations
-- (DDL) while still being subject to Row Level Security policies that are
-- declared with FORCE ROW LEVEL SECURITY (see the enable-RLS migration).
DO
$$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'aulaix_app') THEN
        CREATE ROLE aulaix_app WITH LOGIN PASSWORD 'aulaix_app_password' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
    END IF;
END
$$;

ALTER DATABASE aulaix OWNER TO aulaix_app;
GRANT ALL PRIVILEGES ON DATABASE aulaix TO aulaix_app;

-- Separate database for the automated test suite (RefreshDatabase wipes it
-- on every run), so `php artisan test` never touches dev/seed data.
SELECT 'CREATE DATABASE aulaix_testing OWNER aulaix_app'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'aulaix_testing')\gexec
GRANT ALL PRIVILEGES ON DATABASE aulaix_testing TO aulaix_app;
