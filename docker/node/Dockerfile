FROM node:24-alpine

RUN apk add --no-cache dumb-init

WORKDIR /var/www/node

# Copy package files first for better layer caching
COPY node/package*.json ./

RUN npm install

COPY node/ .

USER node

EXPOSE 3000

# Use dumb-init for proper signal handling
ENTRYPOINT ["dumb-init", "--"]
CMD ["node", "server.js"]
