FROM golang:1.22-alpine AS builder

ARG K6_VERSION=v0.50.0
RUN apk add --no-cache git make bash ca-certificates && update-ca-certificates

# Build k6 from source (Go-based)
RUN git clone --depth 1 --branch ${K6_VERSION} https://github.com/grafana/k6.git /src/k6 \
 && cd /src/k6 \
 && go build -trimpath -ldflags="-s -w" -o /usr/local/bin/k6 ./cmd/k6

FROM alpine:3.20
RUN apk add --no-cache ca-certificates libc6-compat && update-ca-certificates
COPY --from=builder /usr/local/bin/k6 /usr/local/bin/k6

WORKDIR /scripts
# Scripts are mounted via volume; keep an entrypoint compatible with previous usage
ENTRYPOINT ["k6"]
CMD ["run", "/scripts/all-scenarios-test.js"]
