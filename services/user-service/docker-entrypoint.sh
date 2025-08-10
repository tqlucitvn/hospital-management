#!/bin/sh
set -e

echo "Prisma schema sync..."
npx prisma db push

echo "Starting service..."
node src/index.js