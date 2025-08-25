#!/usr/bin/env bash
set -e

echo "Prisma schema sync (patient-service)..."
npx prisma db push

echo "Starting patient-service..."
exec node src/index.js