package main

import (
	"io/ioutil"
	"os"
	"context"
	"fmt"
	"log"
	"net/http"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/go-redis/redis/v8"
	"github.com/gorilla/websocket"
	"golang.org/x/sync/singleflight"
)

var (
	rdb           *redis.Client
	ctx           = context.Background()
	requestGroup  singleflight.Group // キャッシュスクラム（一斉消失時の負荷集中）を防ぐ防壁
	wsUpgrader    = websocket.Upgrader{CheckOrigin: func(r *http.Request) bool { return true }}
	clientManager = ClientManager{clients: make(map[*websocket.Conn]bool)}
)

type ClientManager struct {
	clients map[*websocket.Conn]bool
	mu      sync.Mutex
}

func main() {
	// Redis初期化
	rdb = redis.NewClient(&redis.Options{Addr: "localhost:6379"})

	// バックグラウンドでRedisからのPub/Subイベントの常時監視を開始
	go startSignalSubscriber()

	gin.SetMode(gin.ReleaseMode)
	r := gin.Default()

	// CORSミドルウェア
	r.Use(func(c *gin.Context) {
		c.Writer.Header().Set("Access-Control-Allow-Origin", "*")
		c.Writer.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
		c.Writer.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		if c.Request.Method == "OPTIONS" {
			c.AbortWithStatus(204)
			return
		}
		c.Next()
	})

	// 1. 高速READ API (CQRS)
	r.GET("/api/v1/events", getEventsCachedHandler)
	// 2. WebSocket エンドポイント
	r.GET("/ws/signals", handleWebSocketConnections)

	log.Println("🚀 High-Speed Go Engine successfully booted on :8080")
	r.Run(":8080")
}

// Cache-Aside + Singleflight パターンによる高速データ読み込み
func getEventsCachedHandler(c *gin.Context) {
	userID := c.Query("user_id")
	start := c.Query("start")
	end := c.Query("end")


// パラメータが無くても、エラーで落とさずに処理を続行させる
if start == "" || end == "" {
    log.Println("Warning: start or end parameter is missing, but reading all data.")
}

// 共有JSONファイル（またはRDB）からデータを読み込む
jsonString := fetchFromDatabaseSQL(userID, start, end)

// 【重要】もしGinがc.JSONでデータを二重エスケープするのを防ぐため、c.Dataで生のJSONとして撃ち出す
c.Data(http.StatusOK, "application/json; charset=utf-8", []byte(jsonString))

	cacheKey := fmt.Sprintf("user:%s:events:%s:%s", userID, start, end)

	// パス1: Redisキャッシュの確認
	cachedData, err := rdb.Get(ctx, cacheKey).Result()
	if err == nil {
		c.Header("X-Cache", "HIT")
		c.Data(http.StatusOK, "application/json; charset=utf-8", []byte(cachedData))
		return
	}

	// パス2: キャッシュミス時。数万の同時リクエストがあっても、下の内部処理は「最初の1人のみ」に制限される
	c.Header("X-Cache", "MISS")
	data, _, _ := requestGroup.Do(cacheKey, func() (interface{}, error) {
		// 実際にはここでデータベース、またはLaravelの内部リードエンドポイントを叩く
		freshJSON := fetchFromDatabaseSQL(userID, start, end)
		
		// キャッシュを書き戻す（有効期限10分）
		rdb.Set(ctx, cacheKey, freshJSON, 10*time.Minute)
		return freshJSON, nil
	})

	c.Data(http.StatusOK, "application/json; charset=utf-8", []byte(data.(string)))
}

// Redis Pub/Sub からメッセージを受け取り、接続中のWebSocketクライアント全員に送信
func startSignalSubscriber() {
	pubsub := rdb.Subscribe(ctx, "calendar_signals")
	defer pubsub.Close()

	for msg := range pubsub.Channel() {
		clientManager.mu.Lock()
		for client := range clientManager.clients {
			if err := client.WriteMessage(websocket.TextMessage, []byte(msg.Payload)); err != nil {
				client.Close()
				delete(clientManager.clients, client)
			}
		}
		clientManager.mu.Unlock()
	}
}

func handleWebSocketConnections(c *gin.Context) {
	ws, err := wsUpgrader.Upgrade(c.Writer, c.Request, nil)
	if err != nil {
		return
	}
	defer ws.Close()

	clientManager.mu.Lock()
	clientManager.clients[ws] = true
	clientManager.mu.Unlock()

	for {
		if _, _, err := ws.ReadMessage(); err != nil {
			clientManager.mu.Lock()
			delete(clientManager.clients, ws)
			clientManager.mu.Unlock()
			break
		}
	}
}

// 固定のモックデータを卒業し、共有ファイルを読み込むように変更
func fetchFromDatabaseSQL(userID, start, end string) string {
	jsonFilePath := "./events.json"

	// ファイルが存在するか確認
	if _, err := os.Stat(jsonFilePath); os.IsNotExist(err) {
		// ファイルがない場合は初期状態の空配列を返す
		return "[]"
	}

	// ファイルを読み込む
	fileData, err := ioutil.ReadFile(jsonFilePath)
	if err != nil {
		log.Println("ファイル読み込みエラー:", err)
		return "[]"
	}

	return string(fileData)
}