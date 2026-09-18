#!/bin/sh
set -eu
export MYSQL_PWD="$(cat /run/secrets/db_root_password)"
umask 077
mysqldump --user=root --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 --result-file=/tmp/zeitwerk-backup.sql zeitwerk
echo "Konsistente Sicherung in /tmp/zeitwerk-backup.sql erstellt."
