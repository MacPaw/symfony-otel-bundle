FROM golang:1.22-alpine AS builder

ARG K6_VERSION=v0.50.0
ARG XK6_DASHBOARD_VERSION=v0.7.5
RUN apk add --no-cache git make bash ca-certificates && update-ca-certificates

# Install xk6 to build k6 with extensions
RUN go install go.k6.io/xk6/cmd/xk6@latest

# Build k6 with xk6-dashboard extension for HTML reports and web dashboard
RUN xk6 build ${K6_VERSION} \
    --with github.com/grafana/xk6-dashboard@${XK6_DASHBOARD_VERSION} \
    --output /usr/local/bin/k6

FROM alpine:3.20
RUN apk add --no-cache ca-certificates libc6-compat && update-ca-certificates
COPY --from=builder /usr/local/bin/k6 /usr/local/bin/k6

WORKDIR /scripts
# Create directory for HTML reports
RUN mkdir -p /reports

# Scripts are mounted via volume; keep an entrypoint compatible with previous usage
ENTRYPOINT ["k6"]
CMD ["run", "/scripts/all-scenarios-test.js"]
