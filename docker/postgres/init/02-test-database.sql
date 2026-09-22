-- Base de datos dedicada a la suite de tests (XP: tests sin tocar datos de dev).
SELECT 'CREATE DATABASE appsolicitudes_test OWNER appsolicitudes'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'appsolicitudes_test')\gexec
