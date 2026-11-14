#!/bin/sh
set -eu
trap 'exit 0' TERM INT
last_maintenance=0
while :; do
    php /var/www/html/bin/publish-social.php || echo 'Social worker failed; inspect application state.' >&2
    now=$(date +%s)
    if [ "$((now - last_maintenance))" -ge 86400 ]; then
        php /var/www/html/bin/maintenance.php || echo 'Maintenance failed.' >&2
        last_maintenance=$now
    fi
    sleep 60 &
    wait "$!" || true
done
