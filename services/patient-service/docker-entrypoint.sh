#!/usr/bin/env bash
set -e

echo "Prisma schema sync (patient-service)..."
npx prisma db push --force-reset

echo "Starting patient-service..."
exec node src/index.js