#!/bin/sh
set -eu
if [ "${1:-}" != "--replace-database-contents" ]; then
  echo "Aufruf: sh /opt/zeitwerk/restore.sh --replace-database-contents" >&2
  exit 1
fi
test -s /tmp/zeitwerk-restore.sql
export MYSQL_PWD="$(cat /run/secrets/db_root_password)"
mysql --user=root --default-character-set=utf8mb4 zeitwerk < /tmp/zeitwerk-restore.sql
rm /tmp/zeitwerk-restore.sql
echo "Datenbank wiederhergestellt."
