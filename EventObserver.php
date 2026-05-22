<?php

namespace Observers;

use Event;
use Redis;

class EventObserver
{
    /**
     * データが作成、更新、削除された瞬間に、Goが参照するRedisキャッシュを安全にバースト（破棄）する
     */
    public function saved(Event $event): void {
        $this->invalidateCache($event->user_id);
    }

    public function deleted(Event $event): void {
        $this->invalidateCache($event->user_id);
    }

    private function invalidateCache(int $userId): void {
        $redis = Redis::connection();
        // Goのキー命名規則 "user:{id}:events:*" にマッチするものを検索
        $keys = $redis->keys("user:{$userId}:events:*");

        if (!empty($keys)) {
            $prefix = config('database.redis.options.prefix', '');
            $cleanKeys = array_map(fn($key) => str_replace($prefix, '', $key), $keys);
            $redis->del($cleanKeys);
        }
    }
}