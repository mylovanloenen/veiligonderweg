#!/bin/bash
# Runs once on first container start: creates the test database with PostGIS.
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE veiligonderweg_test;
    CREATE EXTENSION IF NOT EXISTS postgis;
EOSQL
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname veiligonderweg_test <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS postgis;
EOSQL
