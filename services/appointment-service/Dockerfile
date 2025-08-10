FROM node:18-bullseye AS builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY prisma ./prisma/
RUN npx prisma generate
COPY . .

FROM node:18-bullseye
WORKDIR /app
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/prisma ./prisma
COPY --from=builder /app/src ./src
COPY --from=builder /app/package*.json ./
EXPOSE 3003
CMD ["npm","run","start:with-sync"]