#!/bin/sh
# Start dockerd inside the act job container on first docker call.
if ! /usr/bin/docker info >/dev/null 2>&1; then
  (dockerd >/tmp/dockerd.log 2>&1 &)
  i=0
  until /usr/bin/docker info >/dev/null 2>&1; do
    i=$((i + 1))
    [ "$i" -gt 60 ] && { cat /tmp/dockerd.log >&2; exit 1; }
    sleep 0.5
  done
fi
exec /usr/bin/docker "$@"
