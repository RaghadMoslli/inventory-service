#!/bin/sh
# يشغّل Postgres كـ Streaming Replica (قراءة فقط) من الـ Primary.
# أول تشغيل (أو إذا كان المجلد ليس replica): ينسخ الـ Primary بـ pg_basebackup ثم يبقى يستقبل التغييرات لحظيًا.
set -e

PGDATA="${PGDATA:-/var/lib/postgresql/data}"
PRIMARY_HOST="${PRIMARY_HOST:-db}"
PRIMARY_PORT="${PRIMARY_PORT:-5432}"
REPL_USER="${REPL_USER:-postgres}"

mkdir -p "$PGDATA"
chown -R postgres:postgres "$PGDATA"
chmod 700 "$PGDATA"

if [ ! -f "$PGDATA/standby.signal" ]; then
  echo "[replica] no standby.signal -> taking a fresh base backup from $PRIMARY_HOST"
  until PGPASSWORD="$POSTGRES_PASSWORD" pg_isready -h "$PRIMARY_HOST" -p "$PRIMARY_PORT" -U "$REPL_USER"; do
    echo "[replica] waiting for primary..."; sleep 2
  done
  rm -rf "$PGDATA"/* "$PGDATA"/.[!.]* 2>/dev/null || true
  su-exec postgres env PGPASSWORD="$POSTGRES_PASSWORD" \
    pg_basebackup -h "$PRIMARY_HOST" -p "$PRIMARY_PORT" -U "$REPL_USER" -D "$PGDATA" -R -X stream -P
  # نكتب primary_conninfo صراحةً (مع كلمة السر) كي يعمل البث بعد كل إعادة تشغيل
  echo "primary_conninfo = 'host=$PRIMARY_HOST port=$PRIMARY_PORT user=$REPL_USER password=$POSTGRES_PASSWORD'" >> "$PGDATA/postgresql.auto.conf"
  chown -R postgres:postgres "$PGDATA"
  chmod 700 "$PGDATA"
fi

echo "[replica] starting in standby (read-only) mode"
exec su-exec postgres postgres -c hot_standby=on
