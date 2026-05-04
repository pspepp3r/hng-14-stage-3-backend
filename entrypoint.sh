#!/bin/sh
set -e

php bin/migrate

exec "$0"
