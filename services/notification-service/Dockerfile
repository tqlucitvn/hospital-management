FROM node:18-bullseye
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY src ./src
EXPOSE 3004
CMD ["node","src/index.js"]