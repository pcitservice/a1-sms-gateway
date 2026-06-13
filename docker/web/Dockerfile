# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# A1 SMS Gateway — Next.js 15 web image (App Router, standalone output)
# Build context is the repo root; all COPY paths are root-relative.
# -----------------------------------------------------------------------------

FROM node:20-alpine AS deps
WORKDIR /app
RUN apk add --no-cache libc6-compat
COPY web/package.json web/package-lock.json* ./
# No lockfile yet → use `install`, not `ci`.
# --legacy-peer-deps absorbs the React 19 vs. recharts peer-dep mismatch.
RUN npm install --no-audit --no-fund --legacy-peer-deps

FROM node:20-alpine AS build
WORKDIR /app
ARG NEXT_PUBLIC_API_URL
ARG NEXT_PUBLIC_APP_NAME
ARG NEXT_PUBLIC_STRIPE_KEY
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL \
    NEXT_PUBLIC_APP_NAME=$NEXT_PUBLIC_APP_NAME \
    NEXT_PUBLIC_STRIPE_KEY=$NEXT_PUBLIC_STRIPE_KEY \
    NEXT_TELEMETRY_DISABLED=1
COPY --from=deps /app/node_modules ./node_modules
COPY web/ .
RUN npm run build

FROM node:20-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 PORT=3000 HOSTNAME=0.0.0.0
RUN addgroup -g 1001 -S nodejs && adduser -S nextjs -u 1001 -G nodejs
COPY --from=build --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=build --chown=nextjs:nodejs /app/.next/static     ./.next/static
COPY --from=build --chown=nextjs:nodejs /app/public           ./public
USER nextjs
EXPOSE 3000
HEALTHCHECK --interval=15s --timeout=5s --retries=5 --start-period=10s \
  CMD wget -qO- http://127.0.0.1:3000/api/health >/dev/null || exit 1
CMD ["node", "server.js"]
