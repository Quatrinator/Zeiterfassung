#!/bin/sh
set -eu
export MYSQL_PWD="$(cat /run/secrets/db_root_password)"
version="$(mysql --user=root --batch --skip-column-names zeitwerk -e "SELECT COUNT(*) FROM schema_versions WHERE version='002_activity_templates'")"
if [ "$version" = "0" ]; then
  mysql --user=root --default-character-set=utf8mb4 zeitwerk < /opt/zeitwerk/migrations/002_activity_templates.sql
  echo "Migration 002_activity_templates angewendet."
else
  echo "Migration 002_activity_templates bereits vorhanden."
fi
mysql --user=root zeitwerk -e "GRANT SELECT, INSERT, DELETE ON zeitwerk.activity_templates TO 'zeitwerk'@'%'"
