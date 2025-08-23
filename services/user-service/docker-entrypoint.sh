#!/usr/bin/env bash
set -e

echo "Prisma schema sync..."
npx prisma db push

echo "Starting service..."
exec node src/index.js