FROM node:25-alpine

WORKDIR /var/www/html

# node_modules is installed here (Linux binaries for Vite/rolldown's native
# bindings) rather than reused from a host bind mount, which may carry
# Windows/macOS-only native binaries. docker-compose.yml shadows the bind
# mount's node_modules with an anonymous volume seeded from this layer.
COPY package.json package-lock.json ./
RUN npm install

CMD ["npm", "run", "dev", "--", "--host"]
