# Stage 1: Cài đặt dependencies và build
FROM node:18-bullseye AS builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY prisma ./prisma/
RUN npx prisma generate
COPY . .

# Stage 2: Chạy ứng dụng
FROM node:18-bullseye
WORKDIR /app
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/prisma ./prisma
COPY --from=builder /app/src ./src
COPY --from=builder /app/package*.json ./

COPY docker-entrypoint.sh /app/docker-entrypoint.sh
RUN chmod +x /app/docker-entrypoint.sh

# Port mà service sẽ lắng nghe bên trong container
EXPOSE 3001

CMD ["/app/docker-entrypoint.sh"]