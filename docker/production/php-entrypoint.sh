#!/bin/sh
set -eu

database_path="${DB_DATABASE:-/var/www/database/database.sqlite}"
database_directory="$(dirname "$database_path")"
backup_directory="${BACKUP_STORAGE_PATH:-/var/www/storage/backups}"
uploads_directory="${UPLOADS_STORAGE_PATH:-/var/www/public/uploads}"
database_exists=0

if [ -s "$database_path" ]; then
    database_exists=1
fi

mkdir -p "$database_directory" "$backup_directory" "$uploads_directory"
chown -R www-data:www-data "$database_directory" "$backup_directory" "$uploads_directory"

if [ "$uploads_directory" != "/var/www/public/uploads" ]; then
    rm -rf /var/www/public/uploads
    ln -s "$uploads_directory" /var/www/public/uploads
fi

if [ "$database_exists" -eq 0 ] && [ "${APP_ENV:-production}" = "production" ]; then
    case "${ADMIN_USERNAME:-}" in
        ""|admin|CHANGE_ME_ADMIN_USER)
            echo "First-run ADMIN_USERNAME must be set to a non-default value." >&2
            exit 1
            ;;
    esac

    case "${ADMIN_PASSWORD:-}" in
        ""|ChangeThisPassword|CHANGE_ME_ADMIN_PASSWORD)
            echo "First-run ADMIN_PASSWORD must be set to a unique production secret." >&2
            exit 1
            ;;
    esac
fi

su-exec www-data php /var/www/bin/greennet database:migrate

exec "$@"
