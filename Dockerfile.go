# ステージ1: ビルド環境
FROM golang:1.22-alpine AS builder
WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -ldflags="-w -s" -o engine .

# ステージ2: 実行環境
FROM scratch
COPY --from=builder /app/engine /engine
EXPOSE 8080
ENTRYPOINT ["/engine"]