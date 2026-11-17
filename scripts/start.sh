#!/bin/sh
set -eu
PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$PROJECT_DIR"
# Separate docker build calls also work with older Compose/BuildKit versions.
docker build -t mini-booking_backend:latest ./backend
docker build -t mini-booking_frontend:latest ./frontend
if docker compose version >/dev/null 2>&1; then
    docker compose up -d --no-build
else
    docker-compose up -d --no-build
fi
