#!/bin/sh
set -e
echo "[prescription-service] db push..."
npx prisma db push
exec node src/index.js